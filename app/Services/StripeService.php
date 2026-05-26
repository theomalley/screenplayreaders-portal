<?php

// v1.1 — 2026-05-26 | Add invoice creation and customer management for client invoicing module
// v1.0 — 2026-05-26 | Shell — Stripe secret/publishable key config

namespace App\Services;

use Illuminate\Support\Facades\Http;
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
        $search = $this->get('/customers/search', ['query' => "email:\"{$email}\""]);

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
     * Create a Stripe invoice for a customer, add a line item, finalize, and send it.
     * Returns ['invoice_id' => string, 'hosted_invoice_url' => string].
     * $amountCents is in USD cents (e.g. 5000 = $50.00).
     * $dueDateTimestamp is a Unix timestamp or null (defaults to net-30).
     */
    public function createAndSendInvoice(
        string  $stripeCustomerId,
        string  $description,
        int     $amountCents,
        ?int    $dueDateTimestamp = null
    ): array {
        // 1. Create invoice item (pending item attached to customer)
        $this->post('/invoiceitems', [
            'customer'    => $stripeCustomerId,
            'amount'      => $amountCents,
            'currency'    => 'usd',
            'description' => $description,
        ]);

        // 2. Create the invoice
        $invoiceParams = [
            'customer'          => $stripeCustomerId,
            'collection_method' => 'send_invoice',
            'days_until_due'    => 30,
        ];

        if ($dueDateTimestamp) {
            unset($invoiceParams['days_until_due']);
            $invoiceParams['due_date'] = $dueDateTimestamp;
        }

        $invoice   = $this->post('/invoices', $invoiceParams);
        $invoiceId = $invoice['id'];

        // 3. Finalize (locks it)
        $this->post("/invoices/{$invoiceId}/finalize", []);

        // 4. Send — triggers Stripe's own email to the customer
        $sent = $this->post("/invoices/{$invoiceId}/send", []);

        return [
            'invoice_id'         => $invoiceId,
            'hosted_invoice_url' => $sent['hosted_invoice_url'] ?? '',
        ];
    }

    /**
     * Void a finalized Stripe invoice.
     */
    public function voidInvoice(string $stripeInvoiceId): void
    {
        $this->post("/invoices/{$stripeInvoiceId}/void", []);
    }

    // -------------------------------------------------------------------------

    private function get(string $path, array $params = []): array
    {
        $response = Http::withBasicAuth($this->secretKey, '')
            ->get(self::BASE . $path, $params);

        if ($response->failed()) {
            throw new RuntimeException('Stripe API error: ' . ($response->json('error.message') ?? $response->body()));
        }

        return $response->json();
    }

    private function post(string $path, array $params): array
    {
        $response = Http::withBasicAuth($this->secretKey, '')
            ->asForm()
            ->post(self::BASE . $path, $params);

        if ($response->failed()) {
            throw new RuntimeException('Stripe API error: ' . ($response->json('error.message') ?? $response->body()));
        }

        return $response->json();
    }
}
