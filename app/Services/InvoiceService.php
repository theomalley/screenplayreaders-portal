<?php

// v2.3 — 2026-07-08 | BUG FIX: HelpScout delivery only ever checked the manual
//                      helpscout_ticket_number field, which is never auto-populated —
//                      so invoices for normally-created assignments always skipped
//                      HelpScout and went straight to MailerSend. Added
//                      resolveConversationId() which checks the auto-populated
//                      helpscoutConversation relation first (mirrors
//                      CompletionDraftService::buildDraft()), falling back to the
//                      manual ticket field, before ever falling back to MailerSend.
// v2.2 — 2026-07-07 | Restore batch invoicing — clients flagged batch_invoicing accumulate
//                      line items on an open draft (always PDF, never Stripe) until sent manually
// v2.1 — 2026-06-02 | PDF invoices stored in portal_invoices Drive folder; MailerSend delivery for standalone PDF invoices
// v2.0 — 2026-06-02 | Remove batch invoicing; all invoices now take array of line items and send immediately
// v1.6 — 2026-05-26 | Add generateForCustomer(): direct Stripe + optional HelpScout draft for ad-hoc customer invoices
// v1.5 — 2026-05-26 | Wire up real Google Docs invoice template; rework buildPdfPlaceholders() to match actual template tokens
// v1.4 — 2026-05-26 | Let logToOrderRevenue exceptions propagate so errors surface to the user
// v1.3 — 2026-05-26 | Log invoices to order_revenues on payment, not on send; expose as public method
// v1.0 — 2026-05-26 | Invoice generation orchestrator — PDF (Google Docs) and Stripe paths

namespace App\Services;

use App\Models\Assignment;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\OrderRevenue;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class InvoiceService
{
    public const INVOICE_TEMPLATE_ID = '19RKODVuz9wC_1eGhhtTmb0eAwBZlz7Z4LTKJQYuCzZM';

    public function __construct(
        private readonly StripeService      $stripe,
        private readonly GoogleDocsService  $docs,
        private readonly HelpScoutService   $helpscout,
    ) {}

    /**
     * Generate an invoice for a client, optionally tied to an assignment.
     *
     * $lineItems — array of ['description' => string, 'amount' => float], up to 8 items.
     *
     * Batch clients (client->batch_invoicing): line items are appended to an open
     * draft invoice instead — nothing is sent until sendBatch() is called manually.
     * Standard clients: the invoice is sent straight away (Stripe or PDF).
     */
    public function generate(
        Client      $client,
        array       $lineItems,
        ?Assignment $assignment = null,
        ?string     $notes      = null,
        ?string     $dueDate    = null,
    ): Invoice {
        if (empty($lineItems)) {
            throw new RuntimeException('At least one line item is required.');
        }

        if ($client->batch_invoicing) {
            return $this->addToBatchInvoice($client, $lineItems, $assignment, $notes, $dueDate);
        }

        $client->increment('last_invoice_number');
        $client->refresh();

        $total       = array_sum(array_column($lineItems, 'amount'));
        $description = $lineItems[0]['description'];

        $invoice = Invoice::create([
            'client_id'      => $client->id,
            'assignment_id'  => $assignment?->id,
            'invoice_number' => (string) $client->last_invoice_number,
            'description'    => $description,
            'amount'         => $total,
            'status'         => 'draft',
            'invoice_type'   => $client->invoice_type,
            'notes'          => $notes,
            'due_date'       => $dueDate,
            'issued_at'      => now(),
        ]);

        foreach ($lineItems as $item) {
            InvoiceLineItem::create([
                'invoice_id'    => $invoice->id,
                'assignment_id' => $assignment?->id,
                'description'   => $item['description'],
                'amount'        => $item['amount'],
            ]);
        }

        try {
            if ($client->invoice_type === 'stripe') {
                $this->sendStripe($invoice, $client, $lineItems, $assignment);
            } else {
                $this->sendPdf($invoice, $client, $lineItems, $assignment);
            }
        } catch (\Throwable $e) {
            Log::error('InvoiceService generation failed', [
                'invoice_id' => $invoice->id,
                'error'      => $e->getMessage(),
            ]);
            throw $e;
        }

        return $invoice->fresh();
    }

    /**
     * Create and send a Stripe invoice for a one-off customer (no Client record).
     * Optionally posts a HelpScout draft to the given ticket number.
     */
    public function generateForCustomer(
        string  $email,
        string  $firstName,
        string  $lastName,
        array   $lineItems,
        ?string $helpscoutTicket = null,
        ?string $dueDate         = null,
    ): Invoice {
        if (empty($lineItems)) {
            throw new RuntimeException('At least one line item is required.');
        }

        $name             = trim("{$firstName} {$lastName}");
        $stripeCustomerId = $this->stripe->ensureCustomer($email, $name);
        $dueDateTs        = $dueDate ? \Carbon\Carbon::parse($dueDate)->startOfDay()->timestamp : null;
        $total            = array_sum(array_column($lineItems, 'amount'));
        $description      = $lineItems[0]['description'];

        $stripeLineItems = array_map(fn ($item) => [
            'description'  => $item['description'],
            'amount_cents' => (int) round($item['amount'] * 100),
        ], $lineItems);

        $result = $this->stripe->createAndSendInvoice(
            stripeCustomerId: $stripeCustomerId,
            lineItems:        $stripeLineItems,
            dueDateTimestamp: $dueDateTs,
        );

        $invoice = Invoice::create([
            'client_id'          => null,
            'customer_name'      => $name,
            'customer_email'     => $email,
            'invoice_number'     => $result['invoice_number'],
            'description'        => $description,
            'amount'             => $total,
            'status'             => 'sent',
            'invoice_type'       => 'stripe',
            'stripe_invoice_id'  => $result['invoice_id'],
            'stripe_invoice_url' => $result['hosted_invoice_url'],
            'issued_at'          => now(),
            'due_date'           => $dueDate,
        ]);

        foreach ($lineItems as $item) {
            InvoiceLineItem::create([
                'invoice_id'  => $invoice->id,
                'description' => $item['description'],
                'amount'      => $item['amount'],
            ]);
        }

        if ($helpscoutTicket) {
            try {
                $conversationId = $this->helpscout->findConversationIdByTicketNumber($helpscoutTicket);
                if ($conversationId) {
                    $invoice->update(['helpscout_conversation_id' => $conversationId]);
                    $html = '<p>Hi ' . htmlspecialchars($firstName) . ',</p>'
                        . '<p>[Insert saved reply here]</p>'
                        . '<p><em>Note: Stripe invoice ' . htmlspecialchars($result['invoice_number'])
                        . ' for $' . number_format($total, 2)
                        . ' was sent to ' . htmlspecialchars($email) . '.</em></p>';
                    $this->helpscout->createDraftReply($conversationId, $html);
                }
            } catch (\Throwable $e) {
                Log::warning('generateForCustomer: HelpScout draft failed', [
                    'invoice_id' => $invoice->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        return $invoice->fresh();
    }

    // -------------------------------------------------------------------------
    // Batch invoicing
    // -------------------------------------------------------------------------

    /**
     * Append line items to the client's open batch draft, creating one if none
     * exists. Batch invoices are always PDF — they never touch Stripe.
     */
    private function addToBatchInvoice(
        Client      $client,
        array       $lineItems,
        ?Assignment $assignment,
        ?string     $notes,
        ?string     $dueDate,
    ): Invoice {
        $invoice = Invoice::where('client_id', $client->id)
            ->where('status', 'draft')
            ->first();

        if (! $invoice) {
            $client->increment('last_invoice_number');
            $client->refresh();

            $invoice = Invoice::create([
                'client_id'      => $client->id,
                'invoice_number' => (string) $client->last_invoice_number,
                'description'    => 'Batch invoice',
                'amount'         => 0,
                'status'         => 'draft',
                'invoice_type'   => 'pdf',
                'notes'          => $notes,
                'due_date'       => $dueDate,
                'issued_at'      => now(),
            ]);
        }

        foreach ($lineItems as $item) {
            InvoiceLineItem::create([
                'invoice_id'    => $invoice->id,
                'assignment_id' => $assignment?->id,
                'description'   => $item['description'],
                'amount'        => $item['amount'],
            ]);
        }

        $invoice->update(['amount' => $invoice->lineItems()->sum('amount')]);

        return $invoice->fresh();
    }

    /**
     * Manually send an accumulated batch draft invoice. Always PDF — batch
     * clients never get a Stripe invoice.
     */
    public function sendBatch(Invoice $invoice): void
    {
        $client    = $invoice->client;
        $lineItems = $invoice->lineItems()->with('assignment')->get();

        if ($lineItems->isEmpty()) {
            throw new RuntimeException('Cannot send an invoice with no line items.');
        }

        $lineItemsData = $lineItems->map(fn ($item) => [
            'description' => $item->description,
            'amount'      => (float) $item->amount,
        ])->all();

        // Use the first line item's assignment for the Help Scout draft, if available.
        $primaryAssignment = $lineItems->first()?->assignment;

        $this->sendPdf($invoice, $client, $lineItemsData, $primaryAssignment);
    }

    // -------------------------------------------------------------------------
    // Stripe send path
    // -------------------------------------------------------------------------

    private function sendStripe(Invoice $invoice, Client $client, array $lineItems, ?Assignment $assignment): void
    {
        $stripeCustomerId = $client->stripe_customer_id;

        if (! $stripeCustomerId) {
            $stripeCustomerId = $this->stripe->ensureCustomer($client->email ?? '', $client->name);
            $client->update(['stripe_customer_id' => $stripeCustomerId]);
        }

        $stripeLineItems = array_map(fn ($item) => [
            'description'  => $item['description'],
            'amount_cents' => (int) round((float) $item['amount'] * 100),
        ], $lineItems);

        $result = $this->stripe->createAndSendInvoice(
            stripeCustomerId: $stripeCustomerId,
            lineItems:        $stripeLineItems,
            dueDateTimestamp: $invoice->due_date ? $invoice->due_date->timestamp : null,
        );

        $invoice->update([
            'status'             => 'sent',
            'stripe_invoice_id'  => $result['invoice_id'],
            'stripe_invoice_url' => $result['hosted_invoice_url'],
        ]);

        $conversationId = $this->resolveConversationId($assignment);
        if ($conversationId) {
            $this->postStripeHelpScoutReply($invoice, $conversationId, $result['hosted_invoice_url']);
        }
    }

    /**
     * Resolve a HelpScout conversation ID for an assignment. Checks the
     * auto-populated helpscoutConversation relation (stamped by Zapier via
     * order_number when the ticket was first created) before falling back to
     * the manual helpscout_ticket_number field an admin may have typed in.
     * Mirrors the resolution order in CompletionDraftService::buildDraft().
     */
    private function resolveConversationId(?Assignment $assignment): ?string
    {
        if (! $assignment) {
            return null;
        }

        $conversationId = $assignment->helpscoutConversation?->helpscout_conversation_id;

        if (! $conversationId && $assignment->helpscout_ticket_number) {
            $conversationId = $this->helpscout->findConversationIdByTicketNumber(
                (string) $assignment->helpscout_ticket_number
            );
        }

        return $conversationId;
    }

    private function postStripeHelpScoutReply(Invoice $invoice, string $conversationId, string $invoiceUrl): void
    {
        $invoice->update(['helpscout_conversation_id' => $conversationId]);

        $html = '<p>Hi,</p>'
            . '<p>Your invoice #' . htmlspecialchars($invoice->invoice_number) . ' has been created and sent via Stripe. '
            . 'You should receive a separate email from Stripe with the invoice attached.</p>'
            . '<p>You can also view and pay it here: <a href="' . htmlspecialchars($invoiceUrl) . '">' . htmlspecialchars($invoiceUrl) . '</a></p>'
            . '<p>Please let us know if you have any questions.</p>';

        $this->helpscout->createDraftReply($conversationId, $html);
    }

    // -------------------------------------------------------------------------
    // PDF send path
    // -------------------------------------------------------------------------

    private function sendPdf(Invoice $invoice, Client $client, array $lineItems, ?Assignment $assignment): void
    {
        $srAddress    = Setting::getValue('sr_invoice_address', '');
        $emailBody    = Setting::getValue('invoice_email_body', '');
        $placeholders = $this->buildPdfPlaceholders($invoice, $client, $srAddress, $lineItems);

        $folderId = config('services.google.drive_invoice_folder_id');
        $filename  = 'Invoice #' . $invoice->invoice_number . ' — ' . $client->name;

        $docId = $this->docs->createInvoiceDoc(self::INVOICE_TEMPLATE_ID, $filename, $folderId, $placeholders);
        $invoice->update(['google_doc_id' => $docId]);

        $pdfBytes = $this->docs->exportDocToPdfBytes($docId);

        // Deliver via HelpScout if a conversation can be resolved for the assignment
        $conversationId = $this->resolveConversationId($assignment);
        if ($conversationId) {
            $this->postPdfHelpScoutDraft($invoice, $conversationId, $pdfBytes, $filename, $emailBody);
        } elseif ($client->email) {
            // No HelpScout conversation on record — deliver via MailerSend
            $this->sendPdfByMailerSend($invoice, $client, $pdfBytes, $filename);
        }

        $invoice->update(['status' => 'sent']);
    }

    /**
     * Send a PDF invoice to a client via MailerSend using the invoice email template.
     * Called when a standalone PDF invoice has no associated HelpScout ticket.
     */
    public function sendPdfByMailerSend(Invoice $invoice, Client $client, string $pdfBytes, string $filename): void
    {
        $apiKey     = config('services.mailersend.api_key');
        $templateId = config('services.mailersend.invoice_template_id');

        if (! $apiKey || ! $client->email) {
            Log::warning('InvoiceService: skipping MailerSend delivery — no API key or client email', [
                'invoice_id' => $invoice->id,
            ]);
            return;
        }

        $response = \Illuminate\Support\Facades\Http::withToken($apiKey)
            ->post('https://api.mailersend.com/v1/email', [
                'from'            => [
                    'email' => config('mail.from.address', 'noreply@screenplayreaders.com'),
                    'name'  => config('mail.from.name', 'Screenplay Readers'),
                ],
                'to'              => [['email' => $client->email, 'name' => $client->name]],
                'template_id'     => $templateId,
                'personalization' => [[
                    'email' => $client->email,
                    'data'  => ['invoicenumber' => (string) $invoice->invoice_number],
                ]],
                'attachments'     => [[
                    'content'     => base64_encode($pdfBytes),
                    'filename'    => $filename . '.pdf',
                    'type'        => 'application/pdf',
                    'disposition' => 'attachment',
                ]],
            ]);

        if (! $response->successful()) {
            Log::error('InvoiceService: MailerSend delivery failed', [
                'invoice_id' => $invoice->id,
                'status'     => $response->status(),
                'body'       => $response->body(),
            ]);
            throw new RuntimeException('MailerSend delivery failed (' . $response->status() . '): ' . $response->body());
        }
    }

    /**
     * Regenerate the PDF for an existing invoice (e.g. after editing line items).
     * Replaces the existing Google Doc and returns fresh PDF bytes.
     */
    public function regeneratePdf(Invoice $invoice, Client $client, array $lineItems): string
    {
        $srAddress    = Setting::getValue('sr_invoice_address', '');
        $placeholders = $this->buildPdfPlaceholders($invoice, $client, $srAddress, $lineItems);

        $folderId = config('services.google.drive_invoice_folder_id');
        $filename  = 'Invoice #' . $invoice->invoice_number . ' — ' . $client->name;

        $docId = $this->docs->createInvoiceDoc(self::INVOICE_TEMPLATE_ID, $filename, $folderId, $placeholders);
        $invoice->update(['google_doc_id' => $docId]);

        return $this->docs->exportDocToPdfBytes($docId);
    }

    /**
     * Build the find-replace map keyed by the actual Google Docs template tokens.
     * $lineItemsData is an array of ['description' => string, 'amount' => float].
     * Up to 8 line-item slots are filled; unused slots are blanked.
     */
    private function buildPdfPlaceholders(Invoice $invoice, Client $client, string $srAddress, array $lineItemsData): array
    {
        $addrLine1 = trim(implode(', ', array_filter([$client->address_line1, $client->address_line2])));
        $addrLine2 = trim(implode(', ', array_filter([$client->city, $client->state, $client->postcode, $client->country])));

        $placeholders = [
            '{{SR_ADDRESS}}'    => $srAddress,
            '{{INVOICENUMBER}}' => $invoice->invoice_number,
            '{{DATE}}'          => now()->format('F j, Y'),
            '{{name}}'          => $client->name,
            '{{company}}'       => '',
            '{{addressline1}}'  => $addrLine1,
            '{{addressline2}}'  => $addrLine2,
            '{{notes}}'         => $invoice->notes ?? '',
            '{{TOTAL}}'         => number_format((float) $invoice->amount, 2),
            '{{URL}}'           => '',
        ];

        for ($i = 1; $i <= 8; $i++) {
            $item = $lineItemsData[$i - 1] ?? null;
            $placeholders["{{service{$i}}}"]    = $item ? $item['description'] : '';
            $placeholders["{{title{$i}}}"]      = '';
            $placeholders["{{price{$i}}}"]      = $item ? number_format($item['amount'], 2) : '';
            $placeholders["{{qty{$i}}}"]        = $item ? '1' : '';
            $placeholders["{{FINALPRICE{$i}}}"] = $item ? number_format($item['amount'], 2) : '';
        }

        return $placeholders;
    }

    // -------------------------------------------------------------------------
    // Order Log + Revenue integration
    // -------------------------------------------------------------------------

    public function logToOrderRevenue(Invoice $invoice): void
    {
        $client = $invoice->client;
        $code   = strtoupper($client->code ?? 'INV');

        $lineItems = $invoice->lineItems()->get();
        if ($lineItems->count() > 1) {
            $description = $lineItems->values()->map(
                fn ($item, $i) => ($i + 1) . '. ' . $item->description . ' — $' . number_format((float) $item->amount, 2)
            )->implode("\n");
        } elseif ($lineItems->count() === 1) {
            $description = $lineItems->first()->description;
        } else {
            $description = $invoice->description;
        }

        OrderRevenue::updateOrCreate(
            ['order_number' => "INV-{$code}-{$invoice->invoice_number}"],
            [
                'invoice_number'     => $code . str_pad($invoice->invoice_number, 4, '0', STR_PAD_LEFT),
                'ordered_at'         => $invoice->paid_at ?? $invoice->issued_at ?? now(),
                'order_total'        => $invoice->amount,
                'discount_amount'    => 0,
                'cog_reader'         => 0,
                'cog_processing'     => 0,
                'cog_precommission'  => 0,
                'cog_commission'     => 0,
                'cog_total'          => 0,
                'net_revenue'        => $invoice->amount,
                'customer_name'      => $client->name,
                'customer_email'     => $client->email ?? '',
                'services_purchased' => $description,
                'payment_method'     => 'invoice',
                'skip_commission'    => true,
            ]
        );
    }

    private function postPdfHelpScoutDraft(
        Invoice $invoice,
        string  $conversationId,
        string  $pdfBytes,
        string  $filename,
        string  $emailBody
    ): void {
        $invoice->update(['helpscout_conversation_id' => $conversationId]);

        $html = $emailBody ?: '<p>Please find your invoice attached.</p>';

        $this->helpscout->createDraftReply($conversationId, $html, [[
            'fileName' => $filename . '.pdf',
            'mimeType' => 'application/pdf',
            'data'     => base64_encode($pdfBytes),
        ]]);
    }
}
