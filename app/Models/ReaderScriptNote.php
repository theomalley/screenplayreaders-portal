<?php

// v1.1 — 2026-06-09 | Add page_number — auto-logged from the PDF viewer's current page when a note is taken.
// v1.0 — 2026-06-05 | Personal reading notes — per-user, per-assignment, private.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReaderScriptNote extends Model
{
    protected $fillable = ['assignment_id', 'user_id', 'body', 'page_number'];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
