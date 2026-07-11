<?php

// v2.26 — 2026-07-11 | Tier-0 onboarding: reader branch gains isTierZero + a read-only
//                      browse-all-assignments list + self-healing sandbox provisioning.
//                      Admin branch: tier2Assignments is now explicit (tier == 2, was != 1,
//                      which silently swallowed tier-0 rows) plus a new sandboxAssignments bucket.
// v2.25 — 2026-07-10 | Fix: update() now notifies the requested reader when an admin adds/changes
//                      requested_reader_id on an assignment that was already sitting in
//                      Available/unassigned — previously notifyNewAssignment() only fired on a
//                      status transition *into* unassigned, so a request added without a status
//                      change silently sent no email.
// v2.24 — 2026-06-17 | dismissCancelled(): per-user dismissal of cancelled assignments from board.
//                      Admin index(): filter out personally-dismissed cancelled rows.
//                      Reader index(): surface undismissed cancelled assignments in available view.
// v2.23 — 2026-06-16 | store()/update(): handle exempt_from_capacity; accept(): pass rush flag
//                      to isAtCapacity(); reader index(): pass capacityOverrideExcludesRushRequests.
// v2.22 — 2026-06-15 | helpscoutDraftsReady: dismissal is now shared across admins/editors
//                      (any one of them dismissing clears it for everyone), not per-user.
// v2.21 — 2026-06-15 | dismissHelpscoutDraft() also logs to Notification History.
// v2.20 — 2026-06-15 | Add duplicate() — admin/editor clones an assignment (script, writer,
//                      pay rate, type, etc.) into a fresh "incoming" draft for editing.
// v2.19 — 2026-06-13 | store()/update(): accept blocked_reader_ids[] (manual reader blocking
//                      for editors/admins); update() syncs the block list across all sibling
//                      assignments for the order.
// v2.18 — 2026-06-12 | create(): pass assignableUsers + appTimezone to view for Assigned Reader
//                      field and Upload Date/Time labels. store(): coerce empty-string FK fields
//                      to null (same fix as v2.16), authorize assigned_reader_id via canAssign(),
//                      set oversized_fee_included/exempt_from_word_counts from request, and wire
//                      date/time inputs to created_at (same pattern as update()'s $newCreatedAt).
// v2.17 — 2026-06-12 | Fix: update() auto-promotes status unassigned->assigned when admin picks
//                      an Assigned Reader without changing the Status dropdown — previously the
//                      unassigned branch silently nulled the reader selection on save.
// v2.16 — 2026-06-12 | Fix: update() coerces empty-string requested_reader_id/assigned_reader_id
//                      to null before save — MySQL strict mode rejected '' for these FK columns,
//                      causing the whole assignment update to fail.
// v2.15 — 2026-06-12 | Add regenerateDiscountCode() — admin/editor can regenerate the order's
//                      WooCommerce discount coupon from the edit view.
// v2.14 — 2026-06-11 | index(): pass helpscoutDraftsReady (completed orders w/ undismissed HelpScout
//                      draft) for top-of-page notification; add dismissHelpscoutDraft().
// v2.13 — 2026-06-11 | streamCoverage: filename matches Drive coverage PDF naming convention
//                      (FilenameGenerator::coveragePdf) instead of hardcoded "coverage.pdf".
// v2.12 — 2026-06-10 | downloadScriptForReader: build watermark text from admin-configurable
//                      Setting::getWatermarkSettings() field toggles + custom text.
// v2.11 — 2026-06-10 | downloadScript: readers can download a watermarked, restricted copy via
//                      signed/expiring/single-use links (ScriptDownload audit log).
// v2.10 — 2026-06-07 | removePages/unlockScript return JSON for AJAX requests (PDF in-place refresh)
// v2.9 — 2026-06-07 | Add unlockScript() — strips PDF encryption so page-removal can proceed on locked scripts
// v2.8 — 2026-06-05 | Admin: split assignments by tier; Reader: filter available by reader's tiers
// v2.7 — 2026-06-03 | Reader view: show all non-hidden admins/editors too; clickable peer cards via staff.reader-card.
// v2.6 — 2026-06-03 | Reader view: show all non-hidden readers in staff icon panel (not just online); suppress tooltip on peers.
// v2.5 — 2026-05-30 | Fire reader notifications on status→unassigned in update() and updateStatus().
// v2.4 — 2026-05-30 | Email readers on new unassigned assignment via ReaderNotificationService.
// v2.3 — 2026-05-28 | Reader view: pass onlineEditors + onlineReaders for staff icon panel.
// v2.2 — 2026-05-28 | Deep-Dive Dev Notes includes a free reader request — exclude request fee from pay rate.
// v2.1 — 2026-05-28 | Parse assignment date input in app timezone; pass $appTimezone to index/edit views.
// v2.0 — 2026-05-27 | Pass $ageThresholds from DB settings to view (per-type colour bands).
// v1.9 — 2026-05-27 | Admin/editor My Assignments section: own active assignments shown below main table.
// v1.8 — 2026-05-26 | Admin reader popup: show this-week and last-week completed counts + pay.
// v1.7 — 2026-05-26 | Reader view: Completed This Week (current pay period only) + Archived tab with pay-period grouping + search.
// v1.6 — 2026-05-26 | Invoice checkbox on create/edit forms; triggers InvoiceService on save.
// v1.4 — 2026-05-23 | coverage stream endpoint; show coverage PDF in viewer for admins.
// v1.3 — 2026-05-21 | script upload, page deletion, assignment show view.
// v1.2 — 2026-05-18 | multi-reader assignments; per-slot reader request dropdowns.

namespace App\Http\Controllers;

use App\Http\Requests\StoreAssignmentRequest;
use App\Http\Requests\UpdateAssignmentRequest;
use App\Models\Assignment;
use App\Models\Client;
use App\Models\AssignmentNote;
use App\Models\FollowupQuestion;
use App\Models\FollowupToken;
use App\Models\NotificationHistory;
use App\Models\ScriptDownload;
use App\Models\Setting;
use App\Models\User;
use App\Services\HelpScoutService;
use App\Services\InvoiceService;
use App\Services\GoogleDriveService;
use App\Services\ReaderNotificationService;
use App\Services\SpacesStorageService;
use App\Support\FilenameGenerator;
use App\Support\PayPeriod;
use App\Support\Permission;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AssignmentController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Assignment::class);

        $user = auth()->user();

        if ($user->canManageAssignments()) {
            $formattingTypes = ['formatting', 'proofreading'];

            $allAssignments = Assignment::with(['assignedReader.readerProfile', 'assignedReader.editorProfile', 'requestedReader.readerProfile', 'helpscoutConversation', 'editorNotes'])
                ->where('status', '!=', Assignment::STATUS_COMPLETED)
                ->whereNotIn('assignment_type', $formattingTypes)
                ->orderBy('created_at', 'asc')
                ->get()
                ->filter(fn($a) => $a->status !== Assignment::STATUS_CANCELLED || ! $a->isCancelledDismissedBy($user->id))
                ->values();

            $tier1Assignments   = $allAssignments->where('tier', 1)->values();
            $tier2Assignments   = $allAssignments->where('tier', 2)->values();
            $sandboxAssignments = $allAssignments->where('tier', 0)->values();

            $formatting = Assignment::with(['helpscoutConversation', 'assignedReader.readerProfile', 'assignedReader.editorProfile'])
                ->where('status', '!=', Assignment::STATUS_COMPLETED)
                ->whereIn('assignment_type', $formattingTypes)
                ->orderBy('created_at', 'desc')
                ->get()
                ->filter(fn($a) => $a->status !== Assignment::STATUS_CANCELLED || ! $a->isCancelledDismissedBy($user->id))
                ->values();

            $editors = User::whereIn('role', ['admin', 'editor'])
                ->where('hidden_from_staff', false)
                ->with(['editorProfile', 'assignments' => fn($q) => $q->where('status', Assignment::STATUS_ASSIGNED)])
                ->orderBy('name')
                ->get();

            $readers = User::where('role', 'reader')
                ->where('hidden_from_staff', false)
                ->with(['readerProfile', 'assignments' => fn($q) => $q->where('status', Assignment::STATUS_ASSIGNED)])
                ->orderBy('name')
                ->get()
                ->sortBy(fn($r) => $r->readerProfile?->availability === 'unavailable' ? 1 : 0);

            [$thisPeriodStart, $thisPeriodEnd] = PayPeriod::current();
            $lastPeriodEnd   = $thisPeriodStart;
            $lastPeriodStart = PayPeriod::start($thisPeriodStart->copy()->subDay());

            $readerIds = $readers->pluck('id');

            $periodCompleted = Assignment::whereIn('assigned_reader_id', $readerIds)
                ->where('status', Assignment::STATUS_COMPLETED)
                ->whereNotNull('completed_at')
                ->where('completed_at', '>=', $lastPeriodStart)
                ->where('completed_at', '<', $thisPeriodEnd)
                ->get(['assigned_reader_id', 'completed_at', 'pay_rate']);

            $readerWeekStats = [];
            foreach ($readerIds as $rid) {
                $thisWeek = $periodCompleted->filter(
                    fn($a) => $a->assigned_reader_id === $rid
                        && $a->completed_at >= $thisPeriodStart
                        && $a->completed_at < $thisPeriodEnd
                );
                $lastWeek = $periodCompleted->filter(
                    fn($a) => $a->assigned_reader_id === $rid
                        && $a->completed_at >= $lastPeriodStart
                        && $a->completed_at < $lastPeriodEnd
                );
                $readerWeekStats[$rid] = [
                    'this_count' => $thisWeek->count(),
                    'this_pay'   => $thisWeek->sum(fn($a) => (float) $a->pay_rate),
                    'last_count' => $lastWeek->count(),
                    'last_pay'   => $lastWeek->sum(fn($a) => (float) $a->pay_rate),
                    'this_label' => PayPeriod::label($thisPeriodStart),
                    'last_label' => PayPeriod::label($lastPeriodStart),
                ];
            }

            $archivedAll = Assignment::with(['assignedReader.readerProfile'])
                ->where('status', Assignment::STATUS_COMPLETED)
                ->orderBy('completed_at', 'desc')
                ->get();

            $myAssignments = Assignment::where('assigned_reader_id', $user->id)
                ->whereIn('status', [
                    Assignment::STATUS_ASSIGNED,
                    Assignment::STATUS_QC,
                    Assignment::STATUS_NEEDS_ATTENTION,
                ])
                ->with(['coverageSubmission'])
                ->orderBy('accepted_at', 'desc')
                ->get();

            $followups = FollowupQuestion::with(['assignment.assignedReader.readerProfile', 'assignment.helpscoutConversation'])
                ->whereIn('status', [FollowupQuestion::STATUS_PENDING, FollowupQuestion::STATUS_UNANSWERED, FollowupQuestion::STATUS_ANSWERED])
                ->orderBy('created_at', 'desc')
                ->get();

            $pendingApprovals = User::where(function ($q) {
                    $q->whereHas('readerProfile', fn($rq) => $rq
                        ->whereNotNull('about_photo_pending')
                        ->orWhereNotNull('bio_pending'))
                      ->orWhereHas('editorProfile', fn($eq) => $eq
                        ->whereNotNull('about_photo_pending')
                        ->orWhereNotNull('bio_pending'));
                })
                ->with(['readerProfile', 'editorProfile'])
                ->get();

            $assignmentNotes = AssignmentNote::with(['assignment', 'author.readerProfile', 'author.editorProfile', 'replies.author'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->filter(fn($n) => ! $n->isDismissedBy($user->id) && $n->replies->isEmpty())
                ->values();

            $helpscoutDraftsReady = Assignment::where('status', Assignment::STATUS_COMPLETED)
                ->whereNotNull('helpscout_draft_sent_at')
                ->whereNull('helpscout_draft_dismissed_by')
                ->with('helpscoutConversation')
                ->orderBy('helpscout_draft_sent_at', 'desc')
                ->get()
                ->unique('order_number')
                ->values();

            return view('assignments.index', [
                'canManage'        => true,
                'tier1Assignments' => $tier1Assignments,
                'tier2Assignments' => $tier2Assignments,
                'sandboxAssignments' => $sandboxAssignments,
                'formatting'       => $formatting,
                'editors'          => $editors,
                'readers'          => $readers,
                'assignableUsers'  => $this->assignableUsers(),
                'capacityOverride' => (int) Setting::getValue('capacity_override', 0),
                'readerWeekStats'  => $readerWeekStats,
                'archivedAll'      => $archivedAll,
                'myAssignments'    => $myAssignments,
                'ageThresholds'    => Setting::getAgeThresholds(),
                'appTimezone'      => Setting::getAppTimezone(),
                'followups'        => $followups,
                'assignmentNotes'  => $assignmentNotes,
                'pendingApprovals' => $pendingApprovals,
                'helpscoutDraftsReady' => $helpscoutDraftsReady,
            ]);
        }

        // Reader: available pool (rush first, oldest first) + their own active assignments
        $profile      = $user->readerProfile;
        $readerTiers  = $profile ? $profile->tiers() : [1];

        // Tier 0 (onboarding): read-only visibility into every real, published assignment
        // (regardless of tier), plus a self-healing check that the shared sandbox exists.
        $isTierZero = in_array(0, $readerTiers, true);

        $allNonPendingAssignments = null;
        if ($isTierZero) {
            Assignment::ensureSandboxAssignment();

            $allNonPendingAssignments = Assignment::where('status', '!=', Assignment::STATUS_INCOMING)
                ->where('is_test', false)
                ->orderByDesc('created_at')
                ->paginate(50);
        }

        $available = Assignment::available($user->id, $readerTiers)
            ->with(['requestedReader.readerProfile'])
            ->orderByRaw('rush DESC')
            ->orderBy('unassigned_at', 'asc')
            ->get();

        $acceptedRequests = Assignment::acceptedRequests($user->id, $readerTiers)
            ->with(['requestedReader.readerProfile', 'assignedReader.readerProfile'])
            ->get();

        $available = $available->concat($acceptedRequests);

        [$periodStart, $periodEnd] = PayPeriod::current();

        $mine = Assignment::forReader($user->id)
            ->with(['requestedReader.readerProfile', 'coverageSubmission'])
            ->orderBy('accepted_at', 'desc')
            ->get();

        // Archived = completed before current period, OR paid by admin (even if still in current period)
        $archived = Assignment::where('assigned_reader_id', $user->id)
            ->where('status', Assignment::STATUS_COMPLETED)
            ->whereNotNull('completed_at')
            ->where(function ($q) use ($periodStart) {
                $q->where('completed_at', '<', $periodStart)
                  ->orWhereNotNull('reader_paid_at');
            })
            ->with(['coverageSubmission'])
            ->orderBy('completed_at', 'desc')
            ->get();

        $archivedByPeriod = $archived->groupBy(
            fn ($a) => PayPeriod::start($a->completed_at)->format('Y-m-d H:i:s')
        )->sortKeysDesc();

        $capacityOverride = (int) \App\Models\Setting::getValue('capacity_override', 0);
        $readerMax        = $capacityOverride > 0 ? $capacityOverride : (int) ($profile?->max_concurrent_assignments ?? 0);
        $capacityOverrideExcludesRushRequests = (bool) \App\Models\Setting::getValue('capacity_override_excludes_rush_requests', true);

        $staffEditors = User::whereIn('role', ['admin', 'editor'])
            ->where('hidden_from_staff', false)
            ->with(['editorProfile', 'assignments' => fn($q) => $q->where('status', Assignment::STATUS_ASSIGNED)])
            ->orderBy('name')
            ->get();

        $staffReaders = User::where('role', 'reader')
            ->where('hidden_from_staff', false)
            ->with(['readerProfile', 'assignments' => fn($q) => $q->where('status', Assignment::STATUS_ASSIGNED)])
            ->orderBy('name')
            ->get();

        $myFollowups = FollowupQuestion::with(['assignment'])
            ->whereHas('assignment', fn($q) => $q->where('assigned_reader_id', $user->id))
            ->whereIn('status', [FollowupQuestion::STATUS_UNANSWERED, FollowupQuestion::STATUS_ANSWERED])
            ->orderBy('unanswered_at', 'asc')
            ->get();

        // Undismissed replies to this reader's notes
        $myNoteReplies = AssignmentNote::with(['assignment', 'replies.author'])
            ->where('user_id', $user->id)
            ->whereHas('replies')
            ->get()
            ->map(fn($note) => [
                'note'    => $note,
                'replies' => $note->replies->filter(fn($r) => ! $r->isDismissedBy($user->id))->values(),
            ])
            ->filter(fn($item) => $item['replies']->isNotEmpty())
            ->values();

        // Notes the reader has sent (per assignment, for the "Add note" button state)
        $myNotesByAssignment = AssignmentNote::where('user_id', $user->id)
            ->select('assignment_id')
            ->selectRaw('COUNT(*) as note_count')
            ->groupBy('assignment_id')
            ->pluck('note_count', 'assignment_id');

        // Cancelled assignments not yet dismissed by this user — shown as notices until cleared
        $cancelledAssignments = Assignment::where('status', Assignment::STATUS_CANCELLED)
            ->orderByDesc('updated_at')
            ->get()
            ->filter(fn($a) => ! $a->isCancelledDismissedBy($user->id))
            ->values();

        return view('assignments.index', [
            'canManage'              => false,
            'available'              => $available,
            'mine'                   => $mine,
            'isTierZero'             => $isTierZero,
            'allNonPendingAssignments' => $allNonPendingAssignments,
            'readerMax'              => $readerMax,
            'capacityOverrideExcludesRushRequests' => $capacityOverrideExcludesRushRequests,
            'periodStart'            => $periodStart,
            'periodEnd'              => $periodEnd,
            'archivedByPeriod'       => $archivedByPeriod,
            'ageThresholds'          => Setting::getAgeThresholds(),
            'appTimezone'            => Setting::getAppTimezone(),
            'staffEditors'           => $staffEditors,
            'staffReaders'           => $staffReaders,
            'myFollowups'            => $myFollowups,
            'myNoteReplies'          => $myNoteReplies,
            'myNotesByAssignment'    => $myNotesByAssignment,
            'cancelledAssignments'   => $cancelledAssignments,
        ]);
    }

    public function create()
    {
        $this->authorize('create', Assignment::class);

        $rates   = Setting::ratesForForms();
        $readers = User::where('role', 'reader')
            ->with('readerProfile')
            ->orderBy('name')
            ->get();
        $assignableUsers = $this->assignableUsers();
        $appTimezone     = Setting::getAppTimezone();

        return view('assignments.create', compact('rates', 'readers', 'assignableUsers', 'appTimezone'));
    }

    public function store(StoreAssignmentRequest $request)
    {
        $this->authorize('create', Assignment::class);

        $data         = $request->validated();
        $data['rush']                    = $request->boolean('rush');
        $data['proofreading']            = $request->boolean('proofreading');
        $data['oversized_fee_included']  = $request->boolean('oversized_fee_included');
        $data['exempt_from_word_counts'] = $request->boolean('exempt_from_word_counts');
        $data['exempt_from_capacity']    = $request->boolean('exempt_from_capacity');
        $data['tier'] = (int) ($data['tier'] ?? 1) ?: 1;
        $data['blocked_reader_ids'] = !empty($data['blocked_reader_ids'])
            ? array_map('intval', $data['blocked_reader_ids'])
            : null;
        $numReaders   = (int) $data['num_readers'];

        // Empty selects submit '' for these nullable FK columns, which MySQL's
        // strict mode rejects as an invalid integer — coerce to null.
        foreach (['requested_reader_id_1', 'requested_reader_id_2', 'requested_reader_id_3', 'assigned_reader_id'] as $fkField) {
            if (($data[$fkField] ?? null) === '') {
                $data[$fkField] = null;
            }
        }

        if (!empty($data['assigned_reader_id'])) {
            abort_unless($this->canAssign((int) $data['assigned_reader_id']), 403);
        }

        $newCreatedAt = null;
        if (!empty($data['date']) && !empty($data['time'])) {
            $newCreatedAt = Carbon::createFromFormat('Y-m-d H:i', $data['date'] . ' ' . $data['time'], Setting::getAppTimezone());
        }
        unset($data['date'], $data['time']);

        // Extract per-slot reader IDs then strip form-only keys from $data
        $readerIds = [
            $data['requested_reader_id_1'] ?? null,
            $data['requested_reader_id_2'] ?? null,
            $data['requested_reader_id_3'] ?? null,
        ];
        unset($data['num_readers'], $data['requested_reader_id_1'], $data['requested_reader_id_2'], $data['requested_reader_id_3']);

        $firstAssignment    = null;
        $createdAssignments = [];

        if ($numReaders === 1) {
            $data['requested_reader_id'] = $readerIds[0];
            $data['pay_rate']            = (float) ($data['pay_rate'] ?: 0);
            if ($data['status'] === Assignment::STATUS_UNASSIGNED) {
                $data['unassigned_at'] = now();
            }
            $firstAssignment      = Assignment::create($data);
            $createdAssignments[] = $firstAssignment;
        } else {
            $rates              = Setting::ratesForForms();
            $pageCount          = (int) ($data['page_count'] ?? 0);
            $customOversizedFee = (float) $request->input('custom_oversized_fee', 0);

            $types = $numReaders === 2
                ? ['script_coverage', 'notes_only']
                : ['script_coverage', 'notes_only', 'notes_only'];

            $base = $data;
            unset($base['assignment_type'], $base['pay_rate'], $base['requested_reader_id']);

            foreach ($types as $index => $type) {
                $row = array_merge($base, [
                    'assignment_type'     => $type,
                    'requested_reader_id' => $readerIds[$index] ?? null,
                    'pay_rate'            => $this->computePayRate(
                        $rates,
                        $data['vendor'],
                        $type,
                        $data['rush'],
                        $pageCount,
                        $customOversizedFee,
                        $readerIds[$index] ?? null,
                    ),
                ]);
                if ($row['status'] === Assignment::STATUS_UNASSIGNED) {
                    $row['unassigned_at'] = now();
                }
                $created = Assignment::create($row);
                if ($firstAssignment === null) {
                    $firstAssignment = $created;
                }
                $createdAssignments[] = $created;
            }
        }

        if ($newCreatedAt) {
            DB::table('assignments')
                ->whereIn('id', collect($createdAssignments)->pluck('id'))
                ->update(['created_at' => $newCreatedAt->format('Y-m-d H:i:s')]);
        }

        if ($firstAssignment && $request->hasFile('script')) {
            $drive    = app(\App\Services\GoogleDriveService::class);
            $file     = $request->file('script');
            $fileName = FilenameGenerator::script($firstAssignment);
            $fileId   = $drive->uploadScript($firstAssignment->order_number, $file->getPathname(), $fileName);
            Assignment::where('order_number', $firstAssignment->order_number)
                ->update([
                    'drive_script_file_id'  => $fileId,
                    'drive_script_filename' => $fileName,
                ]);
        }

        $invoiceMsg = '';
        if ($firstAssignment && $request->boolean('create_invoice') && $request->filled('invoice_client_id')) {
            $invoiceMsg = $this->maybeGenerateInvoice($request, $firstAssignment);
        }

        $notifier = app(ReaderNotificationService::class);
        foreach ($createdAssignments as $assignment) {
            $notifier->notifyNewAssignment($assignment);
        }

        $label = $numReaders === 1 ? 'Assignment created.' : "{$numReaders} assignments created.";
        $label .= $invoiceMsg;
        return redirect()->route('assignments.index')->with('success', $label);
    }

    private function computePayRate(
        array $rates,
        string $vendor,
        string $type,
        bool $rush,
        int $pageCount,
        float $customOversizedFee,
        ?int $requestedReaderId,
    ): float {
        $baseMap = [
            'sr' => [
                'script_coverage'   => $rates['rate_sr_script_coverage'],
                'notes_only'        => $rates['rate_sr_notes_only'],
                'deep_dive'         => $rates['rate_sr_deep_dive'],
                'short'             => $rates['rate_sr_short'],
                'budget'            => $rates['rate_sr_budget'],
            ],
            'wd' => [
                'coverage'          => $rates['rate_wd_coverage'],
                'development_notes' => $rates['rate_wd_development_notes'],
            ],
        ];

        $total = (float) ($baseMap[$vendor][$type] ?? 0);

        if ($pageCount >= 121 && $pageCount <= 160) {
            $total += (float) ($vendor === 'sr' ? $rates['rate_sr_oversized_121_160'] : $rates['rate_wd_oversized_121_160']);
        } elseif ($pageCount >= 161 && $customOversizedFee > 0) {
            $total += $customOversizedFee;
        }

        if ($rush) {
            $total += (float) ($vendor === 'sr' ? $rates['rate_sr_rush'] : $rates['rate_wd_rush']);
        }

        // Advanced Script Coverage (deep_dive) includes a free reader request — never add the fee
        if ($requestedReaderId && $type !== 'deep_dive') {
            $total += (float) ($vendor === 'sr' ? $rates['rate_sr_request'] : $rates['rate_wd_request']);
        }

        return round($total, 2);
    }

    public function removePages(Request $request, Assignment $assignment)
    {
        $this->authorize('update', $assignment);

        abort_unless($assignment->hasCloudScript(), 422, 'No script on file.');

        $request->validate([
            'pages' => 'required|string|max:200',
        ]);

        $isLocal  = $assignment->drive_script_file_id === '__LOCAL_TEST__';
        $drive    = app(\App\Services\GoogleDriveService::class);
        $rawInput = trim($request->input('pages'));

        // "last" is a special token — resolve to actual last page number
        if ($rawInput === 'last') {
            try {
                $tmp = $isLocal
                    ? (function () { $p = storage_path('app/test-script.pdf'); abort_unless(file_exists($p), 404); return $p; })()
                    : $drive->downloadToTemp($assignment->drive_script_file_id);
                $pdf       = new \setasign\Fpdi\Fpdi();
                $pageCount = $pdf->setSourceFile($tmp);
                if (! $isLocal) @unlink($tmp);
                $pages = [$pageCount];
            } catch (\Throwable $e) {
                if ($request->ajax()) {
                    return response()->json(['success' => false, 'message' => 'Could not determine page count: ' . $e->getMessage()], 422);
                }
                return redirect()->back()->with('error', 'Could not determine page count: ' . $e->getMessage());
            }
        } else {
            $pages = array_values(array_filter(
                array_map('intval', explode(',', $rawInput)),
                fn($n) => $n > 0,
            ));
        }

        abort_if(empty($pages), 422, 'No valid page numbers provided.');

        try {
            if ($isLocal) {
                $localPath = storage_path('app/test-script.pdf');
                abort_unless(file_exists($localPath), 404, 'Test script file not found.');
                $drive->deletePagesLocal($localPath, $pages);
            } else {
                $drive->deletePages($assignment->drive_script_file_id, $pages);
            }
        } catch (\Throwable $e) {
            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Could not remove page: ' . $e->getMessage()], 500);
            }
            return redirect()->back()->with('error', 'Could not remove page: ' . $e->getMessage());
        }

        $label = count($pages) === 1
            ? 'Page ' . $pages[0] . ' removed.'
            : count($pages) . ' pages removed.';

        if ($request->ajax()) {
            return response()->json(['success' => true, 'message' => $label]);
        }

        return redirect()->back()->with('success', $label);
    }

    public function unlockScript(Request $request, Assignment $assignment)
    {
        $this->authorize('update', $assignment);
        abort_unless($assignment->hasCloudScript(), 422, 'No script on file.');
        abort_if($assignment->drive_script_file_id === '__LOCAL_TEST__', 422, 'Cannot unlock test scripts.');

        try {
            app(\App\Services\GoogleDriveService::class)->unlockScript($assignment->drive_script_file_id);
        } catch (\Throwable $e) {
            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Could not unlock PDF: ' . $e->getMessage()], 500);
            }
            return redirect()->back()->with('error', 'Could not unlock PDF: ' . $e->getMessage());
        }

        if ($request->ajax()) {
            return response()->json(['success' => true, 'message' => 'PDF unlocked — you can now remove pages.']);
        }

        return redirect()->back()->with('success', 'PDF unlocked — you can now remove pages.');
    }

    public function uploadScript(Request $request, Assignment $assignment)
    {
        $this->authorize('update', $assignment);

        $request->validate(['script' => 'required|file|mimes:pdf|max:51200']);

        $drive    = app(\App\Services\GoogleDriveService::class);
        $file     = $request->file('script');
        $path     = $file->getPathname();
        $fileName = FilenameGenerator::script($assignment);

        if ($assignment->drive_script_file_id) {
            $drive->replaceFile($assignment->drive_script_file_id, $path, $fileName);
            Assignment::where('order_number', $assignment->order_number)
                ->update(['drive_script_filename' => $fileName]);
        } else {
            $fileId = $drive->uploadScript($assignment->order_number, $path, $fileName);
            Assignment::where('order_number', $assignment->order_number)
                ->update([
                    'drive_script_file_id'  => $fileId,
                    'drive_script_filename' => $fileName,
                ]);
        }

        return redirect()->route('assignments.edit', $assignment)->with('success', 'Script uploaded.');
    }

    public function addReader(Assignment $assignment)
    {
        $this->authorize('update', $assignment);

        abort_unless($assignment->vendor === 'sr', 422, 'Only SR assignments support multiple readers.');
        abort_unless($assignment->order_number, 422, 'Assignment must have an order number.');

        $siblingCount = Assignment::where('order_number', $assignment->order_number)->count();
        abort_if($siblingCount >= 3, 422, 'This order already has 3 readers (maximum).');

        $rates   = Setting::ratesForForms();
        $payRate = $this->computePayRate(
            $rates,
            'sr',
            'notes_only',
            (bool) $assignment->rush,
            (int) $assignment->page_count,
            0,
            null,
        );

        $newStatus = $assignment->status === Assignment::STATUS_UNASSIGNED
            ? Assignment::STATUS_UNASSIGNED
            : Assignment::STATUS_INCOMING;

        Assignment::create([
            'order_number'         => $assignment->order_number,
            'vendor'               => 'sr',
            'assignment_type'      => 'notes_only',
            'script_title'         => $assignment->script_title,
            'writer_name'          => $assignment->writer_name,
            'page_count'           => $assignment->page_count,
            'rush'                 => $assignment->rush,
            'pay_rate'             => $payRate,
            'status'               => $newStatus,
            'unassigned_at'        => $newStatus === Assignment::STATUS_UNASSIGNED ? now() : null,
            'notes'                => $assignment->notes,
            'drive_script_file_id'  => $assignment->drive_script_file_id,
            'drive_script_filename' => $assignment->drive_script_filename,
        ]);

        return back()->with('success', 'Notes-Only assignment added to this order.');
    }

    /**
     * Clone an assignment (script details, pay rate, type, etc.) into a fresh
     * "incoming" draft so an admin/editor can quickly set up a similar
     * assignment without re-entering everything from scratch.
     */
    public function duplicate(Assignment $assignment)
    {
        $this->authorize('duplicate', $assignment);

        $copy = $assignment->replicate([
            'status',
            'assigned_reader_id',
            'requested_reader_id',
            'reader_declined',
            'accepted_at',
            'submitted_at',
            'completed_at',
            'reader_paid_at',
            'unassigned_at',
            'available_at',
            'drive_coverage_doc_id',
            'drive_coverage_pdf_id',
            'helpscout_draft_sent_at',
            'helpscout_draft_dismissed_by',
            'created_at',
            'updated_at',
        ]);

        $copy->status          = Assignment::STATUS_INCOMING;
        $copy->reader_declined = false;
        $copy->save();

        return redirect()->route('assignments.edit', $copy)
            ->with('success', 'Assignment duplicated — review and update the details below.');
    }

    public function show(Assignment $assignment)
    {
        $this->authorize('view', $assignment);

        $user   = auth()->user();
        $fileId = $assignment->drive_script_file_id;

        // Admins viewing a completed order get the N-up coverage layout.
        $isMultiReader = false;
        $siblings      = collect();
        if ($user->isAdminOrEditor() && $assignment->status === Assignment::STATUS_COMPLETED) {
            $siblings = Assignment::where('order_number', $assignment->order_number)
                ->with(['assignedReader.readerProfile'])
                ->orderBy('id')
                ->get();
            $isMultiReader = $siblings->count() > 1;
        }

        // Single-viewer fallback vars (used when isMultiReader is false).
        if ($user->isAdminOrEditor() && $assignment->drive_coverage_pdf_id) {
            $viewLink    = route('assignments.streamCoverage', $assignment);
            $viewerLabel = 'Coverage';
            $dlUrl       = "https://drive.google.com/uc?export=download&id={$assignment->drive_coverage_pdf_id}";
            $dlLabel     = 'Download Coverage';
        } else {
            $viewLink    = $fileId ? route('assignments.streamScript', $assignment) : null;
            $viewerLabel = 'Script';
            $dlUrl       = ($fileId && !$user->isReader() && Permission::check('script.download'))
                ? route('assignments.downloadScript', $assignment)
                : null;
            $dlLabel     = 'Download Script';
        }

        $canDownloadScript = $fileId && $user->isReader() && $assignment->assigned_reader_id === $user->id && Permission::check('script.download');

        return view('assignments.show', compact(
            'assignment', 'viewLink', 'viewerLabel', 'dlUrl', 'dlLabel',
            'isMultiReader', 'siblings', 'canDownloadScript'
        ));
    }

    public function streamScript(Assignment $assignment, GoogleDriveService $drive)
    {
        $this->authorize('view', $assignment);
        abort_unless($assignment->drive_script_file_id, 404);

        $filename = $assignment->drive_script_filename ?? 'script.pdf';

        if ($assignment->drive_script_file_id === '__LOCAL_TEST__') {
            $localPath = storage_path('app/test-script.pdf');
            abort_unless(file_exists($localPath), 404);
            $contents = file_get_contents($localPath);
        } elseif ($assignment->spaces_script_path) {
            $contents = app(SpacesStorageService::class)->get($assignment->spaces_script_path);
        } else {
            $contents = $drive->downloadContents($assignment->drive_script_file_id);
        }

        return response($contents, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . addslashes($filename) . '"',
            'Cache-Control'       => 'private, no-store',
            'X-Frame-Options'     => 'SAMEORIGIN',
        ]);
    }

    public function downloadScript(Request $request, Assignment $assignment, GoogleDriveService $drive)
    {
        $this->authorize('view', $assignment);
        abort_unless($assignment->hasCloudScript(), 404);

        $filename = $assignment->drive_script_filename ?? 'script.pdf';

        if ($request->has('token')) {
            return $this->downloadScriptForReader($request, $assignment, $drive, $filename);
        }

        abort_if(auth()->user()->isReader(), 403);
        abort_unless(Permission::check('script.download'), 403);

        ScriptDownload::create([
            'assignment_id' => $assignment->id,
            'user_id'       => auth()->id(),
            'expires_at'    => null,
            'used_at'       => now(),
            'ip_address'    => $request->ip(),
            'user_agent'    => $request->userAgent(),
        ]);

        if ($assignment->drive_script_file_id === '__LOCAL_TEST__') {
            $localPath = storage_path('app/test-script.pdf');
            abort_unless(file_exists($localPath), 404);
            $contents = file_get_contents($localPath);
        } elseif ($assignment->spaces_script_path) {
            $contents = app(SpacesStorageService::class)->get($assignment->spaces_script_path);
        } else {
            $contents = $drive->downloadContents($assignment->drive_script_file_id);
        }

        return response($contents, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . addslashes($filename) . '"',
            'Cache-Control'       => 'private, no-store',
        ]);
    }

    /**
     * Serve a watermarked, permission-restricted copy via a signed, expiring, single-use link.
     */
    private function downloadScriptForReader(Request $request, Assignment $assignment, GoogleDriveService $drive, string $filename)
    {
        abort_unless($request->hasValidSignature(), 403, 'This download link is invalid or has expired.');

        $scriptDownload = ScriptDownload::where('token', $request->query('token'))
            ->where('assignment_id', $assignment->id)
            ->firstOrFail();

        abort_if($scriptDownload->user_id !== auth()->id(), 403);
        abort_if($scriptDownload->used_at !== null, 410, 'This download link has already been used.');
        abort_if($scriptDownload->expires_at->isPast(), 410, 'This download link has expired.');

        if ($assignment->drive_script_file_id === '__LOCAL_TEST__') {
            $source = storage_path('app/test-script.pdf');
            abort_unless(file_exists($source), 404);
            $tmpSource = tempnam(sys_get_temp_dir(), 'sr_dl_') . '.pdf';
            copy($source, $tmpSource);
        } elseif ($assignment->spaces_script_path) {
            $tmpSource = tempnam(sys_get_temp_dir(), 'sr_dl_') . '.pdf';
            file_put_contents($tmpSource, app(SpacesStorageService::class)->get($assignment->spaces_script_path));
        } else {
            $tmpSource = $drive->downloadToTemp($assignment->drive_script_file_id);
        }

        $wm = Setting::getWatermarkSettings();

        $parts = [];
        if ($wm['watermark_custom_text'] !== '') {
            $parts[] = $wm['watermark_custom_text'];
        }
        if ($wm['watermark_show_name']) {
            $parts[] = auth()->user()->name;
        }
        if ($wm['watermark_show_order']) {
            $parts[] = 'Order #' . $assignment->order_number;
        }
        if ($wm['watermark_show_datetime']) {
            $parts[] = now()->setTimezone(Setting::getAppTimezone())->format('M j, Y g:ia');
        }
        if ($wm['watermark_show_ref']) {
            $parts[] = 'Ref DL-' . $scriptDownload->id;
        }

        $watermarkText = $parts !== [] ? implode(' · ', $parts) : 'Screenplay Readers';

        $output = $drive->watermarkPdf($tmpSource, $watermarkText);

        $scriptDownload->update(['used_at' => now()]);

        return response()->download($output, $filename, [
            'Content-Type'  => 'application/pdf',
            'Cache-Control' => 'private, no-store',
        ])->deleteFileAfterSend(true);
    }

    public function streamCoverage(Assignment $assignment, GoogleDriveService $drive)
    {
        abort_unless(auth()->user()->isAdminOrEditor(), 403);
        abort_unless($assignment->drive_coverage_pdf_id, 404);

        $contents = $assignment->spaces_coverage_pdf_path
            ? app(SpacesStorageService::class)->get($assignment->spaces_coverage_pdf_path)
            : $drive->downloadContents($assignment->drive_coverage_pdf_id);

        $assignment->loadMissing('assignedReader.readerProfile');
        $initials = $assignment->assignedReader?->readerProfile?->initials;
        $filename = FilenameGenerator::coveragePdf($assignment, $initials);

        return response($contents, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . addslashes($filename) . '"',
            'Cache-Control'       => 'private, no-store',
            'X-Frame-Options'     => 'SAMEORIGIN',
        ]);
    }

    /** Dismiss the "goback ready at HelpScout" notification for all admins/editors. */
    public function dismissHelpscoutDraft(Assignment $assignment)
    {
        abort_unless(auth()->user()->isAdminOrEditor(), 403);

        $assignment->dismissHelpscoutDraft(auth()->id());

        NotificationHistory::log(
            auth()->id(),
            "Dismissed HelpScout draft — Order #{$assignment->order_number}",
            $assignment->script_title,
            route('assignments.edit', $assignment)
        );

        return response()->json(['status' => 'ok']);
    }

    /**
     * Generate a fresh $10 WooCommerce discount coupon for this order, replacing
     * any previously generated code (the old coupon remains in WooCommerce but is
     * no longer referenced in the completion email).
     */
    public function regenerateDiscountCode(Assignment $assignment)
    {
        $this->authorize('update', $assignment);

        try {
            Assignment::generateWooDiscountCode($assignment->order_number);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not generate discount code: ' . $e->getMessage());
        }

        return back()->with('success', 'Discount code regenerated.');
    }

    public function edit(Assignment $assignment)
    {
        $this->authorize('update', $assignment);

        $assignment->loadMissing('invoices');

        $rates           = Setting::ratesForForms();
        $readers         = User::where('role', 'reader')->with('readerProfile')->orderBy('name')->get();
        $assignableUsers = $this->assignableUsers();
        $appTimezone     = Setting::getAppTimezone();

        $notes = AssignmentNote::with(['author.readerProfile', 'author.editorProfile', 'replies.author'])
            ->where('assignment_id', $assignment->id)
            ->orderBy('created_at', 'asc')
            ->get();

        $editorNotes = \App\Models\AssignmentEditorNote::with('author.editorProfile')
            ->where('assignment_id', $assignment->id)
            ->orderBy('created_at', 'asc')
            ->get();

        $scriptDownloads = $assignment->scriptDownloads()->with('user')->latest()->get();

        return view('assignments.edit', compact('assignment', 'rates', 'readers', 'assignableUsers', 'appTimezone', 'notes', 'editorNotes', 'scriptDownloads'));
    }

    public function update(UpdateAssignmentRequest $request, Assignment $assignment)
    {
        $this->authorize('update', $assignment);

        $data         = $request->validated();
        $data['rush']                    = $request->boolean('rush');
        $data['proofreading']            = $request->boolean('proofreading');
        $data['exempt_from_word_counts'] = $request->boolean('exempt_from_word_counts');
        $data['oversized_fee_included']  = $request->boolean('oversized_fee_included');
        $data['exempt_from_capacity']    = $request->boolean('exempt_from_capacity');
        $data['tier']                   = (int) ($data['tier'] ?? 1) ?: 1;
        $data['blocked_reader_ids']     = !empty($data['blocked_reader_ids'])
            ? array_map('intval', $data['blocked_reader_ids'])
            : null;

        // Empty selects submit '' for these nullable FK columns, which MySQL's
        // strict mode rejects as an invalid integer — coerce to null.
        foreach (['requested_reader_id', 'assigned_reader_id'] as $fkField) {
            if (($data[$fkField] ?? null) === '') {
                $data[$fkField] = null;
            }
        }

        // Admin picked a reader but left Status as "Available" (unassigned) — promote
        // to "Assigned" so the unassigned branch below doesn't immediately null out
        // the selection. Mirrors updateStatus()'s status=assigned + assigned_reader_id pairing.
        if ($data['status'] === Assignment::STATUS_UNASSIGNED && !empty($data['assigned_reader_id'])) {
            $data['status']          = Assignment::STATUS_ASSIGNED;
            $data['accepted_at']     = now();
            $data['reader_declined'] = false;
        }

        $newCreatedAt = null;
        if (!empty($data['date']) && !empty($data['time'])) {
            $newCreatedAt = Carbon::createFromFormat('Y-m-d H:i', $data['date'] . ' ' . $data['time'], Setting::getAppTimezone());
        }
        unset($data['date'], $data['time']);

        $transitioningToUnassigned = $data['status'] === Assignment::STATUS_UNASSIGNED
            && $assignment->status !== Assignment::STATUS_UNASSIGNED;

        // A request can also be added/changed on an assignment that's already
        // sitting in Available/unassigned, with no status transition to key off of.
        $requestedReaderAdded = $data['status'] === Assignment::STATUS_UNASSIGNED
            && !empty($data['requested_reader_id'])
            && $data['requested_reader_id'] != $assignment->requested_reader_id;

        if ($transitioningToUnassigned) {
            $data['unassigned_at'] = now();
        }

        if ($data['status'] === Assignment::STATUS_COMPLETED
            && $assignment->status !== Assignment::STATUS_COMPLETED) {
            $data['completed_at'] = now();
        }

        if ($data['status'] === Assignment::STATUS_UNASSIGNED) {
            $data['assigned_reader_id'] = null;
            $data['accepted_at']        = null;
            $data['reader_declined']    = false;
            $data['available_at']       = null; // clear any pending auto-release when manually set to Available
        }

        // Convert the datetime-local string to app timezone, or clear if empty
        if (! empty($data['available_at'])) {
            $data['available_at'] = Carbon::createFromFormat(
                'Y-m-d\TH:i',
                $data['available_at'],
                Setting::getAppTimezone()
            );
        } else {
            $data['available_at'] = null;
        }

        if (!empty($data['assigned_reader_id'])) {
            abort_unless($this->canAssign((int) $data['assigned_reader_id']), 403);
        }

        // If a short conversation number was entered (< 10,000,000), resolve it to
        // the large internal HelpScout ID so the URL link works correctly.
        if (! empty($data['helpscout_ticket_number'])
            && is_numeric($data['helpscout_ticket_number'])
            && (int) $data['helpscout_ticket_number'] < 10_000_000) {
            try {
                $resolved = app(HelpScoutService::class)
                    ->findConversationIdByTicketNumber($data['helpscout_ticket_number']);
                if ($resolved) {
                    $data['helpscout_ticket_number'] = $resolved;
                }
            } catch (\Throwable) {
                // Keep whatever the admin entered if the lookup fails
            }
        }

        $assignment->update($data);

        // Blocked readers apply to the whole order, not just this slot — keep
        // every sibling assignment for this order_number in sync.
        if ($assignment->order_number) {
            Assignment::where('order_number', $assignment->order_number)
                ->where('id', '!=', $assignment->id)
                ->update(['blocked_reader_ids' => json_encode($data['blocked_reader_ids'])]);
        }

        if ($transitioningToUnassigned || $requestedReaderAdded) {
            app(ReaderNotificationService::class)->notifyNewAssignment($assignment->fresh());
        }

        if ($newCreatedAt) {
            DB::table('assignments')
                ->where('id', $assignment->id)
                ->update(['created_at' => $newCreatedAt->format('Y-m-d H:i:s')]);
        }

        $invoiceMsg = '';
        if ($request->boolean('create_invoice') && $request->filled('invoice_client_id')) {
            $invoiceMsg = $this->maybeGenerateInvoice($request, $assignment);
        }

        return redirect()->route('assignments.index')->with('success', 'Assignment updated.' . $invoiceMsg);
    }

    public function updateStatus(Request $request, Assignment $assignment)
    {
        $this->authorize('update', $assignment);

        $request->validate([
            'status'              => ['required', 'in:incoming,unassigned,assigned,completed,qc,cancelled,on_hold_customer,on_hold_sr,needs_attention'],
            'assigned_reader_id'  => ['nullable', 'exists:users,id'],
            'cancellation_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $data = ['status' => $request->status];

        if ($request->status === Assignment::STATUS_CANCELLED) {
            $data['cancellation_reason'] = $request->input('cancellation_reason');
        }

        $transitioningToUnassigned = $request->status === Assignment::STATUS_UNASSIGNED
            && $assignment->status !== Assignment::STATUS_UNASSIGNED;

        if ($transitioningToUnassigned) {
            $data['unassigned_at'] = now();
        }

        if ($request->status === Assignment::STATUS_UNASSIGNED) {
            $data['assigned_reader_id'] = null;
            $data['accepted_at']        = null;
            $data['reader_declined']    = false;
        }

        if ($request->status === Assignment::STATUS_ASSIGNED && $request->filled('assigned_reader_id')) {
            abort_unless($this->canAssign((int) $request->assigned_reader_id), 403);
            $data['assigned_reader_id'] = $request->assigned_reader_id;
            $data['accepted_at']        = now();
        }

        if ($request->status === Assignment::STATUS_COMPLETED
            && $assignment->status !== Assignment::STATUS_COMPLETED) {
            $data['completed_at'] = now();
        }

        $assignment->update($data);

        if ($transitioningToUnassigned) {
            app(ReaderNotificationService::class)->notifyNewAssignment($assignment->fresh());
        }

        return back()->with('success', 'Status updated.');
    }

    public function accept(Assignment $assignment)
    {
        $this->authorize('accept', $assignment);

        $user  = auth()->user();
        $error = null;

        DB::transaction(function () use ($assignment, $user, &$error) {
            $fresh = Assignment::lockForUpdate()->findOrFail($assignment->id);

            if ($fresh->status !== Assignment::STATUS_UNASSIGNED) {
                $error = 'This assignment is no longer available.';
                return;
            }

            if ($fresh->requested_reader_id && $fresh->requested_reader_id !== $user->id) {
                $error = 'This assignment is requested for another reader.';
                return;
            }

            // Prevent accepting more than one sibling on a multi-reader order
            $alreadyOnOrder = Assignment::where('order_number', $fresh->order_number)
                ->where('assigned_reader_id', $user->id)
                ->where('id', '!=', $fresh->id)
                ->exists();

            if ($alreadyOnOrder) {
                $error = 'You are already assigned to a coverage on this order.';
                return;
            }

            $profile = $user->readerProfile;
            $isRequestedForMe = $fresh->requested_reader_id === $user->id;
            if ($profile && $profile->isAtCapacity(isRequestedAssignment: $isRequestedForMe, isRushAssignment: (bool) $fresh->rush)) {
                $error = 'You have reached your maximum concurrent assignments.';
                return;
            }

            $fresh->update([
                'status'             => Assignment::STATUS_ASSIGNED,
                'assigned_reader_id' => $user->id,
                'accepted_at'        => now(),
            ]);
        });

        if ($error) {
            return request()->expectsJson()
                ? response()->json(['message' => $error], 422)
                : back()->with('error', $error);
        }

        return request()->expectsJson()
            ? response()->json(['success' => true])
            : back()->with('success', 'Assignment accepted.');
    }

    public function decline(Assignment $assignment)
    {
        $this->authorize('accept', $assignment);

        $user = auth()->user();

        if ($assignment->requested_reader_id !== $user->id) {
            return response()->json(['message' => 'You were not requested for this assignment.'], 403);
        }

        if ($assignment->status !== Assignment::STATUS_UNASSIGNED) {
            return response()->json(['message' => 'This assignment is no longer available.'], 409);
        }

        $assignment->update([
            'status'          => Assignment::STATUS_INCOMING,
            'reader_declined' => true,
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Generate (or return existing) a followup token URL for an order.
     * Finds all assignment slots sharing the same order_number and builds one token covering them all.
     */
    public function generateFollowupToken(Request $request, Assignment $assignment)
    {
        $this->authorize('update', $assignment);

        $orderNumber  = $assignment->order_number;
        $assignments  = Assignment::where('order_number', $orderNumber)
            ->whereNotNull('assigned_reader_id')
            ->get();

        abort_if($assignments->isEmpty(), 422, 'No assigned reader slots found for this order.');

        $existing = FollowupToken::where('order_number', $orderNumber)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (! $existing) {
            $existing = FollowupToken::create([
                'token'          => bin2hex(random_bytes(32)),
                'order_number'   => $orderNumber,
                'assignment_ids' => $assignments->pluck('id')->values()->all(),
                'customer_email' => null,
                'expires_at'     => now()->addDays(30),
            ]);
        }

        $url = route('followup.show', $existing->token);

        if ($request->expectsJson()) {
            return response()->json(['url' => $url]);
        }

        return back()->with('followup_url', $url);
    }

    /**
     * Always create a fresh followup token for an order (or a single reader slot).
     * Pass only_assignment_id in the JSON body to scope the token to one reader.
     */
    public function resetFollowupToken(Request $request, Assignment $assignment)
    {
        $this->authorize('update', $assignment);

        $orderNumber = $assignment->order_number;
        $onlyId      = $request->input('only_assignment_id');

        if ($onlyId) {
            $target = Assignment::where('id', (int) $onlyId)
                ->where('order_number', $orderNumber)
                ->whereNotNull('assigned_reader_id')
                ->first();
            abort_if(! $target, 422, 'Assignment not found for this order.');
            $assignmentIds = [$target->id];
        } else {
            $assignmentIds = Assignment::where('order_number', $orderNumber)
                ->whereNotNull('assigned_reader_id')
                ->pluck('id')
                ->values()
                ->all();
            abort_if(empty($assignmentIds), 422, 'No assigned reader slots found for this order.');
        }

        $token = FollowupToken::create([
            'token'          => bin2hex(random_bytes(32)),
            'order_number'   => $orderNumber,
            'assignment_ids' => $assignmentIds,
            'customer_email' => null,
            'expires_at'     => now()->addDays(30),
        ]);

        $url = route('followup.show', $token->token);

        if ($request->expectsJson()) {
            return response()->json(['url' => $url]);
        }

        return back()->with('followup_url', $url);
    }

    public function destroy(Assignment $assignment)
    {
        $this->authorize('delete', $assignment);

        $drive   = new GoogleDriveService();
        $fileIds = array_filter([
            $assignment->drive_script_file_id,
            $assignment->drive_coverage_doc_id,
            $assignment->drive_coverage_pdf_id,
        ]);

        foreach ($fileIds as $fileId) {
            try {
                $drive->deleteFile($fileId);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Drive file deletion failed on assignment destroy', [
                    'assignment_id' => $assignment->id,
                    'file_id'       => $fileId,
                    'error'         => $e->getMessage(),
                ]);
            }
        }

        $assignment->delete();

        return redirect()->route('assignments.index')->with('success', 'Assignment deleted.');
    }

    private function assignableUsers(): \Illuminate\Database\Eloquent\Collection
    {
        $user = auth()->user();

        if ($user->isAdmin()) {
            return User::whereIn('role', ['admin', 'editor', 'reader'])
                ->with(['readerProfile', 'editorProfile'])
                ->orderBy('name')
                ->get();
        }

        // Editor: readers + self
        return User::where(function ($q) use ($user) {
            $q->where('role', 'reader')->orWhere('id', $user->id);
        })
            ->with(['readerProfile', 'editorProfile'])
            ->orderBy('name')
            ->get();
    }

    private function canAssign(int $userId): bool
    {
        $user   = auth()->user();
        $target = User::find($userId);
        if (!$target) return false;

        if ($user->isAdmin()) return true;

        // Editor: can assign readers or self
        if ($user->isEditor()) {
            return $target->isReader() || $target->id === $user->id;
        }

        return false;
    }

    public function updateNotes(Request $request, Assignment $assignment)
    {
        $this->authorize('update', $assignment);

        $request->validate(['notes' => ['nullable', 'string', 'max:2000']]);

        $assignment->update(['notes' => $request->input('notes')]);

        return response()->json(['success' => true]);
    }

    public function dismissCancelled(Assignment $assignment)
    {
        if ($assignment->status !== Assignment::STATUS_CANCELLED) {
            return response()->json(['ok' => false], 422);
        }

        $assignment->dismissCancelledFor(auth()->id());

        return response()->json(['ok' => true]);
    }

    public function cancel(Assignment $assignment)
    {
        $this->authorize('cancel', $assignment);

        $assignment->update([
            'status'             => Assignment::STATUS_UNASSIGNED,
            'assigned_reader_id' => null,
            'accepted_at'        => null,
        ]);

        return back()->with('success', 'Assignment returned to the pool.');
    }

    private function maybeGenerateInvoice(Request $request, Assignment $assignment): string
    {
        $clientId = (int) $request->input('invoice_client_id');
        $amount   = (float) $request->input('invoice_amount', 0);

        if (! $clientId || $amount <= 0) {
            return '';
        }

        $client = Client::find($clientId);
        if (! $client) {
            return '';
        }

        // Link client to assignment
        $assignment->update(['client_id' => $clientId]);
        $assignment->refresh();

        // Don't double-invoice — check line items directly, since a batch client's
        // invoice never carries the assignment_id at the top level (only its line items do).
        // Voided invoices are ignored so a corrected invoice can be regenerated.
        $alreadyInvoiced = \App\Models\InvoiceLineItem::where('assignment_id', $assignment->id)
            ->whereHas('invoice', fn ($q) => $q->where('status', '!=', 'void'))
            ->exists();

        if ($alreadyInvoiced) {
            return '';
        }

        try {
            $invoice = app(InvoiceService::class)->generate(
                client:     $client,
                lineItems:  [['description' => $this->buildInvoiceDescription($assignment), 'amount' => $amount]],
                assignment: $assignment,
            );
            return " Invoice #{$invoice->invoice_number} generated.";
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Assignment invoice generation failed', [
                'assignment_id' => $assignment->id,
                'error'         => $e->getMessage(),
            ]);
            return " (Invoice generation failed: {$e->getMessage()})";
        }
    }

    private function buildInvoiceDescription(Assignment $assignment): string
    {
        $typeLabel = match ($assignment->assignment_type ?? '') {
            'notes_only' => 'Notes-Only Coverage',
            'deep_dive'  => 'Advanced Script Coverage',
            'budget'     => 'Budget Coverage',
            'short'      => 'Short Coverage',
            default      => 'Script Coverage',
        };

        if ($assignment->vendor === 'wd') {
            $typeLabel = "Writer's Digest Coverage";
        }

        return "{$typeLabel} — {$assignment->script_title} (Order #{$assignment->order_number})";
    }

    public function over120(Assignment $assignment, \App\Services\HelpScoutService $helpScout)
    {
        return $this->pageCountFlagDraft($assignment, $helpScout, Assignment::PAGE_FLAG_OVER_120);
    }

    public function over160(Assignment $assignment, \App\Services\HelpScoutService $helpScout)
    {
        return $this->pageCountFlagDraft($assignment, $helpScout, Assignment::PAGE_FLAG_OVER_160);
    }

    private function pageCountFlagDraft(Assignment $assignment, \App\Services\HelpScoutService $helpScout, string $flag)
    {
        abort_unless(auth()->user()->isAdminOrEditor(), 403);
        abort_unless($assignment->status === Assignment::STATUS_INCOMING, 422);
        abort_unless($assignment->pageCountFlag() === $flag, 422);

        $conversationId = $assignment->helpscoutConversation?->helpscout_conversation_id;
        if (! $conversationId && $assignment->helpscout_ticket_number) {
            $conversationId = $helpScout->findConversationIdByTicketNumber($assignment->helpscout_ticket_number);
        }

        if (! $conversationId) {
            return response()->json(['error' => 'No HelpScout conversation found for this assignment.'], 422);
        }

        try {
            $body = $helpScout->getSavedReplyBody('1441347');
            $body = $helpScout->resolveBodyVariables($body, $conversationId);
            $helpScout->createDraftReply($conversationId, $body);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Draft creation failed: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'url' => 'https://secure.helpscout.net/conversation/' . $conversationId . '/',
        ]);
    }
}
