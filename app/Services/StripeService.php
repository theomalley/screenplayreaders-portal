<?php

// v1.0 — 2026-05-26 | Stripe API client — scaffolded for Stripe Invoicing module

namespace App\Services;

class StripeService
{
    private string $secretKey;
    private string $publishableKey;

    public function __construct()
    {
        $this->secretKey      = (string) config('services.stripe.secret_key');
        $this->publishableKey = (string) config('services.stripe.publishable_key');
    }

    // Invoice methods will be added in the Stripe Invoicing module.
}
