<?php

// v1.0 — 2026-11-07 | Video consulting Phase 1 — a reader's recurring weekly availability window.

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReaderAvailabilityRule extends Model
{
    protected $fillable = [
        'reader_id',
        'day_of_week',
        'start_time',
        'end_time',
        'enabled',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'enabled'     => 'boolean',
    ];

    public function reader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reader_id');
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }
}
