<?php

// v1.0 — 2026-07-17 | Extracted from Api\OrderRevenueController::recalculateCommission() so it can be
//                     parametrized by a specific editor instead of always looking up "the" editor —
//                     needed once more than one editor account exists.

namespace App\Services;

use App\Models\EditorProfile;
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
}
