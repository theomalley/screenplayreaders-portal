<?php

// v1.5 — 2026-09-09 | SECURITY: added Stripe Idempotency-Key headers to every POST — a
//                     retried request (double-submitted "Send Invoice" click, a retry
//                     after a slow/timed-out response) could otherwise create a second
//                     full set of invoice items and a second finalized, sent invoice
//                     for the same charge. Also escaped the email in ensureCustomer()'s
//                     search query.
// v1.4 — 2026-05-26 | Return invoice_number (Stripe's human-readable number) in createAndSendInvoice result
// v1.3 — 2026-05-26 | Add pending_invoice_items_behavior=include and log amount_cents to debug $0 invoice
// v1.2 — 2026-05-26 | Accept array of line items in createAndSendInvoice() for batch invoicing
// v1.1 — 2026-05-26 | Add invoice creation and customer management for client invoicing module
// v1.0 — 2026-05-26 | Shell — Stripe secret/publishable key config

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class StripeService
{
    private const BASE = 'https://api.stripe.com/v1';

    private string $secretKey;

    public function __construct()
    {
        $this->secretKey = (string) config('services.stripe.secret_key');
    }

    /**
     * Find an existing Stripe customer by email, or create one.
     * Returns the Stripe customer ID.
     */
    public function ensureCustomer(string $email, string $name): string
    {
        // Escape a literal `"` or `\` in the email before embedding it in Stripe's
        // search query syntax — otherwise an email containing one would malform the
        // query (Stripe's search parser, not SQL — this can't lead to data leakage,
        // only a malformed/failed search).
        $escapedEmail = str_replace(['\\', '"'], ['\\\\', '\\"'], $email);
        $search = $this->get('/customers/search', ['query' => "email:\"{$escapedEmail}\""]);

        if (! empty($search['data'])) {
            return $search['data'][0]['id'];
        }

        $customer = $this->post('/customers', [
            'email' => $email,
            'name'  => $name,
        ]);

        return $customer['id'];
    }

    /**
     * Create a Stripe invoice for a customer, add one or more line items, finalize, and send it.
     * Returns ['invoice_id' => string, 'hosted_invoice_url' => string].
     *
     * $lineItems must be an array of ['description' => string, 'amount_cents' => int] (USD cents).
     * $dueDateTimestamp is a Unix timestamp or null (defaults to net-30).
     */
    public function createAndSendInvoice(
        string $stripeCustomerId,
        array  $lineItems,
        ?int   $dueDateTimestamp = null
    ): array {
        // FIX: none of the POSTs below carried an Idempotency-Key — a retried request
        // (a double-submitted "Send Invoice" click, or a retry after a slow/timed-out
        // response) would create a second full set of invoice items and a second
        // finalized, sent Stripe invoice for the same charge, which the client could
        // receive and pay twice. Anchored to the exact inputs so a genuine retry with
        // identical customer/line-items/due-date replays Stripe's cached result for
        // each step instead of creating new objects; a request with different inputs
        // gets a different key, so it is never blocked from proceeding normally.
        $idempotencyBase = hash('sha256', json_encode([$stripeCustomerId, $lineItems, $dueDateTimestamp]));

        // 1. Create one pending invoice item per line
        foreach ($lineItems as $i => $item) {
            Log::debug('StripeService: creating invoice item', [
                'customer'    => $stripeCustomerId,
                'amount_cents'=> $item['amount_cents'],
                'description' => $item['description'],
            ]);
            $this->post('/invoiceitems', [
                'customer'    => $stripeCustomerId,
                'amount'      => $item['amount_cents'],
                'currency'    => 'usd',
                'description' => $item['description'],
            ], "{$idempotencyBase}-item-{$i}");
        }

        // 2. Create the invoice — include=pending ensures items created above are collected
        $invoiceParams = [
            'customer'                        => $stripeCustomerId,
            'collection_method'               => 'send_invoice',
            'days_until_due'                  => 30,
            'pending_invoice_items_behavior'  => 'include',
        ];

        if ($dueDateTimestamp) {
            unset($invoiceParams['days_until_due']);
            $invoiceParams['due_date'] = $dueDateTimestamp;
        }

        $invoice   = $this->post('/invoices', $invoiceParams, "{$idempotencyBase}-invoice");
        $invoiceId = $invoice['id'];

        // 3. Finalize (locks it)
        $this->post("/invoices/{$invoiceId}/finalize", [], "{$idempotencyBase}-finalize");

        // 4. Send — triggers Stripe's own email to the customer
        $sent = $this->post("/invoices/{$invoiceId}/send", [], "{$idempotencyBase}-send");

        return [
            'invoice_id'         => $invoiceId,
            'invoice_number'     => $sent['number'] ?? $invoiceId,
            'hosted_invoice_url' => $sent['hosted_invoice_url'] ?? '',
        ];
    }

    /**
     * Void a finalized Stripe invoice.
     */
    public function voidInvoice(string $stripeInvoiceId): void
    {
        $this->post("/invoices/{$stripeInvoiceId}/void", [], 'void-' . $stripeInvoiceId);
    }

    // -------------------------------------------------------------------------

    private function client(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withBasicAuth($this->secretKey, '')
            ->withHeader('Stripe-Version', '2024-06-20');
    }

    private function get(string $path, array $params = []): array
    {
        $response = $this->client()->get(self::BASE . $path, $params);

        if ($response->failed()) {
            throw new RuntimeException('Stripe API error: ' . ($response->json('error.message') ?? $response->body()));
        }

        return $response->json();
    }

    private function post(string $path, array $params, ?string $idempotencyKey = null): array
    {
        $client = $this->client()->asForm();
        if ($idempotencyKey !== null) {
            $client = $client->withHeader('Idempotency-Key', $idempotencyKey);
        }

        $response = $client->post(self::BASE . $path, $params);

        if ($response->failed()) {
            throw new RuntimeException('Stripe API error: ' . ($response->json('error.message') ?? $response->body()));
        }

        return $response->json();
    }
}
