<?php

// v1.0 — 2026-06-09 | Persisted text highlights on a script's PDF, per-user, per-assignment, per-page.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScriptHighlight extends Model
{
    protected $fillable = ['assignment_id', 'user_id', 'page_number', 'text', 'rects', 'color'];

    protected $casts = [
        'rects' => 'array',
    ];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
