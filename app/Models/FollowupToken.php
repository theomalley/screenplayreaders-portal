<?php

// v1.2 — 2026-11-22 | BUG FIX: urlForOrder() reused an existing unexpired token as-is,
//                     never updating its assignment_ids to the newly-passed set. On a
//                     multi-reader order, QcController/ArchiveController::redraftGoback()
//                     call this repeatedly over the order's lifecycle (once per QC
//                     action/redraft) — if the token was first created from an early
//                     completed assignment and reused later as more assignments
//                     completed, the customer's followup link permanently reflected only
//                     the original, now-stale assignment set for the token's full 30-day
//                     life. Now merges in any newly-passed IDs on every call.
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
        } elseif (! empty($assignmentIds)) {
            $merged = array_values(array_unique(array_merge($existing->assignment_ids ?? [], $assignmentIds)));
            if ($merged !== ($existing->assignment_ids ?? [])) {
                $existing->update(['assignment_ids' => $merged]);
            }
        }

        return route('followup.show', $existing->token);
    }
}
