<?php

// v1.0 — 2026-11-07 | Video consulting Phase 1 — short-TTL slot reservation. See migration
//                      2026_11_07_000005 for the race-safe unique-index guarantee behind this.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoConsultingHold extends Model
{
    public const STATUS_HELD      = 'held';
    public const STATUS_CONVERTED = 'converted';

    protected $fillable = [
        'reader_id',
        'duration_id',
        'slot_start',
        'slot_end',
        'hold_token',
        'status',
        'expires_at',
        'converted_assignment_id',
    ];

    protected $casts = [
        'slot_start' => 'datetime',
        'slot_end'   => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function reader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reader_id');
    }

    public function duration(): BelongsTo
    {
        return $this->belongsTo(VideoConsultingDuration::class, 'duration_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_HELD && ! $this->isExpired();
    }
}
