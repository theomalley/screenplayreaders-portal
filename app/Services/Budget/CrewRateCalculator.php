<?php

// v1.0 — 2026-06-21 | Initial: calculates crew position labor cost per phase
// Ported from step-02-budget-calculations.js crewRate(), _scaleWeeklyBase(), wgaWriterFee()

namespace App\Services\Budget;

use App\Models\Budget\CrewPosition;

class CrewRateCalculator
{
    private float $minimumWageWeekly;
    private float $workWeekHours;

    public function __construct(float $minimumWageWeekly, float $workWeekHours = 40)
    {
        $this->minimumWageWeekly = $minimumWageWeekly;
        $this->workWeekHours = $workWeekHours;
    }

    /**
     * Calculate the weekly base rate for a position given a union tier code.
     * Returns the rate from the position's rate tiers, converting hourly to weekly.
     */
    public function scaleWeeklyBase(int $unionCode, CrewPosition $position): float
    {
        $tier = $position->rateTiers->firstWhere('tier_code', $unionCode);
        if (!$tier) {
            return 0;
        }

        return match ($tier->rate_type) {
            'weekly' => (float) $tier->rate_value,
            'hourly' => (float) $tier->rate_value * $this->workWeekHours,
            'flat' => (float) $tier->rate_value,
            'min_wage' => $this->minimumWageWeekly,
            default => 0,
        };
    }

    /**
     * Calculate crew rate for a phase. If non-union (0 or 999), uses non-union base rate.
     */
    public function crewRate(
        float $weeks,
        int $unionCode,
        float $nonUnionBase,
        float $modifier,
        CrewPosition $position
    ): float {
        if ($weeks == 0) {
            return 0;
        }

        if ($unionCode == 0 || $unionCode == 999) {
            return $nonUnionBase * $modifier;
        }

        return $this->scaleWeeklyBase($unionCode, $position) * $modifier;
    }

    /**
     * Calculate WGA writer fee (flat rate with optional publication fee).
     */
    public function wgaWriterFee(
        int $guildCode,
        float $nonWgaAmount,
        float $publicationFee,
        CrewPosition $position
    ): float {
        if ($guildCode == 0 || $guildCode == 999) {
            return $nonWgaAmount;
        }

        $tier = $position->rateTiers->firstWhere('tier_code', $guildCode);
        if (!$tier) {
            return 0;
        }

        $base = (float) $tier->rate_value;
        $pubFee = $tier->add_pub_fee ? $publicationFee : 0;

        return $base + $pubFee;
    }

    /**
     * Calculate full labor cost for a position across all phases.
     * Returns an array with rate, weeks, and labor total per phase, plus overall total.
     */
    public function calculatePosition(
        CrewPosition $position,
        int $unionCode,
        float $nonUnionBase,
        int $budgetClass,
        float $weeksPREP,
        float $weeksSHOOT,
        float $weeksWRAP,
        float $weeksPOST
    ): array {
        $phaseConfig = $position->phase_config;

        if (!$phaseConfig) {
            return $this->emptyResult();
        }

        $bc = (string) $budgetClass;
        $result = [];

        foreach (['prep', 'shoot', 'wrap', 'post'] as $phase) {
            $cfg = $phaseConfig[$phase] ?? ['weeks' => [], 'modifier' => []];
            $weekMultiplier = (float) ($cfg['weeks'][$bc] ?? 0);
            $rateModifier = (float) ($cfg['modifier'][$bc] ?? 0);

            $phaseWeeksVar = match ($phase) {
                'prep' => $weeksPREP,
                'shoot' => $weeksSHOOT,
                'wrap' => $weeksWRAP,
                'post' => $weeksPOST,
            };

            $weeks = $phaseWeeksVar * $weekMultiplier;
            $rate = $this->crewRate($weeks, $unionCode, $nonUnionBase, $rateModifier, $position);

            $result[$phase] = [
                'weeks' => $weeks,
                'rate' => $rate,
                'labor' => $weeks * $rate,
            ];
        }

        $result['labor_total'] = $result['prep']['labor']
            + $result['shoot']['labor']
            + $result['wrap']['labor']
            + $result['post']['labor'];

        $result['total_weeks'] = $result['prep']['weeks']
            + $result['shoot']['weeks']
            + $result['wrap']['weeks']
            + $result['post']['weeks'];

        return $result;
    }

    private function emptyResult(): array
    {
        $empty = ['weeks' => 0, 'rate' => 0, 'labor' => 0];
        return [
            'prep' => $empty, 'shoot' => $empty,
            'wrap' => $empty, 'post' => $empty,
            'labor_total' => 0, 'total_weeks' => 0,
        ];
    }
}
