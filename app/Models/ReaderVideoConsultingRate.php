<?php

// v1.0 — 2026-11-07 | Video consulting Phase 1 — a reader's own pay rate for one duration.
//                      No row for a given duration means that reader doesn't offer it.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReaderVideoConsultingRate extends Model
{
    protected $fillable = [
        'reader_id',
        'duration_id',
        'rate',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
    ];

    public function reader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reader_id');
    }

    public function duration(): BelongsTo
    {
        return $this->belongsTo(VideoConsultingDuration::class, 'duration_id');
    }
}
