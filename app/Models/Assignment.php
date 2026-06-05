<?php

// v1.14 — 2026-06-05 | Add hasCloudScript() helper — true when drive_script_file_id is a real Drive ID
// v1.13 — 2026-06-04 | Add is_test flag; auto-reset completed test assignments after 4 h
// v1.12 — 2026-06-03 | Add exempt_from_word_counts to fillable and casts
// v1.11 — 2026-06-02 | Add available_at to fillable and casts (scheduled auto-release to Available)
// v1.10 — 2026-05-30 | Add reader_declined to fillable and casts
// v1.9 — 2026-05-28 | Zero pay_rate automatically when assigned reader is an admin
// v1.8 — 2026-05-26 | Add client_id relationship for invoicing
// v1.6 — 2026-05-25 | Add needs_attention status + notes field; scopeForReader includes needs_attention
// v1.5 — 2026-05-25 | Add helpscoutConversation relationship (auto-populated by Zapier via order_number).
// v1.4 — 2026-05-24 | Remove dead isVisibleToReaders() and scopeForAdmin().
// v1.3 — 2026-05-24 | Add helpscout_draft_sent_at to fillable and casts
// v1.2 — 2026-05-17 | Replace author_first_initial/author_last_name with writer_name

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Assignment extends Model
{
    // Status constants — use these everywhere instead of raw strings
    public const STATUS_INCOMING        = 'incoming';
    public const STATUS_UNASSIGNED      = 'unassigned';
    public const STATUS_ASSIGNED        = 'assigned';
    public const STATUS_COMPLETED       = 'completed';
    public const STATUS_QC              = 'qc';
    public const STATUS_CANCELLED       = 'cancelled';
    public const STATUS_ON_HOLD_CUSTOMER  = 'on_hold_customer';
    public const STATUS_ON_HOLD_SR       = 'on_hold_sr';
    public const STATUS_NEEDS_ATTENTION  = 'needs_attention';

    protected $fillable = [
        'order_number',
        'vendor',
        'assignment_type',
        'script_title',
        'writer_name',
        'page_count',
        'requested_reader_id',
        'rush',
        'pay_rate',
        'notes',
        'needs_attention_notes',
        'helpscout_ticket_number',
        'status',
        'drive_script_file_id',
        'drive_script_filename',
        'drive_coverage_doc_id',
        'drive_coverage_pdf_id',
        'assigned_reader_id',
        'public_opt_in',
        'unassigned_at',
        'accepted_at',
        'submitted_at',
        'completed_at',
        'reader_paid_at',
        'helpscout_draft_sent_at',
        'client_id',
        'reader_declined',
        'available_at',
        'exempt_from_word_counts',
        'is_test',
    ];

    protected function casts(): array
    {
        return [
            'rush'           => 'boolean',
            'public_opt_in'  => 'boolean',
            'pay_rate'       => 'decimal:2',
            'unassigned_at'  => 'datetime',
            'accepted_at'    => 'datetime',
            'submitted_at'   => 'datetime',
            'completed_at'              => 'datetime',
            'reader_paid_at'            => 'datetime',
            'helpscout_draft_sent_at'   => 'datetime',
            'reader_declined'           => 'boolean',
            'available_at'              => 'datetime',
            'exempt_from_word_counts'   => 'boolean',
            'is_test'                   => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Assignment $assignment) {
            if ($assignment->assigned_reader_id) {
                $reader = User::find($assignment->assigned_reader_id);
                if ($reader && $reader->isAdmin()) {
                    $assignment->pay_rate = '0.00';
                }
            }
        });

        // Auto-reset test assignments 4 hours after they reach completed
        static::updated(function (Assignment $assignment) {
            if ($assignment->is_test
                && $assignment->status === self::STATUS_COMPLETED
                && $assignment->wasChanged('status')
                && Setting::getValue('test_auto_reset', '0') === '1'
            ) {
                \App\Jobs\ResetTestAssignment::dispatch($assignment->id)
                    ->delay(now()->addHours(4));
            }
        });
    }

    // --- Status helpers ---

    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_UNASSIGNED;
    }

    /** True when a script is attached (Drive file or local test placeholder). */
    public function hasCloudScript(): bool
    {
        return !empty($this->drive_script_file_id);
    }

    // --- Relationships ---

    public function assignedReader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_reader_id');
    }

    public function requestedReader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_reader_id');
    }

    public function coverageSubmission(): HasOne
    {
        return $this->hasOne(CoverageSubmission::class);
    }

    public function helpscoutConversation(): HasOne
    {
        return $this->hasOne(HelpScoutConversation::class, 'order_number', 'order_number');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    // --- Scopes ---

    /** Assignments visible to a specific reader in the available list */
    public function scopeAvailable($query, int $userId)
    {
        return $query->where('status', self::STATUS_UNASSIGNED)
            ->where(function ($q) use ($userId) {
                $q->whereNull('requested_reader_id')
                  ->orWhere('requested_reader_id', $userId);
            });
    }

    /** A reader's own active assignments */
    public function scopeForReader($query, int $userId)
    {
        return $query->where('assigned_reader_id', $userId)
                     ->whereIn('status', [
                         self::STATUS_ASSIGNED,
                         self::STATUS_COMPLETED,
                         self::STATUS_QC,
                         self::STATUS_NEEDS_ATTENTION,
                     ]);
    }
}
