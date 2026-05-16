<?php

// v1.1 — 2026-05-16 | index, create, store implemented. accept/cancel/edit stubs remain.

namespace App\Http\Controllers;

use App\Http\Requests\StoreAssignmentRequest;
use App\Models\Assignment;
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

            return view('assignments.index', compact('assignments'));
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

        return view('assignments.index', compact('available', 'mine'));
    }

    public function create()
    {
        $this->authorize('create', Assignment::class);

        $readers = User::where('role', 'reader')
            ->with('readerProfile')
            ->orderBy('name')
            ->get();

        return view('assignments.create', compact('readers'));
    }

    public function store(StoreAssignmentRequest $request)
    {
        $data = $request->validated();
        $data['rush'] = $request->boolean('rush');

        if (($data['status'] ?? '') === Assignment::STATUS_UNASSIGNED) {
            $data['unassigned_at'] = now();
        }

        Assignment::create($data);

        return redirect()->route('assignments.index')->with('success', 'Assignment created.');
    }

    public function edit(Assignment $assignment)
    {
        $this->authorize('update', $assignment);
        // TODO: edit form
    }

    public function update(Request $request, Assignment $assignment)
    {
        $this->authorize('update', $assignment);
        // TODO: validate + update; handle status transitions
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
