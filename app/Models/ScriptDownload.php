<?php

// v1.0 — 2026-06-10 | Audit log + signed-link tracking for reader/admin/editor script downloads.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScriptDownload extends Model
{
    protected $fillable = [
        'assignment_id',
        'user_id',
        'token',
        'expires_at',
        'used_at',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at'    => 'datetime',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * One of: 'used', 'direct', 'expired', 'active'.
     */
    public function getStatusAttribute(): string
    {
        if ($this->used_at !== null) {
            return 'used';
        }

        if ($this->expires_at === null) {
            return 'direct';
        }

        return $this->expires_at->isPast() ? 'expired' : 'active';
    }
}
