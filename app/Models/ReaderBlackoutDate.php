<?php

// v1.0 — 2026-11-07 | Video consulting Phase 1 — one-off exception to a reader's recurring
//                      ReaderAvailabilityRule windows.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReaderBlackoutDate extends Model
{
    protected $fillable = [
        'reader_id',
        'start_at',
        'end_at',
        'reason',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at'   => 'datetime',
    ];

    public function reader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reader_id');
    }
}
