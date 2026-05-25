<?php

// v1.3 — 2026-05-25 | Add coverage preview endpoint (text-only HTML view for admins/editors and reader's own)
// v1.2 — 2026-05-24 | Submit button spinner; redirect to dedicated submitted page with custom HTML
// v1.1 — 2026-05-22 | Fire GoogleDocsService after submission to create coverage doc + draft PDF
// v1.0 — 2026-05-17 | Coverage form show + store for SR and WD vendors

namespace App\Http\Controllers;

use App\Http\Requests\StoreCoverageSubmissionRequest;
use App\Models\Assignment;
use App\Services\GoogleDocsService;
use App\Support\FilenameGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

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

        $submission = null;

        DB::transaction(function () use ($request, $assignment, &$submission) {
            $data = $request->validated();
            $data['vendor'] = $assignment->vendor;

            $submission = $assignment->coverageSubmission()->updateOrCreate(
                ['assignment_id' => $assignment->id],
                $data
            );

            $assignment->update([
                'status'       => Assignment::STATUS_QC,
                'submitted_at' => now(),
            ]);
        });

        // Create the coverage Google Doc and draft PDF outside the transaction
        // so a Drive API failure doesn't roll back the submitted coverage.
        try {
            $docs     = new GoogleDocsService();
            $docId    = $docs->createFromSubmission($assignment, $submission);
            $assignment->loadMissing('assignedReader.readerProfile');
            $initials = $assignment->assignedReader?->readerProfile?->initials;
            $pdfId    = $docs->exportToPdf($docId, FilenameGenerator::coverageDoc($assignment, $initials));

            $assignment->update([
                'drive_coverage_doc_id' => $docId,
                'drive_coverage_pdf_id' => $pdfId,
            ]);
        } catch (\Throwable $e) {
            Log::error('Coverage doc creation failed', [
                'assignment_id' => $assignment->id,
                'error'         => $e->getMessage(),
            ]);
        }

        return redirect()->route('coverage.submitted')
            ->with('submitted_title', $assignment->script_title);
    }

    public function submitted(): View
    {
        return view('coverage.submitted');
    }

    public function coveragePreview(Assignment $assignment): View
    {
        $user = auth()->user();
        abort_unless(
            $user->canManageAssignments() || $assignment->assigned_reader_id === $user->id,
            403
        );
        $submission = $assignment->coverageSubmission;
        abort_if(!$submission, 404);

        return view('coverage.preview', compact('assignment', 'submission'));
    }
}
