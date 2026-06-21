<?php

// v1.0 — 2026-06-21 | Initial: distributes surplus customization points across departments
// Ported from step-02-budget-calculations.js lines 24024-24700

namespace App\Services\Budget;

class AllocationCalculator
{
    // Surplus line item multipliers per budget class: [lineItemId => [surplusCategory, [1=>mult, ..., 8=>mult]]]
    private const SURPLUS_ITEMS = [
        // Cast surplus (90% to additional cast, 10% to agent/attorney fees)
        '_570additionalcastbudget' => ['cast', [1=>0.9, 2=>0.9, 3=>0.9, 4=>0.9, 5=>0.9, 6=>0.9, 7=>0.9, 8=>0.9]],
        '_572agentattyfees' => ['cast', [1=>0.1, 2=>0.1, 3=>0.1, 4=>0.1, 5=>0.1, 6=>0.1, 7=>0.1, 8=>0.1]],

        // Stunts surplus
        '_610stuntslaborbudget' => ['stunts', [1=>1.0, 2=>0.75, 3=>0.8, 4=>0.2, 5=>0.6, 6=>0.6, 7=>0.6, 8=>0.6]],
        '_620purchases' => ['stunts', [1=>0, 2=>0, 3=>0.1, 4=>0.6, 5=>0.2, 6=>0.2, 7=>0.2, 8=>0.2]],
        '_630rentals' => ['stunts', [1=>0, 2=>0.15, 3=>0.05, 4=>0.1, 5=>0.1, 6=>0.1, 7=>0.1, 8=>0.1]],
        '_640boxrentals' => ['stunts', [1=>0, 2=>0.1, 3=>0.025, 4=>0.05, 5=>0.05, 6=>0.05, 7=>0.05, 8=>0.05]],
        '_699miscexpenses' => ['stunts', [1=>0, 2=>0.1, 3=>0.025, 4=>0.05, 5=>0.05, 6=>0.05, 7=>0.05, 8=>0.05]],

        // Travel surplus
        '_710travel' => ['travel', [1=>0.5, 2=>0.5, 3=>0.45, 4=>0.45, 5=>0.45, 6=>0.45, 7=>0.45, 8=>0.45]],
        '_720lodging' => ['travel', [1=>0.05, 2=>0.05, 3=>0.5, 4=>0.5, 5=>0.5, 6=>0.5, 7=>0.5, 8=>0.5]],
        '_799miscexpenses' => ['travel', [1=>0.05, 2=>0.05, 3=>0.05, 4=>0.05, 5=>0.05, 6=>0.05, 7=>0.05, 8=>0.05]],

        // Makeup FX surplus
        '_2310makeupfxlaborbudget' => ['mufx', [1=>0.6, 2=>0.2, 3=>0.2, 4=>0.2, 5=>0.5, 6=>0.5, 7=>0.5, 8=>0.5]],
        '_2312materials' => ['mufx', [1=>0.15, 2=>0.15, 3=>0.6, 4=>0.6, 5=>0.275, 6=>0.275, 7=>0.275, 8=>0.275]],
        '_2314rentals' => ['mufx', [1=>0.05, 2=>0.05, 3=>0.15, 4=>0.15, 5=>0.2, 6=>0.2, 7=>0.2, 8=>0.2]],
        '_2399miscexpenses' => ['mufx', [1=>0.05, 2=>0.05, 3=>0.05, 4=>0.05, 5=>0.025, 6=>0.025, 7=>0.025, 8=>0.025]],

        // Special FX surplus
        '_2410specialfxlaborbudget' => ['spfx', [1=>0.6, 2=>0.25, 3=>0.2, 4=>0.2, 5=>0.2, 6=>0.2, 7=>0.325, 8=>0.325]],
        '_2412purchasesrentals' => ['spfx', [1=>0, 2=>0.6, 3=>0.55, 4=>0.55, 5=>0.55, 6=>0.55, 7=>0.43, 8=>0.43]],
        '_2414manufacturing' => ['spfx', [1=>0, 2=>0, 3=>0.05, 4=>0.05, 5=>0.05, 6=>0.05, 7=>0.05, 8=>0.05]],
        '_2416riggingstriking' => ['spfx', [1=>0.1, 2=>0.1, 3=>0, 4=>0, 5=>0, 6=>0, 7=>0.12, 8=>0.12]],
        '_2418boxrentals' => ['spfx', [1=>0.05, 2=>0.05, 3=>0.15, 4=>0.15, 5=>0.15, 6=>0.15, 7=>0.025, 8=>0.025]],
        '_2499miscexpenses' => ['spfx', [1=>0.05, 2=>0.05, 3=>0.05, 4=>0.05, 5=>0.05, 6=>0.05, 7=>0.05, 8=>0.05]],

        // Animals surplus
        '_2510animalslaborbudget' => ['animals', [1=>0.6, 2=>0.25, 3=>0.2, 4=>0.2, 5=>0.25, 6=>0.25, 7=>0.25, 8=>0.25]],
        '_2512animals' => ['animals', [1=>0.1, 2=>0.1, 3=>0.65, 4=>0.65, 5=>0.6, 6=>0.6, 7=>0.6, 8=>0.6]],
        '_2514animalcarefeeding' => ['animals', [1=>0.05, 2=>0.1, 3=>0.1, 4=>0.1, 5=>0.1, 6=>0.1, 7=>0.1, 8=>0.1]],
        '_2516animaltravellodging' => ['animals', [1=>0.05, 2=>0.05, 3=>0.05, 4=>0.05, 5=>0.05, 6=>0.05, 7=>0.05, 8=>0.05]],

        // VFX surplus
        '_2810visualfxbudget' => ['vfx', [1=>1.0, 2=>1.0, 3=>1.0, 4=>1.0, 5=>1.0, 6=>1.0, 7=>1.0, 8=>1.0]],
    ];

    /**
     * Calculate surplus distribution for all customization point categories.
     */
    public function calculate(
        float $budget,
        int $budgetClass,
        float $preSurplusTotal,
        array $surplusPoints
    ): array {
        $contingencyRate = 0.1;
        $contingencyTotal = $budget * $contingencyRate;
        $surplus = $budget - $contingencyTotal - $preSurplusTotal;

        // Points: cast gets unspent points
        $pointsSpent = ($surplusPoints['cast'] ?? 0)
            + ($surplusPoints['stunts'] ?? 0) + ($surplusPoints['travel'] ?? 0)
            + ($surplusPoints['spfx'] ?? 0) + ($surplusPoints['mufx'] ?? 0)
            + ($surplusPoints['animals'] ?? 0) + ($surplusPoints['vfx'] ?? 0);

        $pointsUnspent = 10 - $pointsSpent;
        $castPoints = ($surplusPoints['cast'] ?? 0) + $pointsUnspent;

        // Per-category surplus amounts (points * 10% of total surplus)
        $categorySurplus = [
            'cast'    => $surplus * ($castPoints * 0.1),
            'stunts'  => $surplus * (($surplusPoints['stunts'] ?? 0) * 0.1),
            'travel'  => $surplus * (($surplusPoints['travel'] ?? 0) * 0.1),
            'spfx'    => $surplus * (($surplusPoints['spfx'] ?? 0) * 0.1),
            'mufx'    => $surplus * (($surplusPoints['mufx'] ?? 0) * 0.1),
            'animals' => $surplus * (($surplusPoints['animals'] ?? 0) * 0.1),
            'vfx'     => $surplus * (($surplusPoints['vfx'] ?? 0) * 0.1),
        ];

        // Distribute surplus to line items (floor at 0 — negative surplus means
        // the department is already over-allocated by labor+fringes+non-labor)
        $lineItems = [];
        foreach (self::SURPLUS_ITEMS as $itemKey => [$category, $multipliers]) {
            $mult = $multipliers[$budgetClass] ?? 0;
            $lineItems[$itemKey] = max(0, min($mult * $categorySurplus[$category], 999999999));
        }

        return [
            'contingency_total' => $contingencyTotal,
            'surplus' => $surplus,
            'points_spent' => $pointsSpent,
            'points_unspent' => $pointsUnspent,
            'points_cast' => $castPoints,
            'category_surplus' => $categorySurplus,
            'line_items' => $lineItems,
            'surpluspoints_cast' => $castPoints,
            'surpluspoints_stunts' => $surplusPoints['stunts'] ?? 0,
            'surpluspoints_travel' => $surplusPoints['travel'] ?? 0,
            'surpluspoints_mufx' => $surplusPoints['mufx'] ?? 0,
            'surpluspoints_spfx' => $surplusPoints['spfx'] ?? 0,
            'surpluspoints_animals' => $surplusPoints['animals'] ?? 0,
            'surpluspoints_vfx' => $surplusPoints['vfx'] ?? 0,
            'surpluspoints_total' => $castPoints + ($surplusPoints['stunts'] ?? 0)
                + ($surplusPoints['travel'] ?? 0) + ($surplusPoints['mufx'] ?? 0)
                + ($surplusPoints['spfx'] ?? 0) + ($surplusPoints['animals'] ?? 0)
                + ($surplusPoints['vfx'] ?? 0),
        ];
    }
}
