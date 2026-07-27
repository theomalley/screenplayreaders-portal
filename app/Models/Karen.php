<?php

// v1.0 — 2026-07-27 | Customers we never do business with again. Matched against
//                     incoming webhook-created assignments — see
//                     Api\IncomingAssignmentController::store().

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Karen extends Model
{
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'notes',
        'flagged_date',
    ];

    protected function casts(): array
    {
        return [
            'flagged_date' => 'date',
        ];
    }

    /**
     * Case/whitespace-insensitive match on email, or on first+last name together
     * (a name-only match requires both parts — a bare first name is too common
     * a false-positive risk). Returns the matched row, or null.
     */
    public static function matchFor(?string $firstName, ?string $lastName, ?string $email): ?self
    {
        $email     = trim((string) $email);
        $firstName = trim((string) $firstName);
        $lastName  = trim((string) $lastName);
        $hasName   = $firstName !== '' && $lastName !== '';

        if ($email === '' && ! $hasName) {
            return null;
        }

        return static::query()
            ->where(function ($q) use ($email, $firstName, $lastName, $hasName) {
                if ($email !== '') {
                    $q->orWhereRaw('LOWER(email) = ?', [mb_strtolower($email)]);
                }
                if ($hasName) {
                    $q->orWhere(function ($q2) use ($firstName, $lastName) {
                        $q2->whereRaw('LOWER(first_name) = ?', [mb_strtolower($firstName)])
                           ->whereRaw('LOWER(last_name) = ?', [mb_strtolower($lastName)]);
                    });
                }
            })
            ->first();
    }
}
