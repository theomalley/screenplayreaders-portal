<?php

// v1.0 — 2026-05-24 | Initial scaffold: editor profile linked 1:1 to users with role=editor

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EditorProfile extends Model
{
    protected $fillable = [
        'user_id',
        'initials',
        'first_name',
        'last_name',
        'photo',
        'paypal_email',
        'availability',
        'availability_message',
        'upload_warning',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function displayName(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }
}
