<?php

// v1.3 — 2026-05-21 | script upload, page deletion, assignment show view.
// v1.2 — 2026-05-18 | multi-reader assignments; per-slot reader request dropdowns.

namespace App\Http\Controllers;

use App\Http\Requests\StoreAssignmentRequest;
use App\Http\Requests\UpdateAssignmentRequest;
use App\Models\Assignment;
use App\Models\Setting;
use App\Models\User;
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
            $assignments = Assignment::with(['assignedReader.readerProfile', 'requestedReader.readerProfile'])
                ->orderBy('created_at', 'asc')
                ->get();

            $readers = User::where('role', 'reader')
                ->with(['readerProfile', 'assignments' => fn($q) => $q->where('status', Assignment::STATUS_ASSIGNED)])
                ->orderBy('name')
                ->get();

            return view('assignments.index', [
                'canManage'   => true,
                'assignments' => $assignments,
                'readers'     => $readers,
            ]);
        }

        // Reader: available pool (rush first, oldest first) + their own active assignments
        $available = Assignment::available()
            ->with(['requestedReader.readerProfile'])
            ->orderByRaw('rush DESC')
            ->orderBy('unassigned_at', 'asc')
            ->get();

        $mine = Assignment::forReader($user->id)
            ->with(['requestedReader.readerProfile'])
            ->orderBy('accepted_at', 'desc')
            ->get();

        return view('assignments.index', [
            'canManage' => false,
            'available' => $available,
            'mine'      => $mine,
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

        if ($numReaders === 1) {
            $data['requested_reader_id'] = $readerIds[0];
            $data['pay_rate']            = (float) ($data['pay_rate'] ?: 0);
            if ($data['status'] === Assignment::STATUS_UNASSIGNED) {
                $data['unassigned_at'] = now();
            }
            Assignment::create($data);
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
                Assignment::create($row);
            }
        }

        $label = $numReaders === 1 ? 'Assignment created.' : "{$numReaders} assignments created.";
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

        $drive->deletePages($assignment->drive_script_file_id, $pages);

        $label = count($pages) === 1
            ? 'Page ' . $pages[0] . ' removed.'
            : count($pages) . ' pages removed.';

        return redirect()->route('assignments.edit', $assignment)->with('success', $label);
    }

    public function uploadScript(Request $request, Assignment $assignment)
    {
        $this->authorize('update', $assignment);

        $request->validate(['script' => 'required|file|mimes:pdf|max:51200']);

        $drive    = app(\App\Services\GoogleDriveService::class);
        $file     = $request->file('script');
        $path     = $file->getPathname();
        $fileName = $file->getClientOriginalName();

        if ($assignment->drive_script_file_id) {
            $drive->replaceFile($assignment->drive_script_file_id, $path, $fileName);
            $assignment->update(['drive_script_filename' => $fileName]);
        } else {
            $fileId = $drive->uploadScript($assignment->id, $path, $fileName);
            $assignment->update([
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
            'order_number'    => $assignment->order_number,
            'vendor'          => 'sr',
            'assignment_type' => 'notes_only',
            'script_title'    => $assignment->script_title,
            'writer_name'     => $assignment->writer_name,
            'page_count'      => $assignment->page_count,
            'rush'            => $assignment->rush,
            'pay_rate'        => $payRate,
            'status'          => $newStatus,
            'unassigned_at'   => $newStatus === Assignment::STATUS_UNASSIGNED ? now() : null,
            'notes'           => $assignment->notes,
        ]);

        return back()->with('success', 'Notes-Only assignment added to this order.');
    }

    public function show(Assignment $assignment)
    {
        $this->authorize('view', $assignment);

        $fileId   = $assignment->drive_script_file_id;
        $viewLink = $fileId ? "https://drive.google.com/file/d/{$fileId}/preview" : null;
        $dlUrl    = ($fileId && auth()->user()->isAdminOrEditor())
            ? "https://drive.google.com/uc?export=download&id={$fileId}"
            : null;

        return view('assignments.show', compact('assignment', 'viewLink', 'dlUrl'));
    }

    public function edit(Assignment $assignment)
    {
        $this->authorize('update', $assignment);

        $rates   = Setting::ratesForForms();
        $readers = User::where('role', 'reader')
            ->with('readerProfile')
            ->orderBy('name')
            ->get();

        return view('assignments.edit', compact('assignment', 'rates', 'readers'));
    }

    public function update(UpdateAssignmentRequest $request, Assignment $assignment)
    {
        $this->authorize('update', $assignment);

        $data         = $request->validated();
        $data['rush'] = $request->boolean('rush');

        $newCreatedAt = null;
        if (!empty($data['date']) && !empty($data['time'])) {
            $newCreatedAt = Carbon::createFromFormat('Y-m-d H:i', $data['date'] . ' ' . $data['time']);
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

        $assignment->update($data);

        if ($newCreatedAt) {
            DB::table('assignments')
                ->where('id', $assignment->id)
                ->update(['created_at' => $newCreatedAt->format('Y-m-d H:i:s')]);
        }

        return redirect()->route('assignments.index')->with('success', 'Assignment updated.');
    }

    public function updateStatus(Request $request, Assignment $assignment)
    {
        $this->authorize('update', $assignment);

        $request->validate([
            'status'             => ['required', 'in:incoming,unassigned,assigned,completed,qc,cancelled,on_hold'],
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
            if ($profile && $profile->isAtCapacity()) {
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
            return back()->with('error', $error);
        }

        return back()->with('success', 'Assignment accepted.');
    }

    public function destroy(Assignment $assignment)
    {
        $this->authorize('delete', $assignment);

        $assignment->delete();

        return redirect()->route('assignments.index')->with('success', 'Assignment deleted.');
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
}
