<?php

// v1.0 — 2026-06-21 | Initial: seeds all budget reference data from JS calculation engine

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Budget\CrewPosition;
use App\Models\Budget\RateTier;
use App\Models\Budget\FringeRate;
use App\Models\Budget\StateRate;
use App\Models\Budget\GuildTierMapping;
use App\Models\Budget\DepartmentAllocation;

class BudgetReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedFringeRates();
        $this->seedStateRates();
        $this->seedCrewPositionsAndTiers();
        $this->seedGuildTierMappings();
        $this->seedDepartmentAllocations();
        $this->seedPhaseConfigs();
    }

    private function seedFringeRates(): void
    {
        $fringes = require __DIR__ . '/data/budget_fringe_rates.php';

        foreach ($fringes as $fringe) {
            FringeRate::updateOrCreate(
                ['slug' => $fringe['slug']],
                $fringe
            );
        }
    }

    private function seedStateRates(): void
    {
        $states = require __DIR__ . '/data/budget_state_rates.php';

        foreach ($states as $state) {
            StateRate::updateOrCreate(
                ['state_name' => $state['state_name']],
                $state
            );
        }
    }

    private function seedCrewPositionsAndTiers(): void
    {
        $positions = require __DIR__ . '/data/budget_crew_positions.php';

        foreach ($positions as [$lineItemId, $slug, $name, $department, $guild, $sortOrder, $tiers]) {
            $position = CrewPosition::updateOrCreate(
                ['line_item_id' => $lineItemId],
                [
                    'slug' => $slug,
                    'name' => $name,
                    'department' => $department,
                    'guild' => $guild,
                    'sort_order' => $sortOrder,
                ]
            );

            foreach ($tiers as [$tierCode, $rateType, $rateValue, $addPubFee]) {
                RateTier::updateOrCreate(
                    ['crew_position_id' => $position->id, 'tier_code' => $tierCode],
                    [
                        'rate_type' => $rateType,
                        'rate_value' => $rateValue,
                        'add_pub_fee' => $addPubFee,
                    ]
                );
            }
        }
    }

    private function seedGuildTierMappings(): void
    {
        // Guild tier code used at each budget class when "all guilds automatic" is selected.
        // Guild code resolution also depends on budget dollar amount — full logic lives in
        // BudgetClassResolver (Phase 3). These are the representative defaults.
        $mappings = [
            // WGA: 999 (non-union) for classes 1-3, varies 4-5, full rate 6-8
            'WGA'       => [1 => 999, 2 => 999, 3 => 999, 4 => 202, 5 => 203, 6 => 203, 7 => 299, 8 => 299],
            // DGA Director: 999 (non-union) for classes 1-4, escalates 5-8
            'DGA_DIR'   => [1 => 999, 2 => 999, 3 => 999, 4 => 999, 5 => 301, 6 => 304, 7 => 399, 8 => 399],
            // DGA UPM/ADs: 999 (non-union) for classes 1-4, escalates 5-8
            'DGA_UPM'   => [1 => 999, 2 => 999, 3 => 999, 4 => 999, 5 => 402, 6 => 404, 7 => 499, 8 => 499],
            // IATSE: 999 (non-union) for classes 1-4, escalates 5-8
            'IATSE'     => [1 => 999, 2 => 999, 3 => 999, 4 => 500, 5 => 501, 6 => 502, 7 => 504, 8 => 599],
            // Teamsters: 999 (non-union) for classes 1-5, union for 6-8
            'TEAMSTERS' => [1 => 999, 2 => 999, 3 => 999, 4 => 999, 5 => 999, 6 => 699, 7 => 699, 8 => 699],
        ];

        foreach ($mappings as $guild => $classTiers) {
            foreach ($classTiers as $budgetClass => $tierCode) {
                GuildTierMapping::updateOrCreate(
                    ['guild' => $guild, 'budget_class' => $budgetClass],
                    ['tier_code' => $tierCode]
                );
            }
        }
    }

    private function seedPhaseConfigs(): void
    {
        $configs = require __DIR__ . '/data/budget_phase_configs.php';

        foreach ($configs as $lineItemId => $phaseConfig) {
            CrewPosition::where('line_item_id', $lineItemId)
                ->update(['phase_config' => json_encode($phaseConfig)]);
        }
    }

    private function seedDepartmentAllocations(): void
    {
        $allocations = require __DIR__ . '/data/budget_department_allocations.php';

        foreach ($allocations as $deptSlug => $classPcts) {
            foreach ($classPcts as $budgetClass => $pct) {
                DepartmentAllocation::updateOrCreate(
                    ['department_slug' => $deptSlug, 'budget_class' => $budgetClass],
                    ['percentage' => $pct]
                );
            }
        }
    }
}
