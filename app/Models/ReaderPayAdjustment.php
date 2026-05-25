<?php

// v1.0 — 2026-05-25 | Admin-added manual pay adjustments (positive or negative) for readers

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReaderPayAdjustment extends Model
{
    protected $fillable = [
        'user_id',
        'amount',
        'description',
        'added_by_user_id',
        'reader_paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount'         => 'decimal:2',
            'reader_paid_at' => 'datetime',
        ];
    }

    public function reader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_user_id');
    }
}
