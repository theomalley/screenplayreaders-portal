<?php

// v1.24 — 2026-06-16 | Add exempt_from_capacity boolean — assignments marked exempt do not
//                      count toward any reader's concurrent assignment cap.
// v1.23 — 2026-06-15 | helpscout_draft_dismissed_by is now a shared dismissal — any
//                      admin/editor dismissing the "goback ready" notice clears it for
//                      everyone, matching the auto-clear when the draft is actually sent.
// v1.22 — 2026-06-13 | scopeAvailable() no longer hides assignments from blocked readers —
//                      they now see the assignment in their Available pool (with the
//                      "Blocked" badge) but cannot Accept it (enforced in AssignmentPolicy).
// v1.21 — 2026-06-13 | Add blocked_reader_ids (customer/editor "do not assign" list);
//                      isReaderBlocked() + blockedReaderInitials() helpers; scopeAvailable()
//                      excludes blocked readers; blockedReaderInitials() surfaced as a
//                      "Blocked: XX, YY" badge in every assignment line item.
// v1.20 — 2026-06-12 | Add woo_discount_code + generateWooDiscountCode() — portal-generated
//                      $10 single-use coupon, replacing the sr-orders Zap's coupon step.
// v1.19 — 2026-06-11 | Add helpscout_draft_dismissed_by + isHelpscoutDraftDismissedBy/dismissHelpscoutDraft for goback-ready notification
// v1.18 — 2026-06-11 | Add editorNotes() relation for internal-notes indicator on assignment listings
// v1.17 — 2026-06-11 | pageCountFlag() also suppresses over_120 when the linked order has an Oversized Fee line item
// v1.16 — 2026-06-10 | Add oversized_fee_included/manual_page_flag; pageCountFlag() for Over 120/160 badges
// v1.15 — 2026-06-05 | Add tier to fillable/casts; scopeAvailable() filters by reader tiers
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

use App\Services\WooCommerceService;
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
        'proofreading',
        'script_title',
        'writer_name',
        'page_count',
        'requested_reader_id',
        'blocked_reader_ids',
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
        'drive_proofread_pdf_id',
        'assigned_reader_id',
        'public_opt_in',
        'unassigned_at',
        'accepted_at',
        'submitted_at',
        'completed_at',
        'reader_paid_at',
        'helpscout_draft_sent_at',
        'helpscout_draft_dismissed_by',
        'cancelled_dismissed_by',
        'cancellation_reason',
        'woo_discount_code',
        'client_id',
        'reader_declined',
        'available_at',
        'exempt_from_word_counts',
        'is_test',
        'tier',
        'oversized_fee_included',
        'manual_page_flag',
        'exempt_from_capacity',
    ];

    protected function casts(): array
    {
        return [
            'rush'           => 'boolean',
            'blocked_reader_ids' => 'array',
            'public_opt_in'  => 'boolean',
            'pay_rate'       => 'decimal:2',
            'unassigned_at'  => 'datetime',
            'accepted_at'    => 'datetime',
            'submitted_at'   => 'datetime',
            'completed_at'              => 'datetime',
            'reader_paid_at'            => 'datetime',
            'helpscout_draft_sent_at'   => 'datetime',
            'helpscout_draft_dismissed_by' => 'array',
            'cancelled_dismissed_by'       => 'array',
            'reader_declined'           => 'boolean',
            'available_at'              => 'datetime',
            'exempt_from_word_counts'   => 'boolean',
            'is_test'                   => 'boolean',
            'tier'                      => 'integer',
            'proofreading'              => 'boolean',
            'oversized_fee_included'    => 'boolean',
            'exempt_from_capacity'      => 'boolean',
        ];
    }

    // --- Page count flags ---

    public const PAGE_FLAG_OVER_120 = 'over_120';
    public const PAGE_FLAG_OVER_160 = 'over_160';

    // WooCommerce variation IDs for the "Oversized Fee" product (parent 54914)
    public const OVERSIZED_FEE_PRODUCT_IDS = [54923, 54926, 54930];

    /**
     * Returns 'over_160', 'over_120', or null — the page-count flag that should
     * be shown for this assignment. Page counts over 160 always flag, "no matter
     * what". Page counts in 121-160 only flag if the order doesn't already
     * include an oversized fee. A manual override (set when an editor visually
     * inspects the script) takes precedence over the recorded page count.
     */
    public function pageCountFlag(): ?string
    {
        if ($this->manual_page_flag === self::PAGE_FLAG_OVER_160 || (int) $this->page_count > 160) {
            return self::PAGE_FLAG_OVER_160;
        }

        if ($this->manual_page_flag === self::PAGE_FLAG_OVER_120
            || ((int) $this->page_count > 120 && (int) $this->page_count <= 160)) {
            if ($this->oversized_fee_included || $this->orderHasOversizedFee()) {
                return null;
            }
            return self::PAGE_FLAG_OVER_120;
        }

        return null;
    }

    /**
     * True if the assignment's WooCommerce order already includes an
     * Oversized Fee line item (acts as an automatic alternative to the
     * manual oversized_fee_included checkbox).
     */
    public function orderHasOversizedFee(): bool
    {
        $lineItems = $this->orderRevenue?->line_items_json;
        if (! is_array($lineItems)) {
            return false;
        }

        foreach ($lineItems as $item) {
            if (in_array((int) ($item['product_id'] ?? 0), self::OVERSIZED_FEE_PRODUCT_IDS, true)) {
                return true;
            }
        }

        return false;
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

    /** True if the given user is on this assignment's blocked-readers list. */
    public function isReaderBlocked(int $userId): bool
    {
        return \in_array($userId, $this->blocked_reader_ids ?: []);
    }

    /** Initials of every reader blocked from this assignment's order, for display in line items. */
    public function blockedReaderInitials(): array
    {
        if (empty($this->blocked_reader_ids)) {
            return [];
        }

        return User::whereIn('id', $this->blocked_reader_ids)
            ->with('readerProfile')
            ->get()
            ->map(fn (User $u) => $u->readerProfile?->initials ?? $u->name)
            ->all();
    }

    /** Whether the "goback ready at HelpScout" notice for this order has been dismissed (shared across admins/editors). */
    public function isHelpscoutDraftDismissed(): bool
    {
        return ! empty($this->helpscout_draft_dismissed_by);
    }

    public function isCancelledDismissedBy(int $userId): bool
    {
        return in_array($userId, $this->cancelled_dismissed_by ?: []);
    }

    public function dismissCancelledFor(int $userId): void
    {
        $ids = $this->cancelled_dismissed_by ?: [];
        if (! in_array($userId, $ids)) {
            $ids[] = $userId;
            $this->update(['cancelled_dismissed_by' => $ids]);
        }
    }

    /** Dismiss the "goback ready at HelpScout" notice for everyone, across all assignments in this order. */
    public function dismissHelpscoutDraft(int $userId): void
    {
        // Query builder update() bypasses the 'array' cast, so encode manually.
        static::where('order_number', $this->order_number)
            ->whereNotNull('helpscout_draft_sent_at')
            ->update(['helpscout_draft_dismissed_by' => json_encode([$userId])]);
    }

    /**
     * Generate a new $10 single-use WooCommerce discount coupon for this order,
     * store the code on all sibling assignments, and return it. Replaces the
     * coupon-generation step previously done by the sr-orders Zap.
     */
    public static function generateWooDiscountCode(string $orderNumber): string
    {
        $chars  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $suffix = '';
        for ($i = 0; $i < 8; $i++) {
            $suffix .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $code = 'SRZ' . $suffix;

        app(WooCommerceService::class)->createOrderDiscountCoupon($code);

        static::where('order_number', $orderNumber)->update(['woo_discount_code' => $code]);

        return $code;
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

    public function orderRevenue(): HasOne
    {
        return $this->hasOne(OrderRevenue::class, 'order_number', 'order_number');
    }

    public function editorNotes(): HasMany
    {
        return $this->hasMany(AssignmentEditorNote::class);
    }

    public function needsProofreading(): bool
    {
        return $this->assignment_type === 'proofreading'
            || (bool) $this->proofreading;
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function scriptDownloads(): HasMany
    {
        return $this->hasMany(ScriptDownload::class);
    }

    // --- Scopes ---

    /**
     * Assignments visible to a specific reader in the available list, filtered to their tiers.
     * Includes assignments requested for other readers (visible but not acceptble).
     * Assignments that block this reader are still included (so the reader can see why an
     * order is unavailable to them) — AssignmentPolicy::accept() prevents them from accepting.
     */
    public function scopeAvailable($query, int $userId, array $tiers = [1])
    {
        if (empty($tiers)) {
            return $query->whereRaw('1 = 0');
        }
        return $query->where('status', self::STATUS_UNASSIGNED)
            ->whereIn('tier', $tiers);
    }

    /**
     * Requested assignments accepted by other readers — visible to all readers
     * so they can see the request was fulfilled.
     */
    public function scopeAcceptedRequests($query, int $userId, array $tiers = [1])
    {
        if (empty($tiers)) {
            return $query->whereRaw('1 = 0');
        }
        return $query->where('status', self::STATUS_ASSIGNED)
            ->whereIn('tier', $tiers)
            ->whereNotNull('requested_reader_id')
            ->where('requested_reader_id', '!=', $userId);
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
