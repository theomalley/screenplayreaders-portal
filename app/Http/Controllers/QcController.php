<?php

// v1.1 — 2026-05-23 | HelpScout draft reply on approval; draftNow escape hatch for early sends
// v1.0 — 2026-05-22 | QC tab — list, review, PDF regeneration, approval

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\HelpScoutConversation;
use App\Services\GoogleDocsService;
use App\Services\GoogleDriveService;
use App\Services\HelpScoutService;
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

        // Auto-draft when every reader for this order is now complete
        $siblings = Assignment::where('order_number', $assignment->order_number)->get();
        $allDone  = $siblings->every(fn ($a) => $a->status === Assignment::STATUS_COMPLETED && $a->drive_coverage_pdf_id);

        if ($allDone) {
            try {
                $this->buildHelpScoutDraft($siblings->all());
                return redirect()->route('qc.index')
                    ->with('success', "#{$assignment->order_number} — {$assignment->script_title} approved. HelpScout draft created.");
            } catch (\Throwable $e) {
                Log::error('HelpScout draft failed after QC approval', [
                    'order_number' => $assignment->order_number,
                    'error'        => $e->getMessage(),
                ]);
                return redirect()->route('qc.index')
                    ->with('success', "#{$assignment->order_number} — {$assignment->script_title} approved.")
                    ->with('warning', 'HelpScout draft could not be created — check the logs.');
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
     */
    public function draftNow(Assignment $assignment)
    {
        abort_unless(Permission::check('qc'), 403);
        abort_unless($assignment->status === Assignment::STATUS_COMPLETED, 422);
        abort_unless($assignment->drive_coverage_pdf_id, 422);

        try {
            $this->buildHelpScoutDraft([$assignment]);
            return back()->with('success', 'HelpScout draft created for this coverage.');
        } catch (\Throwable $e) {
            Log::error('HelpScout draftNow failed', [
                'assignment_id' => $assignment->id,
                'error'         => $e->getMessage(),
            ]);
            return back()->with('error', 'HelpScout draft could not be created — check the logs.');
        }
    }

    // -------------------------------------------------------------------------

    /**
     * Download coverage PDFs for the given assignments and post a draft reply
     * to the matching HelpScout conversation.
     *
     * @param  Assignment[]  $assignments
     * @throws \RuntimeException if no conversation ID is found or the API call fails
     */
    private function buildHelpScoutDraft(array $assignments): void
    {
        $orderNumber = $assignments[0]->order_number;

        $record = HelpScoutConversation::where('order_number', $orderNumber)->first();

        if (! $record) {
            throw new \RuntimeException("No HelpScout conversation ID on record for order #{$orderNumber}.");
        }

        $drive       = new GoogleDriveService();
        $attachments = [];

        foreach ($assignments as $a) {
            if (! $a->drive_coverage_pdf_id) {
                continue;
            }

            $a->loadMissing('assignedReader.readerProfile');
            $initials = $a->assignedReader?->readerProfile?->initials ?? 'coverage';
            $filename = $a->script_title . ' - ' . $initials . '.pdf';
            $filename = preg_replace('/[^\w\s\-.]/', '', $filename); // strip unsafe chars

            $bytes = $drive->downloadContents($a->drive_coverage_pdf_id);

            $attachments[] = [
                'fileName' => $filename,
                'mimeType' => 'application/pdf',
                'data'     => base64_encode($bytes),
            ];
        }

        if (empty($attachments)) {
            throw new \RuntimeException("No coverage PDFs available for order #{$orderNumber}.");
        }

        $html = 'Insert Saved Reply';

        (new HelpScoutService())->createDraftReply(
            $record->helpscout_conversation_id,
            $html,
            $attachments
        );

        Log::info('HelpScout draft created', [
            'order_number'    => $orderNumber,
            'conversation_id' => $record->helpscout_conversation_id,
            'attachments'     => count($attachments),
        ]);
    }
}
