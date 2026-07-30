<?php

// v1.1 — 2026-07-30 | Add applyQcAdjustmentForOrder() — reduces an order's stored commission when
//                     the order's assigned editor didn't personally QC (all of) its assignments.
//                     Toggleable via the qc_commission_penalty_enabled setting.
// v1.0 — 2026-07-17 | Extracted from Api\OrderRevenueController::recalculateCommission() so it can be
//                     parametrized by a specific editor instead of always looking up "the" editor —
//                     needed once more than one editor account exists.

namespace App\Services;

use App\Models\Assignment;
use App\Models\EditorProfile;
use App\Models\OrderRevenue;
use App\Models\Setting;
use App\Models\User;

class EditorCommissionService
{
    /**
     * Compute editor commission from the given editor's portal config.
     * Returns null if the editor has no per-product config set up yet (falls back to theme value).
     */
    public function calculate(EditorProfile $editorProfile, string $lineItemsJson, float $precommission): ?float
    {
        $lineItems = json_decode($lineItemsJson, true);
        if (! is_array($lineItems) || empty($lineItems)) {
            return null;
        }

        $commissionConfig = $editorProfile->productCommissionsKeyed();
        $globalRate = (float) ($editorProfile->editor_commission ?? 10.0) / 100.0;

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

            $config = $commissionConfig->get($productId);

            $enabled = $config ? $config->commission_enabled : $defaultElig;

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

    /**
     * Resolve which editor a new/unassigned order should be attributed to:
     * an explicit admin-configured default, else the sole editor if exactly one exists.
     * Returns null if there's genuine ambiguity (2+ editors, no default set) rather than guessing.
     */
    public function resolveDefaultEditor(): ?User
    {
        $defaultId = Setting::getValue('default_editor_id');
        if ($defaultId) {
            $editor = User::where('id', $defaultId)->where('role', 'editor')->first();
            if ($editor) {
                return $editor;
            }
        }

        $editors = User::where('role', 'editor')->where('is_test', false)->get();

        return $editors->count() === 1 ? $editors->first() : null;
    }

    /**
     * Reduce a completed order's stored commission when the order's assigned editor didn't
     * personally QC (all of) its reader assignments. No-op until every non-cancelled/non-on-hold
     * assignment for the order has reached STATUS_COMPLETED — QC participation isn't final until then.
     *
     * Always derives from cog_commission_base (never from the current cog_commission), so calling
     * this repeatedly — from a later QC approval or a webhook resync — never compounds the penalty.
     *
     * Factor: full commission if the editor personally approved every countable assignment;
     * k/N if they approved some but not all; a 50% floor if they approved none (whether N is 1 or more).
     */
    public function applyQcAdjustmentForOrder(string $orderNumber): void
    {
        $orderRevenue = OrderRevenue::where('order_number', $orderNumber)->first();
        if (! $orderRevenue || ! $orderRevenue->editor_id) {
            return;
        }

        $countable = Assignment::where('order_number', $orderNumber)
            ->whereNotIn('status', [
                Assignment::STATUS_CANCELLED,
                Assignment::STATUS_ON_HOLD_CUSTOMER,
                Assignment::STATUS_ON_HOLD_SR,
            ])
            ->get();

        if ($countable->isEmpty() || $countable->contains(fn (Assignment $a) => $a->status !== Assignment::STATUS_COMPLETED)) {
            return;
        }

        $base = (float) ($orderRevenue->cog_commission_base ?? $orderRevenue->cog_commission);

        $penaltyEnabled = (string) Setting::getValue('qc_commission_penalty_enabled', '1') === '1';

        if (! $penaltyEnabled) {
            $factor = 1.0;
        } else {
            $n = $countable->count();
            $k = $countable->filter(fn (Assignment $a) => $a->qc_completed_by_user_id === $orderRevenue->editor_id)->count();
            $factor = $k === 0 ? 0.5 : min(1.0, $k / $n);
        }

        $newCommission = round($base * $factor, 2);
        $cogTotal      = round((float) $orderRevenue->cog_reader + (float) $orderRevenue->cog_processing + $newCommission, 2);
        $netRevenue    = round((float) $orderRevenue->order_total - $cogTotal, 2);

        $orderRevenue->update([
            'cog_commission' => $newCommission,
            'cog_total'      => $cogTotal,
            'net_revenue'    => $netRevenue,
        ]);
    }
}
