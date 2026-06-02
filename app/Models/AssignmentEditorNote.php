<?php

// v1.0 — 2026-06-02 | Internal editor/admin notes on assignments — not visible to readers

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentEditorNote extends Model
{
    protected $fillable = ['assignment_id', 'user_id', 'body'];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
