<?php

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

use App\Models\Assignment;
use App\Models\FollowupToken;
use App\Models\HelpScoutConversation;
use App\Models\Setting;
use App\Services\GoogleDocsService;
use App\Services\GoogleDriveService;
use App\Services\HelpScoutService;
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
            $oldPdfId = $assignment->drive_coverage_pdf_id;
            $pdfId    = $this->generatePdfForAssignment($assignment);
            $assignment->update(['drive_coverage_pdf_id' => $pdfId]);

            if ($oldPdfId && $oldPdfId !== $pdfId) {
                try {
                    (new GoogleDriveService())->deleteFile($oldPdfId);
                } catch (\Throwable $e) {
                    Log::warning('Failed to delete previous PDF after regeneration', [
                        'assignment_id' => $assignment->id,
                        'old_pdf_id'    => $oldPdfId,
                        'error'         => $e->getMessage(),
                    ]);
                }
            }

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

            try {
                $hsUrl = $this->buildHelpScoutDraft($siblings->all());
                return $this->openInNewTab($hsUrl, route('qc.index'));
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
            $hsUrl = $this->buildHelpScoutDraft([$assignment]);
            return $this->openInNewTab($hsUrl, route('qc.index'));
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
            $hsUrl = $this->buildHelpScoutDraft($siblings->all());
            return $this->openInNewTab($hsUrl, route('qc.index'));
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

    /**
     * Return an HTML shim that opens $hsUrl in a new tab and redirects the
     * current tab to $returnUrl. Works for standard form-POST responses where
     * a server-side 302 can only navigate the current tab.
     */
    private function openInNewTab(string $hsUrl, string $returnUrl): \Illuminate\Http\Response
    {
        $hsUrlJson     = json_encode($hsUrl,     JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
        $returnUrlJson = json_encode($returnUrl, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);

        return response(
            "<!DOCTYPE html><html><head><meta charset=\"utf-8\"><title>Redirecting…</title></head><body>" .
            "<script>window.open({$hsUrlJson},'_blank');window.location.replace({$returnUrlJson});</script>" .
            "<noscript><p>Draft created. <a href=\"{$hsUrl}\" target=\"_blank\">Open in HelpScout</a> &mdash; " .
            "<a href=\"{$returnUrl}\">Return to QC queue</a></p></noscript>" .
            "</body></html>",
            200,
            ['Content-Type' => 'text/html']
        );
    }

    private function generatePdfForAssignment(Assignment $assignment): string
    {
        $initials = $assignment->assignedReader?->readerProfile?->initials;
        $docs     = new GoogleDocsService();
        return $docs->exportToPdf(
            $assignment->drive_coverage_doc_id,
            FilenameGenerator::coverageDoc($assignment, $initials)
        );
    }

    /**
     * Download coverage PDFs for the given assignments and post a draft reply
     * to the matching HelpScout conversation. Stamps helpscout_draft_sent_at on
     * all assignments after success.
     *
     * @param  Assignment[]  $assignments
     * @return string  The HelpScout web URL for the conversation
     * @throws \RuntimeException if no conversation ID is found or the API call fails
     */
    private function buildHelpScoutDraft(array $assignments): string
    {
        $orderNumber = $assignments[0]->order_number;

        $record = HelpScoutConversation::where('order_number', $orderNumber)->first();

        if (! $record) {
            $ticketNumber = collect($assignments)->pluck('helpscout_ticket_number')->filter()->first();

            if (! $ticketNumber) {
                throw new \RuntimeException("No HelpScout conversation on record for order #{$orderNumber}. Set the HelpScout ticket # on the assignment and try again.");
            }

            $helpScout      = new HelpScoutService();
            $conversationId = $helpScout->findConversationIdByTicketNumber($ticketNumber);

            if (! $conversationId) {
                throw new \RuntimeException("Could not find HelpScout conversation for ticket #{$ticketNumber}.");
            }

            $record = HelpScoutConversation::updateOrCreate(
                ['order_number'              => $orderNumber],
                ['helpscout_conversation_id' => $conversationId]
            );
        }

        $drive       = new GoogleDriveService();
        $attachments = [];

        foreach ($assignments as $a) {
            if (! $a->drive_coverage_pdf_id) {
                continue;
            }

            $a->loadMissing('assignedReader.readerProfile');
            $initials = $a->assignedReader?->readerProfile?->initials ?? null;
            $filename = FilenameGenerator::coveragePdf($a, $initials);

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

        $conversationId = $record->helpscout_conversation_id;
        $helpScout      = new HelpScoutService();

        try {
            $discountCode = $assignments[0]->woo_discount_code
                ?: Assignment::generateWooDiscountCode($orderNumber);
        } catch (\Throwable $e) {
            Log::error('WooCommerce discount coupon creation failed', [
                'order_number' => $orderNumber,
                'error'        => $e->getMessage(),
            ]);
            $discountCode = '(coupon unavailable — contact support)';
        }

        $followupUrl = FollowupToken::urlForOrder($orderNumber, collect($assignments)->pluck('id')->values()->all());
        $body        = Setting::getCompletionDraftBody();
        $body        = str_replace('{{followup_url}}', $followupUrl, $body);
        $body        = str_replace('{{woodiscountcode}}', $discountCode, $body);
        $body        = $helpScout->resolveBodyVariables($body, $conversationId);

        $helpScout->createDraftReply($conversationId, $body, $attachments);

        // Stamp all siblings for this order so the archive GoBack column shows ✓
        Assignment::where('order_number', $orderNumber)
            ->update(['helpscout_draft_sent_at' => now()]);

        Log::info('HelpScout draft created', [
            'order_number'    => $orderNumber,
            'conversation_id' => $conversationId,
            'attachments'     => count($attachments),
        ]);

        return 'https://secure.helpscout.net/conversation/' . $conversationId . '/';
    }
}
