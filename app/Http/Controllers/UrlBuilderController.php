<?php

// v1.1 — 2026-07-23 | Authorization moved to the use-url-builder Gate ability
//                     (AppServiceProvider), replacing inline abort_unless(...) calls.
//                     Covered by tests/Feature/UrlBuilderControllerTest.php.

namespace App\Http\Controllers;

use App\Services\WooCommerceService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class UrlBuilderController extends Controller
{
    public function index()
    {
        $this->authorize('use-url-builder');

        return view('url-builder.index');
    }

    public function uploadLookup(Request $request, WooCommerceService $woo)
    {
        $this->authorize('use-url-builder');

        $request->validate(['order_id' => 'required|integer|min:1']);

        try {
            $order = $woo->getOrder((int) $request->order_id);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => "Order #{$request->order_id} not found."], 404);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'WooCommerce API error: ' . $e->getMessage()], 502);
        }

        $storeUrl = rtrim(config('services.woocommerce.store_url', 'https://screenplayreaders.com'), '/');

        $uploadUrl = $storeUrl . '/upload/?' . http_build_query([
            'order_id' => $order['id'],
            'key'      => $order['order_key'] ?? '',
        ]);

        $products = array_map(fn ($item) => $item['name'] ?? '', $order['line_items'] ?? []);

        return response()->json([
            'url'      => $uploadUrl,
            'status'   => ucfirst(str_replace('-', ' ', $order['status'] ?? '')),
            'customer' => trim(($order['billing']['first_name'] ?? '') . ' ' . ($order['billing']['last_name'] ?? '')),
            'email'    => $order['billing']['email'] ?? '',
            'products' => $products,
        ]);
    }
}
