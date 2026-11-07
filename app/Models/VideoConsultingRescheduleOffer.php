<?php

// v1.0 — 2026-11-09 | Video consulting Phase 3 — reschedule offer token. Modeled directly on
//                      FollowupToken's token pattern (no login required to use it).

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoConsultingRescheduleOffer extends Model
{
    public const STATUS_PENDING  = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_EXPIRED  = 'expired';

    protected $fillable = [
        'assignment_id',
        'token',
        'candidate_slots',
        'chosen_slot',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'candidate_slots' => 'array',
        'chosen_slot'     => 'datetime',
        'expires_at'      => 'datetime',
    ];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING && ! $this->isExpired();
    }
}
