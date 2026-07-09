<?php

// v1.1 — 2026-07-09 | variation_label and "never expires" now prefer the theme's
//                      sr_registration_variation_label / sr_registration_never_expires
//                      payload fields (set from its admin-configurable term length),
//                      falling back to the legacy hardcoded VAR_* map only for older
//                      payloads. Lets registrations using a new WooCommerce variation
//                      (added via the theme's SR Registration Form Config tool) show a
//                      real label/expiry instead of "Unknown" / a wrong 90-day fallback.
// v1.0 — 2026-06-22 | Initial: receives script registration webhook from WooCommerce,
//                      creates ScriptRegistration records, dispatches certificate generation.
//                      PORTAL INTEGRATION: endpoint called by woo_scriptregistration.php
//                      after a customer completes checkout for product 55560.

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\CopyRegistrationScriptToSpaces;
use App\Jobs\GenerateRegistrationCertificate;
use App\Models\ScriptRegistration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ScriptRegistrationWebhookController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        if (! $this->authorised($request)) {
            return response()->json(['error' => 'Unauthorised.'], 401);
        }

        $data = $request->validate([
            'order_id'       => 'required|string|max:64',
            'order_number'   => 'nullable|string|max:64',
            'customer_name'  => 'required|string|max:255',
            'customer_email' => 'required|email|max:255',
            'payloads'       => 'required|array|min:1',
        ]);

        Log::info('ScriptRegistrationWebhook: received', [
            'order_id' => $data['order_id'],
            'customer_email' => $data['customer_email'],
            'payload_count' => count($data['payloads']),
        ]);

        if (ScriptRegistration::where('woo_order_id', $data['order_id'])->exists()) {
            return response()->json(['status' => 'already_exists'], 200);
        }

        $created = [];

        foreach ($data['payloads'] as $payload) {
            $variationId = (int) ($payload['sr_registration_variation_id'] ?? 0);

            // Prefer the label the theme computed from its admin-configured term (works for
            // any variation, not just the original 4). Fall back to the legacy hardcoded map
            // for payloads from a theme deploy that predates this field.
            $variationLabel = $payload['sr_registration_variation_label']
                ?? ScriptRegistration::VARIATION_LABELS[$variationId]
                ?? 'Unknown';
            $variationLabel = \Illuminate\Support\Str::limit($variationLabel, 20, '');

            // Same story for "never expires" — prefer the explicit flag over the hardcoded ID check.
            $neverExpires = array_key_exists('sr_registration_never_expires', $payload)
                ? $payload['sr_registration_never_expires'] === '1'
                : $variationId === ScriptRegistration::VAR_LIFETIME;

            $registration = ScriptRegistration::create([
                'woo_order_id'      => $data['order_id'],
                'woo_order_number'  => $data['order_number'] ?? null,
                'customer_name'     => $data['customer_name'],
                'customer_email'    => $data['customer_email'],
                'variation_id'      => $variationId,
                'variation_label'   => $variationLabel,
                'registration_id'   => $payload['sr_registration_id'] ?? ScriptRegistration::generateRegistrationId(),
                'script_title'      => $payload['sr_title'] ?? '',
                'page_count'        => ! empty($payload['sr_page_count']) ? (int) $payload['sr_page_count'] : null,
                'type_of_work'      => $payload['sr_type_of_work'] ?? '',
                'author_first'      => $payload['sr_author_first'] ?? '',
                'author_last'       => $payload['sr_author_last'] ?? '',
                'additional_authors' => $payload['sr_additional_authors'] ?? null,
                'street_address'    => $payload['sr_street_address'] ?? '',
                'city'              => $payload['sr_city'] ?? '',
                'state_or_province' => $payload['sr_state_or_province'] ?? '',
                'postal_or_zip'     => $payload['sr_postal_or_zip'] ?? '',
                'country'           => $payload['sr_country'] ?? '',
                'phone'             => $payload['sr_phone'] ?? '',
                'unique_id'         => $payload['sr_unique_id_optional'] ?? null,
                'email'             => $payload['sr_email'] ?? $data['customer_email'],
                'uploaded_file_url' => $payload['sr_uploaded_file_url'] ?? null,
                'uploaded_file_name' => $payload['sr_uploaded_file_original_name'] ?? null,
                'authcode'          => $payload['sr_authcode'] ?? bin2hex(random_bytes(16)),
                'registered_at'     => now(),
                'expires_at'        => $this->parseExpiry($payload['sr_registration_expires'] ?? null, $variationId, $neverExpires),
                'unlimited_token'   => $neverExpires
                    ? ScriptRegistration::generateUnlimitedToken()
                    : null,
                'status'            => ScriptRegistration::STATUS_PENDING,
            ]);

            GenerateRegistrationCertificate::dispatch($registration->id);

            if ($registration->uploaded_file_url) {
                CopyRegistrationScriptToSpaces::dispatch($registration->id);
            }

            $created[] = $registration->id;
        }

        return response()->json([
            'status' => 'accepted',
            'registration_ids' => $created,
        ], 202);
    }

    private function authorised(Request $request): bool
    {
        $secret = config('services.portal.webhook_secret');

        return ! empty($secret) && hash_equals($secret, $request->bearerToken() ?? '');
    }

    private function parseExpiry(?string $expiryString, int $variationId, bool $neverExpires = false): ?\DateTimeInterface
    {
        if ($neverExpires) {
            return null;
        }

        if ($expiryString && $expiryString !== 'Never Expires') {
            try {
                return \Carbon\Carbon::parse($expiryString);
            } catch (\Throwable) {
                // Fall through to calculate from variation
            }
        }

        return match ($variationId) {
            ScriptRegistration::VAR_FREE_90 => now()->addDays(90),
            ScriptRegistration::VAR_5YR     => now()->addYears(5),
            ScriptRegistration::VAR_10YR    => now()->addYears(10),
            default                         => now()->addDays(90),
        };
    }
}
