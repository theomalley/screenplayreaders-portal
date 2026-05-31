<?php

// v1.0 — 2026-05-31 | Reader-to-admin notes on assignments, with admin replies and per-user dismissal

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssignmentNote extends Model
{
    protected $fillable = ['assignment_id', 'user_id', 'body', 'dismissed_by'];

    protected $casts = ['dismissed_by' => 'array'];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(AssignmentNoteReply::class);
    }

    public function isDismissedBy(int $userId): bool
    {
        return in_array($userId, $this->dismissed_by ?? []);
    }

    public function dismiss(int $userId): void
    {
        $ids = $this->dismissed_by ?? [];
        if (! in_array($userId, $ids)) {
            $ids[] = $userId;
            $this->update(['dismissed_by' => $ids]);
        }
    }
}
