<?php

// v1.2 — 2026-05-17 | Replace author_first_initial/author_last_name with writer_name

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Assignment extends Model
{
    // Status constants — use these everywhere instead of raw strings
    const STATUS_INCOMING   = 'incoming';
    const STATUS_UNASSIGNED = 'unassigned';
    const STATUS_ASSIGNED   = 'assigned';
    const STATUS_COMPLETED  = 'completed';
    const STATUS_QC         = 'qc';
    const STATUS_CANCELLED       = 'cancelled';
    const STATUS_ON_HOLD_CUSTOMER = 'on_hold_customer';
    const STATUS_ON_HOLD_SR      = 'on_hold_sr';

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
            'completed_at'   => 'datetime',
        ];
    }

    // --- Status helpers ---

    public function isVisibleToReaders(): bool
    {
        return $this->status === self::STATUS_UNASSIGNED;
    }

    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_UNASSIGNED;
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

    // --- Scopes ---

    /** Assignments visible to readers in the available list */
    public function scopeAvailable($query)
    {
        return $query->where('status', self::STATUS_UNASSIGNED);
    }

    /** All assignments visible to admin/editor */
    public function scopeForAdmin($query)
    {
        return $query->whereNotIn('status', [self::STATUS_INCOMING]);
    }

    /** A reader's own active assignments */
    public function scopeForReader($query, int $userId)
    {
        return $query->where('assigned_reader_id', $userId)
                     ->whereIn('status', [self::STATUS_ASSIGNED, self::STATUS_COMPLETED, self::STATUS_QC]);
    }
}
