<?php

// v1.0 — 2026-05-17 | Coverage form show + store for SR and WD vendors

namespace App\Http\Controllers;

use App\Http\Requests\StoreCoverageSubmissionRequest;
use App\Models\Assignment;
use Illuminate\Support\Facades\DB;

class CoverageSubmissionController extends Controller
{
    public function show(Assignment $assignment)
    {
        $this->authorize('submitCoverage', $assignment);

        $existing = $assignment->coverageSubmission;
        $view = $assignment->vendor === 'wd' ? 'coverage.wd' : 'coverage.sr';

        return view($view, compact('assignment', 'existing'));
    }

    public function store(StoreCoverageSubmissionRequest $request, Assignment $assignment)
    {
        $this->authorize('submitCoverage', $assignment);

        DB::transaction(function () use ($request, $assignment) {
            $data = $request->validated();
            $data['vendor'] = $assignment->vendor;

            $assignment->coverageSubmission()->updateOrCreate(
                ['assignment_id' => $assignment->id],
                $data
            );

            $assignment->update([
                'status'       => Assignment::STATUS_QC,
                'submitted_at' => now(),
            ]);
        });

        return redirect()->route('assignments.index')
            ->with('success', 'Coverage submitted for QC.');
    }
}
