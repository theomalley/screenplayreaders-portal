<?php

// v1.6 — 2026-05-26 | Add generateForCustomer(): direct Stripe + optional HelpScout draft for ad-hoc customer invoices
// v1.5 — 2026-05-26 | Wire up real Google Docs invoice template; rework buildPdfPlaceholders() to match actual template tokens
// v1.4 — 2026-05-26 | Let logToOrderRevenue exceptions propagate so errors surface to the user
// v1.3 — 2026-05-26 | Log invoices to order_revenues on payment, not on send; expose as public method
// v1.2 — 2026-05-26 | Log sent invoices to order_revenues for Order Log + Revenue visibility
// v1.1 — 2026-05-26 | Add batch invoicing path — addToBatchInvoice(), send()
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
     * For batch clients: finds (or creates) an open draft invoice for the client
     * and appends a line item. Does NOT send — the editor sends manually later.
     *
     * For standard clients: creates the Invoice record, fires the appropriate
     * delivery path (Stripe or PDF), and posts to Help Scout if a ticket exists.
     */
    public function generate(
        Client      $client,
        string      $description,
        float       $amount,
        ?Assignment $assignment = null,
        ?string     $notes      = null,
        ?string     $dueDate    = null,
    ): Invoice {
        if ($client->batch_invoicing) {
            return $this->addToBatchInvoice($client, $description, $amount, $assignment);
        }

        // Standard (non-batch) path — allocate invoice number atomically
        $client->increment('last_invoice_number');
        $client->refresh();

        $invoice = Invoice::create([
            'client_id'      => $client->id,
            'assignment_id'  => $assignment?->id,
            'invoice_number' => (string) $client->last_invoice_number,
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
            throw $e;
        }

        return $invoice->fresh();
    }

    /**
     * Create and send a Stripe invoice for a one-off customer (no Client record).
     * Optionally posts a HelpScout draft to the given ticket number.
     * Returns the created Invoice.
     */
    public function generateForCustomer(
        string  $email,
        string  $firstName,
        string  $lastName,
        string  $description,
        float   $amount,
        ?string $helpscoutTicket = null,
        ?string $dueDate         = null,
    ): Invoice {
        $name             = trim("{$firstName} {$lastName}");
        $stripeCustomerId = $this->stripe->ensureCustomer($email, $name);

        $dueDateTs = $dueDate ? \Carbon\Carbon::parse($dueDate)->startOfDay()->timestamp : null;

        $result = $this->stripe->createAndSendInvoice(
            stripeCustomerId: $stripeCustomerId,
            lineItems: [['description' => $description, 'amount_cents' => (int) round($amount * 100)]],
            dueDateTimestamp: $dueDateTs,
        );

        $invoice = Invoice::create([
            'client_id'          => null,
            'customer_name'      => $name,
            'customer_email'     => $email,
            'invoice_number'     => $result['invoice_number'],
            'description'        => $description,
            'amount'             => $amount,
            'status'             => 'sent',
            'invoice_type'       => 'stripe',
            'stripe_invoice_id'  => $result['invoice_id'],
            'stripe_invoice_url' => $result['hosted_invoice_url'],
            'issued_at'          => now(),
            'due_date'           => $dueDate,
        ]);

        if ($helpscoutTicket) {
            try {
                $conversationId = $this->helpscout->findConversationIdByTicketNumber($helpscoutTicket);
                if ($conversationId) {
                    $invoice->update(['helpscout_conversation_id' => $conversationId]);
                    $html = '<p>Hi ' . htmlspecialchars($firstName) . ',</p>'
                        . '<p>[Insert saved reply here]</p>'
                        . '<p><em>Note: Stripe invoice ' . htmlspecialchars($result['invoice_number'])
                        . ' for $' . number_format($amount, 2)
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

    /**
     * Manually send a batch draft invoice.
     * Compiles all accumulated line items and fires the appropriate delivery path.
     */
    public function send(Invoice $invoice): void
    {
        $client    = $invoice->client;
        $lineItems = $invoice->lineItems()->with('assignment')->get();

        if ($lineItems->isEmpty()) {
            throw new RuntimeException('Cannot send an invoice with no line items.');
        }

        // Build Stripe line items payload
        $stripeLineItems = $lineItems->map(fn ($item) => [
            'description'  => $item->description,
            'amount_cents' => (int) round((float) $item->amount * 100),
        ])->all();

        // Use the first line item's assignment for Help Scout, if available
        $primaryAssignment = $lineItems->first()?->assignment;

        $invoice->update(['issued_at' => $invoice->issued_at ?? now()]);

        try {
            if ($invoice->invoice_type === 'stripe') {
                $this->sendBatchStripe($invoice, $client, $stripeLineItems, $primaryAssignment);
            } else {
                $this->sendBatchPdf($invoice, $client, $primaryAssignment);
            }
        } catch (\Throwable $e) {
            Log::error('InvoiceService::send failed', [
                'invoice_id' => $invoice->id,
                'error'      => $e->getMessage(),
            ]);
            throw $e;
        }

    }

    // -------------------------------------------------------------------------
    // Batch helpers
    // -------------------------------------------------------------------------

    private function addToBatchInvoice(Client $client, string $description, float $amount, ?Assignment $assignment): Invoice
    {
        // Find the open accumulating draft for this client, or create one
        $invoice = Invoice::where('client_id', $client->id)
            ->where('status', 'draft')
            ->whereNull('stripe_invoice_id')
            ->whereNull('google_doc_id')
            ->latest()
            ->first();

        if (! $invoice) {
            $client->increment('last_invoice_number');
            $client->refresh();

            $invoice = Invoice::create([
                'client_id'      => $client->id,
                'invoice_number' => (string) $client->last_invoice_number,
                'description'    => 'Weekly batch invoice',
                'amount'         => 0,
                'status'         => 'draft',
                'invoice_type'   => $client->invoice_type,
                'issued_at'      => now(),
            ]);
        }

        InvoiceLineItem::create([
            'invoice_id'    => $invoice->id,
            'assignment_id' => $assignment?->id,
            'description'   => $description,
            'amount'        => $amount,
        ]);

        // Keep invoice amount in sync with the sum of all line items
        $invoice->update(['amount' => $invoice->lineItems()->sum('amount')]);

        return $invoice->fresh();
    }

    // -------------------------------------------------------------------------
    // Standard (single-item) Stripe path
    // -------------------------------------------------------------------------

    private function generateStripe(Invoice $invoice, Client $client, ?Assignment $assignment): void
    {
        $stripeCustomerId = $client->stripe_customer_id;

        if (! $stripeCustomerId) {
            $stripeCustomerId = $this->stripe->ensureCustomer($client->email ?? '', $client->name);
            $client->update(['stripe_customer_id' => $stripeCustomerId]);
        }

        $singleLineItem = [
            'description'  => $invoice->description,
            'amount_cents' => (int) round((float) $invoice->amount * 100),
        ];

        $result = $this->stripe->createAndSendInvoice(
            stripeCustomerId: $stripeCustomerId,
            lineItems: [$singleLineItem],
            dueDateTimestamp: $invoice->due_date ? $invoice->due_date->timestamp : null,
        );

        $invoice->update([
            'status'             => 'sent',
            'stripe_invoice_id'  => $result['invoice_id'],
            'stripe_invoice_url' => $result['hosted_invoice_url'],
        ]);

        if ($assignment?->helpscout_ticket_number) {
            $this->postStripeHelpScoutReply($invoice, $assignment, $result['hosted_invoice_url']);
        }
    }

    // -------------------------------------------------------------------------
    // Batch Stripe send path
    // -------------------------------------------------------------------------

    private function sendBatchStripe(Invoice $invoice, Client $client, array $lineItems, ?Assignment $primaryAssignment): void
    {
        $stripeCustomerId = $client->stripe_customer_id;

        if (! $stripeCustomerId) {
            $stripeCustomerId = $this->stripe->ensureCustomer($client->email ?? '', $client->name);
            $client->update(['stripe_customer_id' => $stripeCustomerId]);
        }

        $result = $this->stripe->createAndSendInvoice(
            stripeCustomerId: $stripeCustomerId,
            lineItems: $lineItems,
            dueDateTimestamp: $invoice->due_date ? $invoice->due_date->timestamp : null,
        );

        $invoice->update([
            'status'             => 'sent',
            'stripe_invoice_id'  => $result['invoice_id'],
            'stripe_invoice_url' => $result['hosted_invoice_url'],
        ]);

        if ($primaryAssignment?->helpscout_ticket_number) {
            $this->postStripeHelpScoutReply($invoice, $primaryAssignment, $result['hosted_invoice_url']);
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
    // Standard (single-item) PDF path
    // -------------------------------------------------------------------------

    private function generatePdf(Invoice $invoice, Client $client, ?Assignment $assignment): void
    {
        $srAddress    = Setting::getValue('sr_invoice_address', '');
        $emailBody    = Setting::getValue('invoice_email_body', '');
        $lineItemsData = [['description' => $invoice->description, 'amount' => (float) $invoice->amount]];
        $placeholders  = $this->buildPdfPlaceholders($invoice, $client, $srAddress, $lineItemsData);

        $folderId = config('services.google.drive_coverage_folder_id');
        $filename  = 'Invoice #' . $invoice->invoice_number . ' — ' . $client->name;

        $docId = $this->docs->createInvoiceDoc(self::INVOICE_TEMPLATE_ID, $filename, $folderId, $placeholders);
        $invoice->update(['google_doc_id' => $docId]);

        $pdfBytes = $this->docs->exportDocToPdfBytes($docId);

        if ($assignment?->helpscout_ticket_number) {
            $this->postPdfHelpScoutDraft($invoice, $assignment, $pdfBytes, $filename, $emailBody);
        }

        $invoice->update(['status' => 'sent']);
    }

    // -------------------------------------------------------------------------
    // Batch PDF send path
    // -------------------------------------------------------------------------

    private function sendBatchPdf(Invoice $invoice, Client $client, ?Assignment $primaryAssignment): void
    {
        $srAddress    = Setting::getValue('sr_invoice_address', '');
        $emailBody    = Setting::getValue('invoice_email_body', '');
        $lineItemsData = $invoice->lineItems()->get()->map(fn ($item) => [
            'description' => $item->description,
            'amount'      => (float) $item->amount,
        ])->values()->all();
        $placeholders  = $this->buildPdfPlaceholders($invoice, $client, $srAddress, $lineItemsData);

        $folderId = config('services.google.drive_coverage_folder_id');
        $filename  = 'Invoice #' . $invoice->invoice_number . ' — ' . $client->name;

        $docId = $this->docs->createInvoiceDoc(self::INVOICE_TEMPLATE_ID, $filename, $folderId, $placeholders);
        $invoice->update(['google_doc_id' => $docId]);

        $pdfBytes = $this->docs->exportDocToPdfBytes($docId);

        if ($primaryAssignment?->helpscout_ticket_number) {
            $this->postPdfHelpScoutDraft($invoice, $primaryAssignment, $pdfBytes, $filename, $emailBody);
        }

        $invoice->update(['status' => 'sent']);
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
            '{{SR_ADDRESS}}'   => $srAddress,
            '{{INVOICENUMBER}}'=> $invoice->invoice_number,
            '{{DATE}}'         => now()->format('F j, Y'),
            '{{name}}'         => $client->name,
            '{{company}}'      => '',
            '{{addressline1}}' => $addrLine1,
            '{{addressline2}}' => $addrLine2,
            '{{notes}}'        => $invoice->notes ?? '',
            '{{TOTAL}}'        => number_format((float) $invoice->amount, 2),
            '{{URL}}'          => '',
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

        // Build description from line items (batch) or invoice description (single)
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

        $this->helpscout->createDraftReply($conversationId, $html, [[
            'fileName' => $filename . '.pdf',
            'mimeType' => 'application/pdf',
            'data'     => base64_encode($pdfBytes),
        ]]);
    }
}
