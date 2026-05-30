<?php

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
}
