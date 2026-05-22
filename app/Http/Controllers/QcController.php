<?php

// v1.0 — 2026-05-22 | QC tab — list, review, PDF regeneration, approval

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Services\GoogleDocsService;
use App\Support\FilenameGenerator;
use App\Support\Permission;
use Illuminate\Support\Facades\Log;

class QcController extends Controller
{
    public function index()
    {
        abort_unless(Permission::check('qc'), 403);

        $assignments = Assignment::with(['assignedReader.readerProfile', 'coverageSubmission'])
            ->where('status', Assignment::STATUS_QC)
            ->orderByDesc('submitted_at')
            ->paginate(50);

        return view('qc.index', compact('assignments'));
    }

    public function show(Assignment $assignment)
    {
        abort_unless(Permission::check('qc'), 403);
        abort_unless($assignment->status === Assignment::STATUS_QC, 404);

        $assignment->load(['assignedReader.readerProfile', 'coverageSubmission']);

        return view('qc.show', compact('assignment'));
    }

    public function regeneratePdf(Assignment $assignment)
    {
        abort_unless(Permission::check('qc'), 403);

        if (!$assignment->drive_coverage_doc_id) {
            return back()->with('error', 'No Google Doc found for this assignment.');
        }

        try {
            $assignment->loadMissing('assignedReader.readerProfile');
            $initials = $assignment->assignedReader?->readerProfile?->initials;
            $docs     = new GoogleDocsService();
            $pdfId    = $docs->exportToPdf(
                $assignment->drive_coverage_doc_id,
                FilenameGenerator::coveragePdf($assignment, $initials)
            );

            $assignment->update(['drive_coverage_pdf_id' => $pdfId]);

            return back()->with('success', 'PDF regenerated.');
        } catch (\Throwable $e) {
            Log::error('QC PDF regeneration failed', [
                'assignment_id' => $assignment->id,
                'error'         => $e->getMessage(),
            ]);
            return back()->with('error', 'PDF regeneration failed — check the logs.');
        }
    }

    public function approve(Assignment $assignment)
    {
        abort_unless(Permission::check('qc'), 403);
        abort_unless($assignment->status === Assignment::STATUS_QC, 422);

        $assignment->update([
            'status'       => Assignment::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        return redirect()->route('qc.index')
            ->with('success', "#{$assignment->order_number} — {$assignment->script_title} approved and marked complete.");
    }
}
