<?php

// v1.0 — 2026-05-25 | Admin-added manual pay adjustments (positive or negative) for editors

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EditorPayAdjustment extends Model
{
    protected $fillable = [
        'user_id',
        'amount',
        'description',
        'added_by_user_id',
        'editor_paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount'          => 'decimal:2',
            'editor_paid_at'  => 'datetime',
        ];
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_user_id');
    }
}
