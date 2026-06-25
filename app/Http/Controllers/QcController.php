<?php

// v1.11 — 2026-06-19 | Extract buildHelpScoutDraft + openInNewTab to CompletionDraftService
// v1.10 — 2026-06-12 | regeneratePdf(): delete the previous Drive PDF after a replacement is
//                      generated successfully, so repeated regeneration doesn't leave orphans.
// v1.9 — 2026-06-12 | {{woodiscountcode}} replaced by a per-order WooCommerce coupon
//                     (generated via Assignment::generateWooDiscountCode if not already set).
// v1.8 — 2026-06-12 | Completion draft body now sourced from Setting::getCompletionDraftBody(),
//                     with {{followup_url}} replaced by a per-order FollowupToken URL.
// v1.7 — 2026-06-05 | sendBack() emails reader if email_notify_qc_fail is enabled.
// v1.6 — 2026-05-29 | Pass qcSavedReplies to show() for Send Back modal quick-insert checkboxes.
// v1.5 — 2026-05-25 | Add sendBack() — returns assignment to reader as needs_attention with optional notes
// v1.4 — 2026-05-24 | Standardize draftAll() auth to Permission::check('qc').
// v1.3 — 2026-05-24 | draftAll: create draft with all available coverage PDFs for an order
//                     (used from assignment show page for early sends on multi-reader orders).
// v1.2 — 2026-05-24 | Auto-draft fires when all sibling docs exist (generates missing PDFs inline);
//                     draftNow no longer requires PDF pre-existing; helpscout_draft_sent_at stamped
//                     on all siblings after successful draft; error messages surfaced to flash.
// v1.1 — 2026-05-23 | HelpScout draft reply on approval; draftNow escape hatch for early sends
// v1.0 — 2026-05-22 | QC tab — list, review, PDF regeneration, approval

namespace App\Http\Controllers;

use App\Jobs\CopyFileToSpaces;
use App\Models\Assignment;
use App\Models\Setting;
use App\Services\CompletionDraftService;
use App\Services\GoogleDocsService;
use App\Support\FilenameGenerator;
use App\Support\Permission;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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

        $qcSavedReplies = Setting::getSavedReplies();

        return view('qc.show', compact('assignment', 'qcSavedReplies'));
    }

    public function regeneratePdf(Assignment $assignment)
    {
        abort_unless(Permission::check('qc'), 403);

        if (!$assignment->drive_coverage_doc_id) {
            return back()->with('error', 'No Google Doc found for this assignment.');
        }

        try {
            $assignment->loadMissing('assignedReader.readerProfile');
            $pdfId = $this->generatePdfForAssignment($assignment, $assignment->drive_coverage_pdf_id);
            $assignment->update([
                'drive_coverage_pdf_id' => $pdfId,
                'spaces_coverage_pdf_path' => null,
            ]);

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

        // Auto-draft when every reader for this order is now complete and has a coverage doc.
        // PDFs are generated inline for any sibling that doesn't have one yet.
        $siblings = Assignment::where('order_number', $assignment->order_number)->get();
        $allDone  = $siblings->every(
            fn ($a) => $a->status === Assignment::STATUS_COMPLETED && $a->drive_coverage_doc_id
        );

        if ($allDone) {
            // Skip HelpScout draft entirely for test assignments
            if ($assignment->is_test) {
                return redirect()->route('qc.index')
                    ->with('success', "#{$assignment->order_number} — {$assignment->script_title} approved. (Test — no HelpScout draft created.)");
            }

            // Generate any missing PDFs before attempting the draft
            foreach ($siblings as $sibling) {
                if ($sibling->drive_coverage_doc_id && ! $sibling->drive_coverage_pdf_id) {
                    try {
                        $sibling->loadMissing('assignedReader.readerProfile');
                        $pdfId = $this->generatePdfForAssignment($sibling);
                        $sibling->update(['drive_coverage_pdf_id' => $pdfId]);
                    } catch (\Throwable $e) {
                        Log::error('Auto-PDF generation failed before draft', [
                            'assignment_id' => $sibling->id,
                            'error'         => $e->getMessage(),
                        ]);
                    }
                }
            }

            // Reload so PDF IDs are fresh
            $siblings = Assignment::where('order_number', $assignment->order_number)->get();

            // Queue finalized files for copy to DO Spaces
            foreach ($siblings as $sibling) {
                if ($sibling->drive_coverage_pdf_id && ! $sibling->spaces_coverage_pdf_path) {
                    CopyFileToSpaces::dispatch(
                        Assignment::class, $sibling->id,
                        'drive_coverage_pdf_id', 'spaces_coverage_pdf_path',
                        "coverage/{$sibling->order_number}/{$sibling->id}-coverage.pdf",
                    );
                }
                if ($sibling->drive_script_file_id && ! $sibling->spaces_script_path) {
                    CopyFileToSpaces::dispatch(
                        Assignment::class, $sibling->id,
                        'drive_script_file_id', 'spaces_script_path',
                        "scripts/{$sibling->order_number}/{$sibling->id}-script.pdf",
                    );
                }
            }

            try {
                $hsUrl = app(CompletionDraftService::class)->buildDraft($siblings->all());
                return CompletionDraftService::openInNewTab($hsUrl, route('qc.index'));
            } catch (\Throwable $e) {
                Log::error('HelpScout draft failed after QC approval', [
                    'order_number' => $assignment->order_number,
                    'error'        => $e->getMessage(),
                ]);
                return redirect()->route('qc.index')
                    ->with('success', "#{$assignment->order_number} — {$assignment->script_title} approved.")
                    ->with('warning', 'HelpScout draft could not be created: ' . $e->getMessage());
            }
        }

        $pending = $siblings->filter(fn ($a) => $a->status !== Assignment::STATUS_COMPLETED)->count();

        return redirect()->route('qc.index')
            ->with('success', "#{$assignment->order_number} — {$assignment->script_title} approved. Waiting on {$pending} more reader(s) before drafting.");
    }

    /**
     * Escape hatch: immediately create a HelpScout draft for this one assignment,
     * regardless of whether sibling assignments are complete.
     * Used for the ~5% of cases where coverage needs to be sent to the customer early.
     * Generates the PDF inline if it doesn't exist yet.
     */
    public function draftNow(Assignment $assignment)
    {
        abort_unless(Permission::check('qc'), 403);
        abort_unless($assignment->status === Assignment::STATUS_COMPLETED, 422);
        abort_unless($assignment->drive_coverage_doc_id, 422);

        // Generate PDF if not already done
        if (! $assignment->drive_coverage_pdf_id) {
            try {
                $assignment->loadMissing('assignedReader.readerProfile');
                $pdfId = $this->generatePdfForAssignment($assignment);
                $assignment->update(['drive_coverage_pdf_id' => $pdfId]);
                $assignment->refresh();
            } catch (\Throwable $e) {
                Log::error('draftNow PDF generation failed', [
                    'assignment_id' => $assignment->id,
                    'error'         => $e->getMessage(),
                ]);
                return back()->with('error', 'PDF generation failed — check the logs.');
            }
        }

        try {
            $hsUrl = app(CompletionDraftService::class)->buildDraft([$assignment]);
            return CompletionDraftService::openInNewTab($hsUrl, route('qc.index'));
        } catch (\Throwable $e) {
            Log::error('HelpScout draftNow failed', [
                'assignment_id' => $assignment->id,
                'error'         => $e->getMessage(),
            ]);
            return back()->with('error', 'HelpScout draft could not be created: ' . $e->getMessage());
        }
    }

    /**
     * Create a HelpScout draft with all available coverage PDFs for the order.
     * Generates any missing PDFs inline. Used from the assignment show page so admins
     * can send partial coverage (e.g. one of two readers) without waiting for all readers.
     */
    public function draftAll(Assignment $assignment)
    {
        abort_unless(Permission::check('qc'), 403);

        $siblings = Assignment::where('order_number', $assignment->order_number)
            ->with(['assignedReader.readerProfile'])
            ->whereNotNull('drive_coverage_doc_id')
            ->get();

        if ($siblings->isEmpty()) {
            return back()->with('error', 'No coverage docs found for this order.');
        }

        foreach ($siblings as $sibling) {
            if (! $sibling->drive_coverage_pdf_id) {
                try {
                    $pdfId = $this->generatePdfForAssignment($sibling);
                    $sibling->update(['drive_coverage_pdf_id' => $pdfId]);
                    $sibling->refresh();
                } catch (\Throwable $e) {
                    Log::error('draftAll PDF generation failed', [
                        'assignment_id' => $sibling->id,
                        'error'         => $e->getMessage(),
                    ]);
                }
            }
        }

        try {
            $hsUrl = app(CompletionDraftService::class)->buildDraft($siblings->all());
            return CompletionDraftService::openInNewTab($hsUrl, route('qc.index'));
        } catch (\Throwable $e) {
            Log::error('HelpScout draftAll failed', [
                'order_number' => $assignment->order_number,
                'error'        => $e->getMessage(),
            ]);
            return back()->with('error', 'HelpScout draft could not be created: ' . $e->getMessage());
        }
    }

    public function sendBack(Assignment $assignment)
    {
        abort_unless(Permission::check('qc'), 403);
        abort_unless($assignment->status === Assignment::STATUS_QC, 422);

        $notes = trim(request()->input('notes', ''));

        $assignment->update([
            'status'                => Assignment::STATUS_NEEDS_ATTENTION,
            'needs_attention_notes' => $notes ?: null,
        ]);

        $reader = $assignment->assignedReader;
        if ($reader?->readerProfile?->email_notify_qc_fail) {
            Mail::to($reader->email)->send(new \App\Mail\QcFailedMail($assignment->fresh(), $reader));
        }
        // SMS: pending Twilio integration — flag: sms_notify_qc_fail

        return redirect()->route('qc.index')
            ->with('success', "#{$assignment->order_number} — {$assignment->script_title} sent back to reader.");
    }

    // -------------------------------------------------------------------------

    private function generatePdfForAssignment(Assignment $assignment, ?string $existingPdfId = null): string
    {
        $initials = $assignment->assignedReader?->readerProfile?->initials;
        $docs     = new GoogleDocsService();
        return $docs->exportToPdf(
            $assignment->drive_coverage_doc_id,
            FilenameGenerator::coverageDoc($assignment, $initials),
            $existingPdfId
        );
    }

}
