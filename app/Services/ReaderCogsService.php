<?php

// v1.0 — 2026-11-02 | Extracted so Assignment's saving()/saved() hooks can re-sync an order's
//                     reader COGS the moment a real reader is known, mirroring how
//                     EditorCommissionService replaces the theme's commission estimate with a
//                     portal-computed one independent of what the webhook originally sent.

namespace App\Services;

use App\Models\Assignment;
use App\Models\OrderRevenue;

class ReaderCogsService
{
    /**
     * Recomputes OrderRevenue.cog_reader for $orderNumber from the real pay_rate sum across
     * its Assignment rows, now that at least one reader is actually assigned — replacing the
     * flat per-product COGS estimate the theme (woo_order-financials.php) sent at order
     * completion, before any reader existed. Re-derives cog_total/net_revenue to match.
     *
     * No-ops if the order has no OrderRevenue row yet, or no assignment has an assigned
     * reader yet — in that case the theme's placeholder estimate is left as-is.
     */
    public function syncForOrder(string $orderNumber): void
    {
        $orderRevenue = OrderRevenue::where('order_number', $orderNumber)->first();
        if (! $orderRevenue) {
            return;
        }

        $assignments = Assignment::where('order_number', $orderNumber)
            ->where('status', '!=', Assignment::STATUS_CANCELLED)
            ->whereNotNull('assigned_reader_id')
            ->get();

        if ($assignments->isEmpty()) {
            return;
        }

        $cogReader  = round((float) $assignments->sum('pay_rate'), 2);
        $cogTotal   = round($cogReader + (float) $orderRevenue->cog_processing + (float) $orderRevenue->cog_commission, 2);
        $netRevenue = round((float) $orderRevenue->order_total - $cogTotal, 2);

        $orderRevenue->update([
            'cog_reader'  => $cogReader,
            'cog_total'   => $cogTotal,
            'net_revenue' => $netRevenue,
        ]);
    }
}
