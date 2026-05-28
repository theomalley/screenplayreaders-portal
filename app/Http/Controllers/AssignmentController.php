<?php

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
use App\Models\Setting;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\GoogleDriveService;
use App\Support\FilenameGenerator;
use App\Support\PayPeriod;
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

            $assignments = Assignment::with(['assignedReader.readerProfile', 'assignedReader.editorProfile', 'requestedReader.readerProfile', 'helpscoutConversation'])
                ->where('status', '!=', Assignment::STATUS_COMPLETED)
                ->whereNotIn('assignment_type', $formattingTypes)
                ->orderBy('created_at', 'asc')
                ->get();

            $formatting = Assignment::with(['helpscoutConversation'])
                ->where('status', '!=', Assignment::STATUS_COMPLETED)
                ->whereIn('assignment_type', $formattingTypes)
                ->orderBy('created_at', 'desc')
                ->get();

            $editors = User::where('role', 'editor')
                ->with(['editorProfile', 'assignments' => fn($q) => $q->where('status', Assignment::STATUS_ASSIGNED)])
                ->orderBy('name')
                ->get();

            $readers = User::where('role', 'reader')
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

            return view('assignments.index', [
                'canManage'        => true,
                'assignments'      => $assignments,
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
            ]);
        }

        // Reader: available pool (rush first, oldest first) + their own active assignments
        $available = Assignment::available($user->id)
            ->with(['requestedReader.readerProfile'])
            ->orderByRaw('rush DESC')
            ->orderBy('unassigned_at', 'asc')
            ->get();

        [$periodStart, $periodEnd] = PayPeriod::current();

        $mine = Assignment::forReader($user->id)
            ->with(['requestedReader.readerProfile', 'coverageSubmission'])
            ->orderBy('accepted_at', 'desc')
            ->get();

        // Completed assignments from before the current pay period go to the Archived tab
        $archived = Assignment::where('assigned_reader_id', $user->id)
            ->where('status', Assignment::STATUS_COMPLETED)
            ->whereNotNull('completed_at')
            ->where('completed_at', '<', $periodStart)
            ->with(['coverageSubmission'])
            ->orderBy('completed_at', 'desc')
            ->get();

        $archivedByPeriod = $archived->groupBy(
            fn ($a) => PayPeriod::start($a->completed_at)->format('Y-m-d H:i:s')
        )->sortKeysDesc();

        $profile        = $user->readerProfile;
        $capacityOverride = (int) \App\Models\Setting::getValue('capacity_override', 0);
        $readerMax      = $capacityOverride > 0 ? $capacityOverride : (int) ($profile?->max_concurrent_assignments ?? 0);

        return view('assignments.index', [
            'canManage'        => false,
            'available'        => $available,
            'mine'             => $mine,
            'readerMax'        => $readerMax,
            'periodStart'      => $periodStart,
            'periodEnd'        => $periodEnd,
            'archivedByPeriod' => $archivedByPeriod,
            'ageThresholds'    => Setting::getAgeThresholds(),
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

        return view('assignments.create', compact('rates', 'readers'));
    }

    public function store(StoreAssignmentRequest $request)
    {
        $this->authorize('create', Assignment::class);

        $data         = $request->validated();
        $data['rush'] = $request->boolean('rush');
        $numReaders   = (int) $data['num_readers'];

        // Extract per-slot reader IDs then strip form-only keys from $data
        $readerIds = [
            $data['requested_reader_id_1'] ?? null,
            $data['requested_reader_id_2'] ?? null,
            $data['requested_reader_id_3'] ?? null,
        ];
        unset($data['num_readers'], $data['requested_reader_id_1'], $data['requested_reader_id_2'], $data['requested_reader_id_3']);

        $firstAssignment = null;

        if ($numReaders === 1) {
            $data['requested_reader_id'] = $readerIds[0];
            $data['pay_rate']            = (float) ($data['pay_rate'] ?: 0);
            if ($data['status'] === Assignment::STATUS_UNASSIGNED) {
                $data['unassigned_at'] = now();
            }
            $firstAssignment = Assignment::create($data);
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
            }
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

        if ($requestedReaderId) {
            $total += (float) ($vendor === 'sr' ? $rates['rate_sr_request'] : $rates['rate_wd_request']);
        }

        return round($total, 2);
    }

    public function removePages(Request $request, Assignment $assignment)
    {
        $this->authorize('update', $assignment);

        abort_unless($assignment->drive_script_file_id, 422, 'No script on file.');

        $request->validate([
            'pages' => 'required|string|max:200',
        ]);

        $drive     = app(\App\Services\GoogleDriveService::class);
        $rawInput  = trim($request->input('pages'));

        // "last" is a special token — resolve to actual last page number
        if ($rawInput === 'last') {
            $tmp       = $drive->downloadToTemp($assignment->drive_script_file_id);
            $pdf       = new \setasign\Fpdi\Fpdi();
            $pageCount = $pdf->setSourceFile($tmp);
            @unlink($tmp);
            $pages = [$pageCount];
        } else {
            $pages = array_values(array_filter(
                array_map('intval', explode(',', $rawInput)),
                fn($n) => $n > 0,
            ));
        }

        abort_if(empty($pages), 422, 'No valid page numbers provided.');

        try {
            $drive->deletePages($assignment->drive_script_file_id, $pages);
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        $label = count($pages) === 1
            ? 'Page ' . $pages[0] . ' removed.'
            : count($pages) . ' pages removed.';

        return redirect()->back()->with('success', $label);
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
            $dlUrl       = ($fileId && $user->isAdminOrEditor()) ? "https://drive.google.com/uc?export=download&id={$fileId}" : null;
            $dlLabel     = 'Download Script';
        }

        return view('assignments.show', compact(
            'assignment', 'viewLink', 'viewerLabel', 'dlUrl', 'dlLabel',
            'isMultiReader', 'siblings'
        ));
    }

    public function streamScript(Assignment $assignment, GoogleDriveService $drive)
    {
        $this->authorize('view', $assignment);

        abort_unless($assignment->drive_script_file_id, 404);

        $contents = $drive->downloadContents($assignment->drive_script_file_id);

        return response($contents, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="script.pdf"',
            'Cache-Control'       => 'private, no-store',
            'X-Frame-Options'     => 'SAMEORIGIN',
        ]);
    }

    public function streamCoverage(Assignment $assignment, GoogleDriveService $drive)
    {
        abort_unless(auth()->user()->isAdminOrEditor(), 403);
        abort_unless($assignment->drive_coverage_pdf_id, 404);

        $contents = $drive->downloadContents($assignment->drive_coverage_pdf_id);

        return response($contents, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="coverage.pdf"',
            'Cache-Control'       => 'private, no-store',
            'X-Frame-Options'     => 'SAMEORIGIN',
        ]);
    }

    public function edit(Assignment $assignment)
    {
        $this->authorize('update', $assignment);

        $assignment->loadMissing('invoices');

        $rates           = Setting::ratesForForms();
        $readers         = User::where('role', 'reader')->with('readerProfile')->orderBy('name')->get();
        $assignableUsers = $this->assignableUsers();
        $appTimezone     = Setting::getAppTimezone();

        return view('assignments.edit', compact('assignment', 'rates', 'readers', 'assignableUsers', 'appTimezone'));
    }

    public function update(UpdateAssignmentRequest $request, Assignment $assignment)
    {
        $this->authorize('update', $assignment);

        $data         = $request->validated();
        $data['rush'] = $request->boolean('rush');

        $newCreatedAt = null;
        if (!empty($data['date']) && !empty($data['time'])) {
            $newCreatedAt = Carbon::createFromFormat('Y-m-d H:i', $data['date'] . ' ' . $data['time'], Setting::getAppTimezone())
                ->utc();
        }
        unset($data['date'], $data['time']);

        if ($data['status'] === Assignment::STATUS_UNASSIGNED
            && $assignment->status !== Assignment::STATUS_UNASSIGNED) {
            $data['unassigned_at'] = now();
        }

        if ($data['status'] === Assignment::STATUS_COMPLETED
            && $assignment->status !== Assignment::STATUS_COMPLETED) {
            $data['completed_at'] = now();
        }

        if ($data['status'] === Assignment::STATUS_UNASSIGNED) {
            $data['assigned_reader_id'] = null;
            $data['accepted_at']        = null;
        }

        if (!empty($data['assigned_reader_id'])) {
            abort_unless($this->canAssign((int) $data['assigned_reader_id']), 403);
        }

        $assignment->update($data);

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
            'status'             => ['required', 'in:incoming,unassigned,assigned,completed,qc,cancelled,on_hold_customer,on_hold_sr,needs_attention'],
            'assigned_reader_id' => ['nullable', 'exists:users,id'],
        ]);

        $data = ['status' => $request->status];

        if ($request->status === Assignment::STATUS_UNASSIGNED
            && $assignment->status !== Assignment::STATUS_UNASSIGNED) {
            $data['unassigned_at'] = now();
        }

        if ($request->status === Assignment::STATUS_UNASSIGNED) {
            $data['assigned_reader_id'] = null;
            $data['accepted_at']        = null;
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

            $profile = $user->readerProfile;
            $isRequestedForMe = $fresh->requested_reader_id === $user->id;
            if ($profile && $profile->isAtCapacity(isRequestedAssignment: $isRequestedForMe)) {
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
        $assignment->load('invoices');

        // Don't double-invoice
        if ($assignment->invoices->isNotEmpty()) {
            return '';
        }

        try {
            $invoice = app(InvoiceService::class)->generate(
                client:     $client,
                description: $this->buildInvoiceDescription($assignment),
                amount:     $amount,
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
            'deep_dive'  => 'Development Notes',
            'budget'     => 'Budget Coverage',
            'short'      => 'Short Coverage',
            default      => 'Script Coverage',
        };

        if ($assignment->vendor === 'wd') {
            $typeLabel = "Writer's Digest Coverage";
        }

        return "{$typeLabel} — {$assignment->script_title} (Order #{$assignment->order_number})";
    }
}
