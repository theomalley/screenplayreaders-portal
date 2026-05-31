<?php

// v1.0 — 2026-05-31 | Admin/editor replies to reader assignment notes

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentNoteReply extends Model
{
    protected $fillable = ['assignment_note_id', 'user_id', 'body', 'dismissed_by'];

    protected $casts = ['dismissed_by' => 'array'];

    public function note(): BelongsTo
    {
        return $this->belongsTo(AssignmentNote::class, 'assignment_note_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
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
