<?php

// v1.1 — 2026-06-02 | Add expires_at (expiration date) and scopeActive() for banner filtering
// v1.0 — 2026-05-26 | Reader announcements with per-user read/dismiss state.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Announcement extends Model
{
    protected $fillable = ['body', 'expires_at', 'created_by'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    /** Only announcements that have not yet expired. */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /** Admins can edit any announcement; editors can only edit their own. */
    public function canBeEditedBy(User $user): bool
    {
        return $user->isAdmin() || ($user->isEditor() && $this->created_by === $user->id);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(AnnouncementRead::class);
    }
}
