<?php

// v1.1 — 2026-06-12 | Add urlForOrder() — get-or-create a token and return its public URL
// v1.0 — 2026-05-30 | Initial: followup question token linking order → customer form URL

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FollowupToken extends Model
{
    protected $fillable = [
        'token',
        'order_number',
        'assignment_ids',
        'customer_email',
        'expires_at',
    ];

    protected $casts = [
        'assignment_ids' => 'array',
        'expires_at'     => 'datetime',
    ];

    public function followupQuestions(): HasMany
    {
        return $this->hasMany(FollowupQuestion::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Get (or create) an unexpired token for the given order and return its public followup URL.
     */
    public static function urlForOrder(string $orderNumber, array $assignmentIds = []): string
    {
        $existing = static::where('order_number', $orderNumber)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (! $existing) {
            $existing = static::create([
                'token'          => bin2hex(random_bytes(32)),
                'order_number'   => $orderNumber,
                'assignment_ids' => $assignmentIds,
                'customer_email' => null,
                'expires_at'     => now()->addDays(30),
            ]);
        }

        return route('followup.show', $existing->token);
    }
}
