<?php

// v1.0 — 2026-05-26 | Invoice generation orchestrator — PDF (Google Docs) and Stripe paths

namespace App\Services;

use App\Models\Assignment;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class InvoiceService
{
    // Stub template ID — swap in your actual Google Docs invoice template Drive file ID.
    private const INVOICE_TEMPLATE_ID = 'REPLACE_WITH_GOOGLE_DOCS_INVOICE_TEMPLATE_ID';

    public function __construct(
        private readonly StripeService      $stripe,
        private readonly GoogleDocsService  $docs,
        private readonly HelpScoutService   $helpscout,
    ) {}

    /**
     * Generate an invoice for a client, optionally tied to an assignment.
     * Creates the Invoice record, fires the appropriate path (PDF or Stripe),
     * and creates a Help Scout thread if a ticket number is available.
     *
     * @param  Client          $client
     * @param  string          $description   Line item description
     * @param  float           $amount        Invoice amount in USD
     * @param  Assignment|null $assignment    The linked assignment (provides HS ticket number)
     * @param  string|null     $notes
     * @param  string|null     $dueDate       Y-m-d or null
     * @return Invoice
     */
    public function generate(
        Client      $client,
        string      $description,
        float       $amount,
        ?Assignment $assignment = null,
        ?string     $notes      = null,
        ?string     $dueDate    = null,
    ): Invoice {
        // Allocate the next invoice number atomically
        $client->increment('last_invoice_number');
        $client->refresh();
        $invoiceNumber = (string) $client->last_invoice_number;

        $invoice = Invoice::create([
            'client_id'      => $client->id,
            'assignment_id'  => $assignment?->id,
            'invoice_number' => $invoiceNumber,
            'description'    => $description,
            'amount'         => $amount,
            'status'         => 'draft',
            'invoice_type'   => $client->invoice_type,
            'notes'          => $notes,
            'due_date'       => $dueDate,
            'issued_at'      => now(),
        ]);

        try {
            if ($client->invoice_type === 'stripe') {
                $this->generateStripe($invoice, $client, $assignment);
            } else {
                $this->generatePdf($invoice, $client, $assignment);
            }
        } catch (\Throwable $e) {
            Log::error('InvoiceService generation failed', [
                'invoice_id' => $invoice->id,
                'error'      => $e->getMessage(),
            ]);
            // Leave invoice record as draft so it can be retried — don't rethrow silently
            throw $e;
        }

        return $invoice->fresh();
    }

    // -------------------------------------------------------------------------
    // Stripe path
    // -------------------------------------------------------------------------

    private function generateStripe(Invoice $invoice, Client $client, ?Assignment $assignment): void
    {
        // 1. Ensure a Stripe customer exists for this client
        $stripeCustomerId = $client->stripe_customer_id;

        if (! $stripeCustomerId) {
            $stripeCustomerId = $this->stripe->ensureCustomer(
                $client->email ?? '',
                $client->name
            );
            $client->update(['stripe_customer_id' => $stripeCustomerId]);
        }

        // 2. Create, finalize, and send via Stripe
        $dueDateTs = $invoice->due_date ? $invoice->due_date->timestamp : null;

        $result = $this->stripe->createAndSendInvoice(
            stripeCustomerId: $stripeCustomerId,
            description:      $invoice->description,
            amountCents:      (int) round($invoice->amount * 100),
            dueDateTimestamp: $dueDateTs,
        );

        $invoice->update([
            'status'             => 'sent',
            'stripe_invoice_id'  => $result['invoice_id'],
            'stripe_invoice_url' => $result['hosted_invoice_url'],
        ]);

        // 3. Reply on Help Scout with the invoice link (if we have a ticket)
        if ($assignment?->helpscout_ticket_number) {
            $this->postStripeHelpScoutReply($invoice, $assignment, $result['hosted_invoice_url']);
        }
    }

    private function postStripeHelpScoutReply(Invoice $invoice, Assignment $assignment, string $invoiceUrl): void
    {
        $conversationId = $this->helpscout->findConversationIdByTicketNumber(
            (string) $assignment->helpscout_ticket_number
        );

        if (! $conversationId) {
            Log::warning('InvoiceService: Help Scout conversation not found', [
                'ticket' => $assignment->helpscout_ticket_number,
            ]);
            return;
        }

        $invoice->update(['helpscout_conversation_id' => $conversationId]);

        $html = '<p>Hi,</p>'
            . '<p>Your invoice #' . htmlspecialchars($invoice->invoice_number) . ' has been created and sent via Stripe. '
            . 'You should receive a separate email from Stripe with the invoice attached.</p>'
            . '<p>You can also view and pay it here: <a href="' . htmlspecialchars($invoiceUrl) . '">' . htmlspecialchars($invoiceUrl) . '</a></p>'
            . '<p>Please let us know if you have any questions.</p>';

        $this->helpscout->createDraftReply($conversationId, $html);
    }

    // -------------------------------------------------------------------------
    // PDF path (Google Docs)
    // -------------------------------------------------------------------------

    private function generatePdf(Invoice $invoice, Client $client, ?Assignment $assignment): void
    {
        $srAddress    = Setting::getValue('sr_invoice_address', '');
        $emailBody    = Setting::getValue('invoice_email_body', '');
        $placeholders = $this->buildPdfPlaceholders($invoice, $client, $srAddress);

        // 1. Copy template and fill placeholders
        $folderId = config('services.google.drive_coverage_folder_id');
        $filename  = 'Invoice #' . $invoice->invoice_number . ' — ' . $client->name;

        // Use the Google Drive / Docs APIs via GoogleDocsService helpers
        $docId = $this->docs->createInvoiceDoc(self::INVOICE_TEMPLATE_ID, $filename, $folderId, $placeholders);

        $invoice->update(['google_doc_id' => $docId]);

        // 2. Export to PDF bytes
        $pdfBytes = $this->docs->exportDocToPdfBytes($docId);

        // 3. Post draft reply to Help Scout with PDF attached (if we have a ticket)
        if ($assignment?->helpscout_ticket_number) {
            $this->postPdfHelpScoutDraft($invoice, $assignment, $pdfBytes, $filename, $emailBody);
        }

        $invoice->update(['status' => 'sent']);
    }

    private function buildPdfPlaceholders(Invoice $invoice, Client $client, string $srAddress): array
    {
        return [
            '{{SR_ADDRESS}}'       => $srAddress,
            '{{CLIENT_NAME}}'      => $client->name,
            '{{CLIENT_ADDRESS}}'   => $client->billingAddress(),
            '{{CLIENT_EMAIL}}'     => $client->email ?? '',
            '{{INVOICE_NUMBER}}'   => $invoice->invoice_number,
            '{{INVOICE_DATE}}'     => now()->format('F j, Y'),
            '{{DUE_DATE}}'         => $invoice->due_date ? $invoice->due_date->format('F j, Y') : 'Upon Receipt',
            '{{DESCRIPTION}}'      => $invoice->description,
            '{{AMOUNT}}'           => '$' . number_format((float) $invoice->amount, 2),
        ];
    }

    private function postPdfHelpScoutDraft(
        Invoice    $invoice,
        Assignment $assignment,
        string     $pdfBytes,
        string     $filename,
        string     $emailBody
    ): void {
        $conversationId = $this->helpscout->findConversationIdByTicketNumber(
            (string) $assignment->helpscout_ticket_number
        );

        if (! $conversationId) {
            Log::warning('InvoiceService: Help Scout conversation not found', [
                'ticket' => $assignment->helpscout_ticket_number,
            ]);
            return;
        }

        $invoice->update(['helpscout_conversation_id' => $conversationId]);

        $html = $emailBody ?: '<p>Please find your invoice attached.</p>';

        $attachment = [
            'fileName' => $filename . '.pdf',
            'mimeType' => 'application/pdf',
            'data'     => base64_encode($pdfBytes),
        ];

        $this->helpscout->createDraftReply($conversationId, $html, [$attachment]);
    }
}
