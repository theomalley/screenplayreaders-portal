<?php

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
        // 1. Create one pending invoice item per line
        foreach ($lineItems as $item) {
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
            ]);
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

    private function post(string $path, array $params): array
    {
        $response = $this->client()->asForm()->post(self::BASE . $path, $params);

        if ($response->failed()) {
            throw new RuntimeException('Stripe API error: ' . ($response->json('error.message') ?? $response->body()));
        }

        return $response->json();
    }
}
