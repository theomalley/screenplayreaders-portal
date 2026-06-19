<?php

// v1.2 — 2026-06-19 | redraftGoback() accepts optional ticket_number param — prompts inline when HS conversation is missing
// v1.1 — 2026-06-19 | sendToQc() — return completed order to QC; redraftGoback() — recreate HelpScout draft from archive

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\FollowupQuestion;
use App\Models\FollowupToken;
use App\Models\HelpScoutConversation;
use App\Models\Setting;
use App\Services\GoogleDocsService;
use App\Services\GoogleDriveService;
use App\Services\HelpScoutService;
use App\Support\FilenameGenerator;
use App\Support\Permission;
use Illuminate\Support\Facades\Log;

class ArchiveController extends Controller
{
    public function index()
    {
        abort_unless(Permission::check('archive'), 403);

        // Completed — grouped by order number so multi-reader orders appear as one row.
        $groups = Assignment::with(['assignedReader.readerProfile', 'coverageSubmission'])
            ->where('status', Assignment::STATUS_COMPLETED)
            ->get()
            ->groupBy('order_number')
            ->sortByDesc(fn($group) => $group->max(fn($a) => $a->completed_at?->timestamp ?? 0));

        $ordersWithSubmissions = FollowupQuestion::whereIn('order_number', $groups->keys())
            ->pluck('order_number')
            ->unique()
            ->flip()
            ->all();

        // Cancelled — grouped by order number, sorted by most recently created.
        $cancelled = Assignment::with(['assignedReader.readerProfile'])
            ->where('status', Assignment::STATUS_CANCELLED)
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('order_number');

        return view('archive.index', compact('groups', 'ordersWithSubmissions', 'cancelled'));
    }

    public function sendToQc(Assignment $assignment)
    {
        abort_unless(Permission::check('archive'), 403);
        abort_unless($assignment->status === Assignment::STATUS_COMPLETED, 422);

        $siblings = Assignment::where('order_number', $assignment->order_number)
            ->where('status', Assignment::STATUS_COMPLETED)
            ->get();

        foreach ($siblings as $sibling) {
            $sibling->update([
                'status'                      => Assignment::STATUS_QC,
                'completed_at'                => null,
                'helpscout_draft_sent_at'     => null,
                'helpscout_draft_dismissed_by' => null,
            ]);
        }

        return redirect()->route('archive.index')
            ->with('success', "#{$assignment->order_number} — {$assignment->script_title} sent back to QC.");
    }

    public function redraftGoback(Assignment $assignment)
    {
        abort_unless(Permission::check('archive'), 403);
        abort_unless($assignment->status === Assignment::STATUS_COMPLETED, 422);

        $isAjax      = request()->expectsJson();
        $ticketInput = trim((string) request()->input('ticket_number', ''));

        $siblings = Assignment::where('order_number', $assignment->order_number)
            ->with(['assignedReader.readerProfile'])
            ->where('status', Assignment::STATUS_COMPLETED)
            ->whereNotNull('drive_coverage_doc_id')
            ->get();

        if ($siblings->isEmpty()) {
            $msg = 'No coverage docs found for this order.';
            return $isAjax ? response()->json(['error' => $msg], 422) : back()->with('error', $msg);
        }

        foreach ($siblings as $sibling) {
            if (! $sibling->drive_coverage_pdf_id) {
                try {
                    $initials = $sibling->assignedReader?->readerProfile?->initials;
                    $pdfId    = (new GoogleDocsService())->exportToPdf(
                        $sibling->drive_coverage_doc_id,
                        FilenameGenerator::coverageDoc($sibling, $initials)
                    );
                    $sibling->update(['drive_coverage_pdf_id' => $pdfId]);
                    $sibling->refresh();
                } catch (\Throwable $e) {
                    Log::error('Archive redraft PDF generation failed', [
                        'assignment_id' => $sibling->id,
                        'error'         => $e->getMessage(),
                    ]);
                }
            }
        }

        try {
            $hsUrl = $this->buildHelpScoutDraft($siblings->all(), $ticketInput ?: null);

            if ($isAjax) {
                return response()->json(['url' => $hsUrl]);
            }

            $hsUrlJson     = json_encode($hsUrl, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
            $returnUrl     = route('archive.index');
            $returnUrlJson = json_encode($returnUrl, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);

            return response(
                "<!DOCTYPE html><html><head><meta charset=\"utf-8\"><title>Redirecting…</title></head><body>" .
                "<script>window.open({$hsUrlJson},'_blank');window.location.replace({$returnUrlJson});</script>" .
                "<noscript><p>Draft created. <a href=\"{$hsUrl}\" target=\"_blank\">Open in HelpScout</a> &mdash; " .
                "<a href=\"{$returnUrl}\">Return to Archive</a></p></noscript>" .
                "</body></html>",
                200,
                ['Content-Type' => 'text/html']
            );
        } catch (\RuntimeException $e) {
            $needsTicket = str_contains($e->getMessage(), 'No HelpScout conversation on record');

            if ($isAjax && $needsTicket) {
                return response()->json(['error' => $e->getMessage(), 'needs_ticket' => true], 422);
            }

            Log::error('Archive redraft HelpScout draft failed', [
                'order_number' => $assignment->order_number,
                'error'        => $e->getMessage(),
            ]);

            $msg = 'HelpScout draft could not be created: ' . $e->getMessage();
            return $isAjax ? response()->json(['error' => $msg], 500) : back()->with('error', $msg);
        } catch (\Throwable $e) {
            Log::error('Archive redraft HelpScout draft failed', [
                'order_number' => $assignment->order_number,
                'error'        => $e->getMessage(),
            ]);

            $msg = 'HelpScout draft could not be created: ' . $e->getMessage();
            return $isAjax ? response()->json(['error' => $msg], 500) : back()->with('error', $msg);
        }
    }

    private function buildHelpScoutDraft(array $assignments, ?string $manualTicket = null): string
    {
        $orderNumber = $assignments[0]->order_number;

        $record = HelpScoutConversation::where('order_number', $orderNumber)->first();

        if (! $record) {
            $ticketNumber = $manualTicket
                ?: collect($assignments)->pluck('helpscout_ticket_number')->filter()->first();

            if (! $ticketNumber) {
                throw new \RuntimeException("No HelpScout conversation on record for order #{$orderNumber}.");
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
            $bytes    = $drive->downloadContents($a->drive_coverage_pdf_id);

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

        Assignment::where('order_number', $orderNumber)
            ->update(['helpscout_draft_sent_at' => now()]);

        Log::info('HelpScout draft recreated from archive', [
            'order_number'    => $orderNumber,
            'conversation_id' => $conversationId,
            'attachments'     => count($attachments),
        ]);

        return 'https://secure.helpscout.net/conversation/' . $conversationId . '/';
    }
}
