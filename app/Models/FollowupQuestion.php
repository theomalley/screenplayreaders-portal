<?php

// v1.0 — 2026-05-30 | Initial: followup question linked to a specific reader assignment slot

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FollowupQuestion extends Model
{
    public const STATUS_PENDING    = 'pending';
    public const STATUS_UNANSWERED = 'unanswered';
    public const STATUS_ANSWERED   = 'answered';
    public const STATUS_COMPLETE   = 'complete';

    protected $fillable = [
        'followup_token_id',
        'assignment_id',
        'order_number',
        'customer_questions',
        'edited_questions',
        'reader_response',
        'edited_response',
        'status',
        'unanswered_at',
    ];

    protected $casts = [
        'unanswered_at' => 'datetime',
    ];

    public function token(): BelongsTo
    {
        return $this->belongsTo(FollowupToken::class, 'followup_token_id');
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    /** The text shown to the reader — edited version preferred, falls back to raw. */
    public function questionsForReader(): ?string
    {
        return $this->edited_questions ?? $this->customer_questions;
    }

    /** The text sent to the customer — edited version preferred, falls back to raw. */
    public function responseForCustomer(): ?string
    {
        return $this->edited_response ?? $this->reader_response;
    }

    /** Deadline for the 10-day countdown (null if clock hasn't started). */
    public function deadlineAt(): ?\Carbon\Carbon
    {
        return $this->unanswered_at?->copy()->addDays(10);
    }

    public function isEditable(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
