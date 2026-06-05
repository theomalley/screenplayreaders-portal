<?php

// v1.0 — 2026-06-05 | Personal reading notes — per-user, per-assignment, private.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReaderScriptNote extends Model
{
    protected $fillable = ['assignment_id', 'user_id', 'body'];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
