<?php

// v1.0 — 2026-07-17 | Live WooCommerce retail prices for the Ratebook page's Retail Price
//                     column. Cached forever, refreshed by retail-prices:sync (hourly) or the
//                     admin "Refresh from WooCommerce" button — the Ratebook page itself never
//                     calls out to WooCommerce, so it never depends on that site being up.

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class RetailPriceService
{
    private const CACHE_KEY = 'sr_retail_prices';

    /**
     * Ratebook key => WooCommerce product(s). 'single' rows read one product's price;
     * 'multi' rows read the 1R/2R/3R variation prices of a parent variable product.
     * rate_sr_notes_only and rate_sr_budget have no WooCommerce product and are deliberately
     * absent — those retail prices are entered manually (see Setting::RETAIL_MANUAL_KEYS),
     * same as the rate_wd_* rows.
     */
    public const PRODUCT_MAP = [
        'rate_sr_script_coverage'   => ['type' => 'multi', 'parent' => 54909, 'variations' => ['1' => 54911, '2' => 54912, '3' => 54913]],
        'rate_sr_short'             => ['type' => 'single', 'id' => 5871],
        'rate_sr_deep_dive'         => ['type' => 'single', 'id' => 5868],
        'rate_sr_oversized_121_160' => ['type' => 'multi', 'parent' => 54914, 'variations' => ['1' => 54923, '2' => 54926, '3' => 54930]],
        'rate_sr_rush'              => ['type' => 'multi', 'parent' => 54936, 'variations' => ['1' => 54937, '2' => 54938, '3' => 54939]],
        'rate_sr_request'           => ['type' => 'multi', 'parent' => 54943, 'variations' => ['1' => 54944, '2' => 54945, '3' => 54946]],
        'rate_sr_proofreading'      => ['type' => 'single', 'id' => 23425],
    ];

    /**
     * Pull fresh prices from WooCommerce for every mapped key. Builds the whole result before
     * returning so a failure partway through never leaves refresh() with a half-updated cache.
     */
    public static function fetch(): array
    {
        $wc = new WooCommerceService();
        $result = [];

        foreach (self::PRODUCT_MAP as $key => $config) {
            if ($config['type'] === 'single') {
                $product = $wc->getProduct($config['id']);
                $result[$key] = ['single' => $product['price'] !== '' ? (float) $product['price'] : null];
                continue;
            }

            $variations = $wc->getProductVariations($config['parent']);
            $pricesById = array_column($variations, 'price', 'id');

            $tiers = [];
            foreach ($config['variations'] as $reader => $variationId) {
                $price = $pricesById[$variationId] ?? '';
                $tiers[$reader] = $price !== '' ? (float) $price : null;
            }
            $result[$key] = $tiers;
        }

        return $result;
    }

    /**
     * Refresh the cached prices from WooCommerce and record the sync time.
     * Throws whatever RuntimeException the WooCommerce client raises — callers decide how to
     * surface that (flash message for the admin button, log-and-swallow for the schedule).
     */
    public static function refresh(): array
    {
        $prices = self::fetch();

        Cache::forever(self::CACHE_KEY, $prices);
        Setting::setValue('retail_prices_synced_at', now()->toISOString());

        return $prices;
    }

    /** Last successfully fetched prices, keyed by Ratebook rate key. Empty if never synced. */
    public static function cached(): array
    {
        return Cache::get(self::CACHE_KEY, []);
    }

    public static function lastSyncedAt(): ?\Carbon\Carbon
    {
        $value = Setting::getValue('retail_prices_synced_at');

        return $value ? \Carbon\Carbon::parse($value) : null;
    }
}
