<?php

// v1.3 — 2026-05-26 | Cast numeric NOT-NULL fields to float to reject null from callers
// v1.2 — 2026-05-25 | Accept customer/order detail fields for Order Log
// v1.1 — 2026-05-25 | Accept line_items_json; recalculate cog_commission using portal config
// v1.0 — 2026-05-25 | WooCommerce webhook endpoint — receives order financials

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EditorProductCommission;
use App\Models\OrderRevenue;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OrderRevenueController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        if (! $this->authorised($request)) {
            return response()->json(['error' => 'Unauthorised.'], 401);
        }

        $input = [
            'order_number'        => trim((string) $request->input('order_number', '')),
            'woocommerce_order_id'=> $request->input('woocommerce_order_id'),
            'ordered_at'          => $request->input('ordered_at'),
            'order_total'         => $request->input('order_total'),
            'discount_amount'     => (float) ($request->input('discount_amount') ?? 0),
            'cog_reader'          => (float) ($request->input('cog_reader') ?? 0),
            'cog_processing'      => (float) ($request->input('cog_processing') ?? 0),
            'cog_precommission'   => (float) ($request->input('cog_precommission') ?? 0),
            'cog_commission'      => (float) ($request->input('cog_commission') ?? 0),
            'cog_total'           => (float) ($request->input('cog_total') ?? 0),
            'net_revenue'         => (float) ($request->input('net_revenue') ?? 0),
            'payment_method'      => trim((string) $request->input('payment_method', '')),
            'coupon_code'         => trim((string) $request->input('coupon_code', '')),
            'customer_email'      => trim((string) $request->input('customer_email', '')),
            'customer_name'       => trim((string) $request->input('customer_name', '')),
            'customer_phone'      => trim((string) $request->input('customer_phone', '')),
            'customer_address'    => trim((string) $request->input('customer_address', '')),
            'script_title'        => trim((string) $request->input('script_title', '')),
            'sku'                 => trim((string) $request->input('sku', '')),
            'ticket_summary'      => trim((string) $request->input('ticket_summary', '')),
            'order_quantity'      => $request->input('order_quantity'),
            'invoice_number'      => trim((string) $request->input('invoice_number', '')),
            'services_purchased'  => $request->input('services_purchased'),
            'line_items_json'     => $request->input('line_items_json'),
            'staff_member'        => trim((string) $request->input('staff_member', '')),
            'skip_commission'     => (bool) $request->input('skip_commission', false),
        ];

        $validator = Validator::make($input, [
            'order_number'        => 'required|string|max:64',
            'woocommerce_order_id'=> 'nullable|integer',
            'ordered_at'          => 'required|date',
            'order_total'         => 'required|numeric',
            'discount_amount'     => 'numeric',
            'cog_reader'          => 'numeric',
            'cog_processing'      => 'numeric',
            'cog_precommission'   => 'numeric',
            'cog_commission'      => 'numeric',
            'cog_total'           => 'numeric',
            'net_revenue'         => 'numeric',
            'payment_method'      => 'nullable|string|max:64',
            'coupon_code'         => 'nullable|string|max:128',
            'customer_email'      => 'nullable|email|max:255',
            'customer_name'       => 'nullable|string|max:255',
            'customer_phone'      => 'nullable|string|max:50',
            'customer_address'    => 'nullable|string',
            'script_title'        => 'nullable|string|max:500',
            'sku'                 => 'nullable|string|max:255',
            'ticket_summary'      => 'nullable|string|max:500',
            'order_quantity'      => 'nullable|string|max:100',
            'invoice_number'      => 'nullable|string|max:100',
            'services_purchased'  => 'nullable|string',
            'line_items_json'     => 'nullable|string',
            'staff_member'        => 'nullable|string|max:128',
            'skip_commission'     => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Validation failed.', 'details' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        foreach (['payment_method', 'coupon_code', 'customer_email', 'staff_member',
                  'customer_name', 'customer_phone', 'customer_address', 'script_title',
                  'sku', 'ticket_summary', 'invoice_number'] as $field) {
            if (isset($data[$field]) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        // Recalculate editor commission using portal's per-product config (if line_items available)
        if (! $data['skip_commission'] && ! empty($data['line_items_json'])) {
            $recalculated = $this->recalculateCommission(
                $data['line_items_json'],
                (float) $data['cog_precommission']
            );
            if ($recalculated !== null) {
                $data['cog_commission'] = $recalculated;
                // Recompute dependent totals
                $data['cog_total']   = round((float) $data['cog_reader'] + (float) $data['cog_processing'] + $recalculated, 2);
                $data['net_revenue'] = round((float) $data['order_total'] - $data['cog_total'], 2);
            }
        }

        OrderRevenue::updateOrCreate(
            ['order_number' => $data['order_number']],
            $data
        );

        return response()->json(['status' => 'ok'], 200);
    }

    /**
     * Compute editor commission from portal config.
     * Returns null if no editor profile/config found (falls back to theme value).
     */
    private function recalculateCommission(string $lineItemsJson, float $precommission): ?float
    {
        $lineItems = json_decode($lineItemsJson, true);
        if (! is_array($lineItems) || empty($lineItems)) {
            return null;
        }

        // Find the active editor (first editor user with a profile)
        $editor = User::where('role', 'editor')
            ->whereHas('editorProfile')
            ->first();

        if (! $editor) {
            return null;
        }

        $editorProfile = $editor->editorProfile;
        $commissionConfig = $editorProfile->productCommissionsKeyed();
        $globalRate = (float) Setting::getValue('rate_editor_commission', 10.0) / 100.0;

        // If no per-product config has been set up yet, fall back to theme value
        if ($commissionConfig->isEmpty()) {
            return null;
        }

        $totalCommission   = 0.0;
        $eligibleLineTotal = 0.0;
        $totalLineTotal    = 0.0;
        $anyEligible       = false;

        foreach ($lineItems as $item) {
            $productId   = (int) ($item['product_id'] ?? 0);
            $lineTotal   = (float) ($item['line_total'] ?? 0);
            $defaultElig = (bool) ($item['commission_eligible'] ?? false);
            $totalLineTotal += $lineTotal;

            // Look up portal config for this product
            $config = $commissionConfig->get($productId);

            if ($config) {
                $enabled = $config->commission_enabled;
            } else {
                $enabled = $defaultElig;
            }

            if (! $enabled) continue;

            $anyEligible = true;

            // Custom flat amount: add directly
            if ($config && $config->custom_amount !== null) {
                $totalCommission += (float) $config->custom_amount;
            } else {
                $eligibleLineTotal += $lineTotal;
            }
        }

        if (! $anyEligible) {
            return 0.0;
        }

        // For non-custom products, apply global rate to their share of precommission
        if ($eligibleLineTotal > 0 && $totalLineTotal > 0 && $precommission > 0) {
            $eligibleShare = $eligibleLineTotal / $totalLineTotal;
            $totalCommission += round($precommission * $eligibleShare * $globalRate, 2);
        }

        return round($totalCommission, 2);
    }

    private function authorised(Request $request): bool
    {
        $secret = config('services.portal.webhook_secret');
        return ! empty($secret) && $request->bearerToken() === $secret;
    }
}
