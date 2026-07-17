<?php

// v1.2 — 2026-07-17 | WD assignments attach a DOCX export instead of the PDF.
// v1.1 — 2026-06-27 | Auto-heal stale conversation IDs: verify stored ID against API before
//                     drafting; on 404, search by order number and update the record.
// v1.0 — 2026-06-19 | Extracted from QcController + ArchiveController — shared HelpScout
//                     completion draft builder with optional manual ticket fallback.

namespace App\Services;

use App\Exceptions\MissingHelpScoutConversationException;
use App\Models\Assignment;
use App\Models\FollowupToken;
use App\Models\HelpScoutConversation;
use App\Models\Setting;
use App\Support\FilenameGenerator;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class CompletionDraftService
{
    /**
     * Build a HelpScout draft reply with coverage PDFs attached.
     *
     * @param  Assignment[]  $assignments
     * @param  string|null   $manualTicket  Ticket number entered by admin when the lookup record is missing
     * @return string  HelpScout web URL for the conversation
     *
     * @throws MissingHelpScoutConversationException  when no conversation can be resolved
     * @throws \RuntimeException  on API/attachment failures
     */
    public function buildDraft(array $assignments, ?string $manualTicket = null): string
    {
        $orderNumber = $assignments[0]->order_number;

        $record = HelpScoutConversation::where('order_number', $orderNumber)->first();

        if (! $record) {
            $ticketNumber = $manualTicket
                ?: collect($assignments)->pluck('helpscout_ticket_number')->filter()->first();

            if (! $ticketNumber) {
                throw new MissingHelpScoutConversationException(
                    "No HelpScout conversation on record for order #{$orderNumber}. Set the HelpScout ticket # on the assignment and try again."
                );
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

            // WD assignments attach the coverage as a Word doc instead of a PDF.
            if ($a->vendor === 'wd') {
                $filename = FilenameGenerator::coverageDocx($a, $initials);
                $bytes    = app(GoogleDocsService::class)->exportDocToDocxBytes($a->drive_coverage_doc_id);

                $attachments[] = [
                    'fileName' => $filename,
                    'mimeType' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'data'     => base64_encode($bytes),
                ];
                continue;
            }

            $filename = FilenameGenerator::coveragePdf($a, $initials);
            $bytes = $a->spaces_coverage_pdf_path
                ? app(SpacesStorageService::class)->get($a->spaces_coverage_pdf_path)
                : $drive->downloadContents($a->drive_coverage_pdf_id);

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

        // Verify the stored conversation ID still resolves — it may have been deleted
        // outright, or merged into another conversation, since Zapier stored it. HelpScout
        // returns 200 (not 404) for a merged-away ID on GET, so a plain existence check
        // isn't enough — resolveConversationId() surfaces the canonical ID from the body.
        $resolvedId = $helpScout->resolveConversationId($conversationId);

        if ($resolvedId === null) {
            Log::warning('HelpScout stored conversation ID no longer exists, searching by order number', [
                'order_number' => $orderNumber,
                'stale_id'     => $conversationId,
            ]);

            $resolved = $helpScout->findConversationIdByOrderNumber($orderNumber);
            if (! $resolved) {
                throw new \RuntimeException(
                    "Stored HelpScout conversation #{$conversationId} no longer exists and no replacement was found for order #{$orderNumber}."
                );
            }

            $record->update(['helpscout_conversation_id' => $resolved]);
            $conversationId = $resolved;

            Log::info('HelpScout conversation ID auto-healed (404)', [
                'order_number' => $orderNumber,
                'old_id'       => $record->getOriginal('helpscout_conversation_id'),
                'new_id'       => $conversationId,
            ]);
        } elseif ($resolvedId !== $conversationId) {
            Log::warning('HelpScout stored conversation ID was merged into another conversation, auto-healing', [
                'order_number' => $orderNumber,
                'old_id'       => $conversationId,
                'new_id'       => $resolvedId,
            ]);

            $record->update(['helpscout_conversation_id' => $resolvedId]);
            $conversationId = $resolvedId;
        }

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
        $scriptTitle  = $assignments[0]->script_title ?? '';

        $body        = Setting::getCompletionDraftBody();
        $body        = str_replace('{{script_title}}', $scriptTitle, $body);
        $body        = str_replace('{{followup_url}}', $followupUrl, $body);
        $body        = str_replace('{{woodiscountcode}}', $discountCode, $body);
        $body        = $helpScout->resolveBodyVariables($body, $conversationId);

        $helpScout->createDraftReply($conversationId, $body, $attachments);

        // Stamp only completed siblings so cancelled/on-hold assignments aren't affected
        Assignment::where('order_number', $orderNumber)
            ->where('status', Assignment::STATUS_COMPLETED)
            ->update(['helpscout_draft_sent_at' => now()]);

        Log::info('HelpScout draft created', [
            'order_number'    => $orderNumber,
            'conversation_id' => $conversationId,
            'attachments'     => count($attachments),
        ]);

        return 'https://secure.helpscout.net/conversation/' . $conversationId . '/';
    }

    /**
     * Return an HTML response that opens $hsUrl in a new tab and redirects
     * the current tab to $returnUrl.
     */
    public static function openInNewTab(string $hsUrl, string $returnUrl): Response
    {
        $hsUrlE     = htmlspecialchars($hsUrl, ENT_QUOTES, 'UTF-8');
        $returnUrlE = htmlspecialchars($returnUrl, ENT_QUOTES, 'UTF-8');
        $hsUrlJson     = json_encode($hsUrl, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
        $returnUrlJson = json_encode($returnUrl, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);

        return response(
            "<!DOCTYPE html><html><head><meta charset=\"utf-8\"><title>Redirecting…</title></head><body>" .
            "<script>window.open({$hsUrlJson},'_blank');window.location.replace({$returnUrlJson});</script>" .
            "<noscript><p>Draft created. <a href=\"{$hsUrlE}\" target=\"_blank\">Open in HelpScout</a> &mdash; " .
            "<a href=\"{$returnUrlE}\">Back</a></p></noscript>" .
            "</body></html>",
            200,
            ['Content-Type' => 'text/html']
        );
    }
}
