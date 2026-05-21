<?php

// v1.0 — 2026-05-16 | Initial scaffold: reader profile linked 1:1 to users

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReaderProfile extends Model
{
    protected $fillable = [
        'user_id',
        'initials',
        'first_name',
        'last_name',
        'photo',
        'max_concurrent_assignments',
        'paypal_email',
        'phone',
        'sms_notifications',
    ];

    protected $casts = [
        'sms_notifications' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Assignments currently held by this reader */
    public function activeAssignments(): HasMany
    {
        return $this->user->assignments()
                          ->whereIn('status', [Assignment::STATUS_ASSIGNED]);
    }

    public function isAtCapacity(): bool
    {
        return $this->user->assignments()
                          ->where('status', Assignment::STATUS_ASSIGNED)
                          ->count() >= $this->max_concurrent_assignments;
    }

    public function displayName(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }
}
