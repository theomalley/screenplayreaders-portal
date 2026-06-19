<?php

// v1.0 — 2026-06-19 | Proofreading annotations on script PDFs — strikethrough, arrow, and text note marks.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProofreadingMark extends Model
{
    protected $fillable = ['assignment_id', 'user_id', 'page_number', 'type', 'data'];

    protected $casts = [
        'data' => 'array',
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
