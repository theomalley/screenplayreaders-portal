<?php

// v1.2 — 2026-05-18 | multi-reader assignments; per-slot reader request dropdowns.

namespace App\Http\Controllers;

use App\Http\Requests\StoreAssignmentRequest;
use App\Http\Requests\UpdateAssignmentRequest;
use App\Models\Assignment;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssignmentController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Assignment::class);

        $user = auth()->user();

        if ($user->canManageAssignments()) {
            $assignments = Assignment::with(['assignedReader.readerProfile', 'requestedReader.readerProfile'])
                ->orderByRaw("FIELD(status, 'unassigned', 'assigned', 'qc', 'completed', 'incoming', 'on_hold', 'cancelled')")
                ->orderByRaw('rush DESC')
                ->orderBy('unassigned_at', 'asc')
                ->orderBy('created_at', 'desc')
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
            (int) ($data['requested_reader_id_1'] ?: 0) ?: null,
            (int) ($data['requested_reader_id_2'] ?: 0) ?: null,
            (int) ($data['requested_reader_id_3'] ?: 0) ?: null,
        ];
        unset($data['num_readers'], $data['requested_reader_id_1'], $data['requested_reader_id_2'], $data['requested_reader_id_3']);

        if ($numReaders === 1) {
            $data['requested_reader_id'] = $readerIds[0];
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

        $user = auth()->user();

        DB::transaction(function () use ($assignment, $user) {
            $fresh = Assignment::lockForUpdate()->findOrFail($assignment->id);

            abort_if($fresh->status !== Assignment::STATUS_UNASSIGNED, 409, 'Assignment no longer available.');

            $profile = $user->readerProfile;
            if ($profile && $profile->isAtCapacity()) {
                abort(409, 'You are at your maximum concurrent assignments.');
            }

            $fresh->update([
                'status'             => Assignment::STATUS_ASSIGNED,
                'assigned_reader_id' => $user->id,
                'accepted_at'        => now(),
            ]);
        });

        return back()->with('success', 'Assignment accepted.');
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
