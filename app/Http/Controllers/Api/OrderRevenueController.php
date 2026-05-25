<?php

// v1.0 — 2026-05-25 | WooCommerce webhook endpoint — receives order financials computed by woo_order-financials.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderRevenue;
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
            'order_number'       => trim((string) $request->input('order_number', '')),
            'woocommerce_order_id' => $request->input('woocommerce_order_id'),
            'ordered_at'         => $request->input('ordered_at'),
            'order_total'        => $request->input('order_total'),
            'discount_amount'    => $request->input('discount_amount', 0),
            'cog_reader'         => $request->input('cog_reader', 0),
            'cog_processing'     => $request->input('cog_processing', 0),
            'cog_precommission'  => $request->input('cog_precommission', 0),
            'cog_commission'     => $request->input('cog_commission', 0),
            'cog_total'          => $request->input('cog_total', 0),
            'net_revenue'        => $request->input('net_revenue', 0),
            'payment_method'     => trim((string) $request->input('payment_method', '')),
            'coupon_code'        => trim((string) $request->input('coupon_code', '')),
            'customer_email'     => trim((string) $request->input('customer_email', '')),
            'services_purchased' => $request->input('services_purchased'),
            'staff_member'       => trim((string) $request->input('staff_member', '')),
            'skip_commission'    => (bool) $request->input('skip_commission', false),
        ];

        $validator = Validator::make($input, [
            'order_number'        => 'required|string|max:64',
            'woocommerce_order_id' => 'nullable|integer',
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
            'services_purchased'  => 'nullable|string',
            'staff_member'        => 'nullable|string|max:128',
            'skip_commission'     => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Validation failed.', 'details' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        // Normalise nullable string fields
        foreach (['payment_method', 'coupon_code', 'customer_email', 'staff_member'] as $field) {
            if (isset($data[$field]) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        OrderRevenue::updateOrCreate(
            ['order_number' => $data['order_number']],
            $data
        );

        return response()->json(['status' => 'ok'], 200);
    }

    private function authorised(Request $request): bool
    {
        $secret = config('services.portal.webhook_secret');

        return ! empty($secret) && $request->bearerToken() === $secret;
    }
}
