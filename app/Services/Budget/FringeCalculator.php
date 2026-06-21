<?php

// v1.0 — 2026-06-21 | Initial: applies payroll taxes and union fringe benefits with ceilings
// Ported from step-02-budget-calculations.js fringe calculation blocks

namespace App\Services\Budget;

use App\Models\Budget\FringeRate;
use App\Models\Budget\StateRate;

class FringeCalculator
{
    private array $fringeRates = [];
    private float $suiRate = 0.034;
    private float $suiCeiling = 7000;

    public function loadRates(?string $stateName = null): self
    {
        $fringes = FringeRate::all()->keyBy('slug');

        foreach ($fringes as $slug => $fringe) {
            $this->fringeRates[$slug] = [
                'rate' => (float) $fringe->rate,
                'ceiling' => $fringe->ceiling !== null ? (float) $fringe->ceiling : null,
                'hourly_addon' => $fringe->hourly_addon !== null ? (float) $fringe->hourly_addon : null,
            ];
        }

        if ($stateName && $stateName !== '0') {
            $state = StateRate::where('state_name', $stateName)->first();
            if ($state) {
                $this->suiRate = (float) $state->sui_rate;
                $this->suiCeiling = (float) $state->sui_ceiling;
            }
        }

        return $this;
    }

    /**
     * Calculate all fringes for a single crew position.
     *
     * @param float  $laborTotal  Total labor cost for this position
     * @param string $guild       Guild affiliation (WGA, DGA_DIR, DGA_UPM, IATSE, TEAMSTERS)
     * @param int    $guildCode   Resolved guild tier code (0/999 = non-union)
     * @param float  $totalWeeks  Total weeks worked (for IATSE/Teamsters hourly addon)
     * @param float  $workWeekHours Hours per work week (default 40)
     */
    public function calculateFringes(
        float $laborTotal,
        string $guild,
        int $guildCode,
        float $totalWeeks = 0,
        float $workWeekHours = 40
    ): array {
        $isUnion = $guildCode !== 0 && $guildCode !== 999;

        $result = [
            'fica' => $this->applyCapped('fica', $laborTotal),
            'medicare' => $this->applyCapped('medicare', $laborTotal),
            'fui' => $this->applyCapped('fui', $laborTotal),
            'sui' => $this->applyWithCeiling($laborTotal, $this->suiRate, $this->suiCeiling),
            'payroll' => $this->applyCapped('payroll', $laborTotal),
            'wga_pension' => 0, 'wga_health' => 0,
            'dga_pension' => 0, 'dga_health' => 0,
            'sag' => 0,
            'iatse' => 0, 'iatse_hours' => 0, 'iatse_hourly_total' => 0,
            'teamsters' => 0, 'teamsters_hours' => 0, 'teamsters_hourly_total' => 0,
        ];

        if (!$isUnion) {
            $result['fringe_total'] = $result['fica'] + $result['medicare'] + $result['fui']
                + $result['sui'] + $result['payroll'];
            return $result;
        }

        if ($guild === 'WGA') {
            $result['wga_pension'] = $this->applyCapped('wga_pension', $laborTotal);
            $result['wga_health'] = $this->applyCapped('wga_health', $laborTotal);
        } elseif ($guild === 'DGA_DIR' || $guild === 'DGA_UPM') {
            $result['dga_pension'] = $this->applyCapped('dga_pension', $laborTotal);
            $result['dga_health'] = $this->applyCapped('dga_health', $laborTotal);
        } elseif ($guild === 'IATSE') {
            $result['iatse'] = $this->applyCapped('iatse', $laborTotal);
            $hours = $totalWeeks * $workWeekHours;
            $hourlyAddon = $this->fringeRates['iatse']['hourly_addon'] ?? 10.60;
            $result['iatse_hours'] = $hours;
            $result['iatse_hourly_total'] = $hours * $hourlyAddon;
        } elseif ($guild === 'TEAMSTERS') {
            $result['teamsters'] = $this->applyCapped('teamsters', $laborTotal);
            $hours = $totalWeeks * $workWeekHours;
            $hourlyAddon = $this->fringeRates['teamsters']['hourly_addon'] ?? 10.60;
            $result['teamsters_hours'] = $hours;
            $result['teamsters_hourly_total'] = $hours * $hourlyAddon;
        }

        // SAG fringes apply to cast positions regardless of other guild
        // (handled separately by the orchestrator for cast members)

        $result['fringe_total'] = $result['fica'] + $result['medicare'] + $result['fui']
            + $result['sui'] + $result['payroll']
            + $result['wga_pension'] + $result['wga_health']
            + $result['dga_pension'] + $result['dga_health']
            + $result['sag']
            + $result['iatse'] + $result['iatse_hourly_total']
            + $result['teamsters'] + $result['teamsters_hourly_total'];

        return $result;
    }

    /**
     * Calculate SAG fringes for cast members (separate from crew guild fringes).
     */
    public function calculateSAGFringes(float $laborTotal, int $sagCode): array
    {
        if ($sagCode === 0 || $sagCode === 999) {
            return ['sag' => 0];
        }

        return [
            'sag' => $this->applyCapped('sag', $laborTotal),
        ];
    }

    private function applyCapped(string $slug, float $laborTotal): float
    {
        $fringe = $this->fringeRates[$slug] ?? null;
        if (!$fringe) {
            return 0;
        }

        return $this->applyWithCeiling($laborTotal, $fringe['rate'], $fringe['ceiling']);
    }

    private function applyWithCeiling(float $laborTotal, float $rate, ?float $ceiling): float
    {
        if ($ceiling !== null && $laborTotal > $ceiling) {
            return $ceiling * $rate;
        }

        return $laborTotal * $rate;
    }

    public function getSuiRate(): float
    {
        return $this->suiRate;
    }

    public function getSuiCeiling(): float
    {
        return $this->suiCeiling;
    }

    public function getFringeRate(string $slug): array
    {
        return $this->fringeRates[$slug] ?? ['rate' => 0, 'ceiling' => null, 'hourly_addon' => null];
    }
}
