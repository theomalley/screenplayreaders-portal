<?php

// v1.0 — 2026-06-21 | Initial: orchestrates full budget calculation, assembles output payload
// Ported from step-02-budget-calculations.js — replaces the 26K-line Zapier Code step

namespace App\Services\Budget;

use App\Models\Budget\CrewPosition;
use App\Models\Budget\DepartmentAllocation;

class BudgetCalculationService
{
    public function calculate(array $input): array
    {
        // 1. Resolve budget class, guild codes, non-union rates, weeks
        $resolver = (new BudgetClassResolver())->resolve($input);

        $budget = $resolver->getBudget();
        $budgetClass = $resolver->getBudgetClass();

        // 2. Load fringe rates for the selected state
        $fringeCalc = (new FringeCalculator())->loadRates($input['shootingstate'] ?? '0');

        // 3. Set up crew rate calculator
        $crewCalc = new CrewRateCalculator($resolver->minimumWageWeekly, $resolver->workWeekHours);

        // 4. Load all crew positions with rate tiers
        $positions = CrewPosition::with('rateTiers')->orderBy('sort_order')->get();

        // 5. Calculate each crew position
        $positionResults = [];
        $payload = [];

        foreach ($positions as $position) {
            $guildCode = $resolver->guildCodeForPosition($position->guild);

            $nonUnionBase = match ($position->guild) {
                'DGA_DIR', 'DGA_UPM' => $resolver->rateNonunionKey,
                'WGA' => $resolver->rateNonunionKey,
                'IATSE' => $resolver->rateNonunionKey,
                'TEAMSTERS' => $resolver->rateNonunionKey,
                default => $resolver->rateNonunionKey,
            };

            $result = $crewCalc->calculatePosition(
                $position, $guildCode, $nonUnionBase, $budgetClass,
                $resolver->weeksPREP, $resolver->weeksSHOOT,
                $resolver->weeksWRAP, $resolver->weeksPOST
            );

            // Calculate fringes for this position
            $fringes = $fringeCalc->calculateFringes(
                $result['labor_total'],
                $position->guild,
                $guildCode,
                $result['total_weeks'],
                $resolver->workWeekHours
            );

            $positionResults[$position->line_item_id] = [
                'position' => $position,
                'result' => $result,
                'fringes' => $fringes,
            ];

            // Build per-position output variables (matching JS template token names)
            $prefix = '_' . $position->line_item_id . $position->slug;
            $this->addPositionToPayload($payload, $prefix, $result, $fringes, $position->guild);
        }

        // 6. Calculate fringe totals (ATL vs BTL production vs BTL post)
        $this->calculateFringeTotals($payload, $positionResults);

        // 7. Calculate department allocation totals
        $this->calculateAllocations($payload, $budget, $budgetClass);

        // 8. Calculate pre-surplus totals
        $preSurplusTotal = ($payload['presurplus_total_ATL'] ?? 0) + ($payload['presurplus_total_BTL'] ?? 0);

        // 9. Surplus distribution
        $surplusPoints = [
            'cast' => (float) ($input['usercast'] ?? 0),
            'stunts' => (float) ($input['userstunts'] ?? 0),
            'travel' => (float) ($input['usertravel'] ?? 0),
            'spfx' => (float) ($input['userspfx'] ?? 0),
            'mufx' => (float) ($input['usermufx'] ?? 0),
            'animals' => (float) ($input['useranimals'] ?? 0),
            'vfx' => (float) ($input['uservfx'] ?? 0),
        ];

        $allocCalc = new AllocationCalculator();
        $surplusResult = $allocCalc->calculate($budget, $budgetClass, $preSurplusTotal, $surplusPoints);

        // Merge surplus line items into payload
        foreach ($surplusResult['line_items'] as $key => $value) {
            $payload[$key] = $value;
        }

        // 10. Add configuration and header variables
        $payload['budget'] = $budget;
        $payload['budgetclass'] = $budgetClass;
        $payload['contingency_total'] = $surplusResult['contingency_total'];
        $payload['weeksPREP'] = $resolver->weeksPREP;
        $payload['weeksSHOOT'] = $resolver->weeksSHOOT;
        $payload['weeksWRAP'] = $resolver->weeksWRAP;
        $payload['weeksPOST'] = $resolver->weeksPOST;

        // Guild codes and texts
        $payload['guildcodeSAG'] = $resolver->guildCodeSAG;
        $payload['guildcodeWGA'] = $resolver->guildCodeWGA;
        $payload['guildcodeDGADIR'] = $resolver->guildCodeDGADIR;
        $payload['guildcodeDGAUPM'] = $resolver->guildCodeDGAUPM;
        $payload['guildcodeIATSE'] = $resolver->guildCodeIATSE;
        $payload['guildcodeTEAMSTERS'] = $resolver->guildCodeTEAMSTERS;
        $payload['guildcodeWGAtext'] = $resolver->guildCodeWGAText;
        $payload['guildcodeSAGtext'] = $resolver->guildCodeSAGText;
        $payload['guildcodeDGADIRtext'] = $resolver->guildCodeDGADIRText;
        $payload['guildcodeDGAUPMtext'] = $resolver->guildCodeDGAUPMText;
        $payload['guildcodeIATSEtext'] = $resolver->guildCodeIATSEText;
        $payload['guildcodeTEAMSTERStext'] = $resolver->guildCodeTEAMSTERSText;

        // Non-union rates
        $payload['rate_nonunionkey'] = $resolver->rateNonunionKey;
        $payload['rate_nonunion2nd'] = $resolver->rateNonunion2nd;
        $payload['rate_nonunion3rd'] = $resolver->rateNonunion3rd;
        $payload['rate_nonunionasst'] = $resolver->rateNonunionAsst;
        $payload['rate_SAG'] = $resolver->rateSAG;
        $payload['rate_minimumwage'] = $resolver->minimumWage;
        $payload['rate_minimumwage_weekly'] = $resolver->minimumWageWeekly;
        $payload['text_stipendwarning'] = $resolver->textStipendWarning;

        // Fringe rate output variables
        $payload['fringeFICA'] = $fringeCalc->getFringeRate('fica')['rate'];
        $payload['fringeMedicare'] = $fringeCalc->getFringeRate('medicare')['rate'];
        $payload['fringeFUI'] = $fringeCalc->getFringeRate('fui')['rate'];
        $payload['fringeSUI'] = $fringeCalc->getSuiRate();
        $payload['fringepayroll'] = $fringeCalc->getFringeRate('payroll')['rate'];
        $payload['fringeWGApension'] = $fringeCalc->getFringeRate('wga_pension')['rate'];
        $payload['fringeWGAhealth'] = $fringeCalc->getFringeRate('wga_health')['rate'];
        $payload['fringeDGApension'] = $fringeCalc->getFringeRate('dga_pension')['rate'];
        $payload['fringeDGAhealth'] = $fringeCalc->getFringeRate('dga_health')['rate'];
        $payload['fringeSAG'] = $fringeCalc->getFringeRate('sag')['rate'];
        $payload['fringeIATSE'] = $fringeCalc->getFringeRate('iatse')['rate'];
        $payload['fringeIATSEhourly'] = $fringeCalc->getFringeRate('iatse')['hourly_addon'] ?? 10.60;
        $payload['fringeTeamsters'] = $fringeCalc->getFringeRate('teamsters')['rate'];
        $payload['fringeTeamstershourly'] = $fringeCalc->getFringeRate('teamsters')['hourly_addon'] ?? 10.60;

        // Surplus points
        $payload['surpluspoints_spent'] = $surplusResult['points_spent'];
        $payload['surpluspoints_unspent'] = $surplusResult['points_unspent'];
        $payload['surpluspoints_cast'] = $surplusResult['surpluspoints_cast'];
        $payload['surpluspoints_stunts'] = $surplusResult['surpluspoints_stunts'];
        $payload['surpluspoints_travel'] = $surplusResult['surpluspoints_travel'];
        $payload['surpluspoints_mufx'] = $surplusResult['surpluspoints_mufx'];
        $payload['surpluspoints_spfx'] = $surplusResult['surpluspoints_spfx'];
        $payload['surpluspoints_animals'] = $surplusResult['surpluspoints_animals'];
        $payload['surpluspoints_vfx'] = $surplusResult['surpluspoints_vfx'];
        $payload['surpluspoints_total'] = $surplusResult['surpluspoints_total'];

        // Header/display fields (pass through from input)
        foreach (['headertitle', 'headerdirector', 'headerdate', 'headernumofepisodes',
                   'headerepisodenumber', 'headerepisodetitle', 'headernamefirst', 'headernamelast',
                   'projecttitle', 'budgettype', 'seriestype'] as $field) {
            $payload[$field] = $input[$field] ?? '';
        }

        // Computed header labels (replicate GF hidden fields 164-176)
        $budgetType = $input['budgettype'] ?? 'Feature or Short Film';
        $seriesType = $input['seriestype'] ?? '';
        $numEpisodes = (int) ($input['headernumofepisodes'] ?? 0);

        if ($budgetType === 'Feature or Short Film') {
            $payload['headerlabelbudget'] = 'Budget | ';
            $payload['headerlabeloverallseries'] = '';
            $payload['headerlabelepisodebudget'] = '';
            $payload['headerlabelepisodenumber'] = '';
            $payload['headerlabelpipe2'] = '';
            $payload['headerlabelepisodes'] = '';
            $payload['headerlabelmakeplural'] = '';
            $payload['headerdollarsign'] = '';
            $payload['headerbudgetperepisode'] = '';
            $payload['headerlabelperepisode'] = '';
            $payload['headerlabelpipe1'] = '';
        } elseif ($seriesType === 'Episode Budget') {
            $payload['headerlabelbudget'] = '';
            $payload['headerlabeloverallseries'] = '';
            $payload['headerlabelepisodebudget'] = 'Episode Budget | ';
            $payload['headerlabelepisodenumber'] = 'Episode ';
            $payload['headerlabelpipe2'] = ' | ';
            $payload['headerlabelepisodes'] = '';
            $payload['headerlabelmakeplural'] = '';
            $payload['headerdollarsign'] = '';
            $payload['headerbudgetperepisode'] = '';
            $payload['headerlabelperepisode'] = '';
            $payload['headerlabelpipe1'] = '';
        } elseif ($seriesType === 'Overall Series Budget') {
            $payload['headerlabelbudget'] = '';
            $payload['headerlabeloverallseries'] = 'Overall Series Budget | ';
            $payload['headerlabelepisodebudget'] = '';
            $payload['headerlabelepisodenumber'] = '';
            $payload['headerlabelpipe2'] = '';
            $payload['headerlabelepisodes'] = ' Episode';
            $payload['headerlabelmakeplural'] = $numEpisodes > 1 ? 's' : '';
            $payload['headerdollarsign'] = $numEpisodes > 1 ? '$' : '';
            $payload['headerbudgetperepisode'] = $numEpisodes > 1 ? number_format($budget / $numEpisodes, 2) : '';
            $payload['headerlabelperepisode'] = $numEpisodes > 1 ? ' Per Episode' : '';
            $payload['headerlabelpipe1'] = ' | ';
        } else {
            $payload['headerlabelbudget'] = 'Budget | ';
            $payload['headerlabeloverallseries'] = '';
            $payload['headerlabelepisodebudget'] = '';
            $payload['headerlabelepisodenumber'] = '';
            $payload['headerlabelpipe2'] = '';
            $payload['headerlabelepisodes'] = '';
            $payload['headerlabelmakeplural'] = '';
            $payload['headerdollarsign'] = '';
            $payload['headerbudgetperepisode'] = '';
            $payload['headerlabelperepisode'] = '';
            $payload['headerlabelpipe1'] = '';
        }

        // Cast member names (pass through)
        for ($i = 1; $i <= 25; $i++) {
            $key = 'cast' . str_pad($i, 2, '0', STR_PAD_LEFT);
            $payload[$key] = $input[$key] ?? '';
        }

        // Tax incentive text
        if (($input['shootingstate'] ?? '0') !== '0') {
            $stateRate = \App\Models\Budget\StateRate::where('state_name', $input['shootingstate'])->first();
            $payload['text_taxincentive'] = $stateRate?->tax_incentive_text ?? '';
        } else {
            $payload['text_taxincentive'] = '';
        }

        // Normalize empty/zero values to empty string (matches JS post-processing)
        foreach ($payload as $key => $value) {
            if ($value === null || $value === 0 || $value === 0.0 || $value === '0') {
                $payload[$key] = '';
            }
        }

        return $payload;
    }

    private function addPositionToPayload(
        array &$payload, string $prefix,
        array $result, array $fringes, string $guild
    ): void {
        // Phase-level output
        foreach (['prep', 'shoot', 'wrap', 'post'] as $phase) {
            $payload[$prefix . 'weeks' . $phase] = $result[$phase]['weeks'];
            $payload[$prefix . 'rate' . $phase] = $result[$phase]['rate'];
        }

        $payload[$prefix . 'labortotal'] = $result['labor_total'];

        // Fringe output
        $payload[$prefix . 'FICA'] = $fringes['fica'];
        $payload[$prefix . 'Medicare'] = $fringes['medicare'];
        $payload[$prefix . 'FUI'] = $fringes['fui'];
        $payload[$prefix . 'SUI'] = $fringes['sui'];
        $payload[$prefix . 'payroll'] = $fringes['payroll'];

        if ($guild === 'WGA') {
            $payload[$prefix . 'WGApension'] = $fringes['wga_pension'];
            $payload[$prefix . 'WGAhealth'] = $fringes['wga_health'];
        } elseif ($guild === 'DGA_DIR' || $guild === 'DGA_UPM') {
            $payload[$prefix . 'DGApension'] = $fringes['dga_pension'];
            $payload[$prefix . 'DGAhealth'] = $fringes['dga_health'];
        } elseif ($guild === 'IATSE') {
            $payload[$prefix . 'IATSE'] = $fringes['iatse'];
            $payload[$prefix . 'IATSEhours'] = $fringes['iatse_hours'];
            $payload[$prefix . 'IATSEhourlytotal'] = $fringes['iatse_hourly_total'];
        } elseif ($guild === 'TEAMSTERS') {
            $payload[$prefix . 'Teamsters'] = $fringes['teamsters'];
            $payload[$prefix . 'Teamstershours'] = $fringes['teamsters_hours'];
            $payload[$prefix . 'Teamstershourlytotal'] = $fringes['teamsters_hourly_total'];
        }
    }

    private function calculateFringeTotals(array &$payload, array $positionResults): void
    {
        $atlDepts = ['writing', 'directing', 'cast'];
        $postDepts = ['post_production', 'post_sound'];

        $totals = [
            'FICA' => ['ATL' => 0, 'prod_BTL' => 0, 'post_BTL' => 0],
            'Medicare' => ['ATL' => 0, 'prod_BTL' => 0, 'post_BTL' => 0],
            'FUI' => ['ATL' => 0, 'prod_BTL' => 0, 'post_BTL' => 0],
            'SUI' => ['ATL' => 0, 'prod_BTL' => 0, 'post_BTL' => 0],
            'payroll' => ['ATL' => 0, 'prod_BTL' => 0, 'post_BTL' => 0],
        ];

        $guildTotals = [
            'WGApension' => 0, 'WGAhealth' => 0,
            'DGApension_prod' => 0, 'DGAhealth_prod' => 0,
            'SAGpension_cast' => 0,
            'IATSE_prod' => 0, 'IATSE_post' => 0,
            'Teamsters_prod' => 0,
        ];

        foreach ($positionResults as $lineId => $data) {
            $dept = $data['position']->department;
            $fringes = $data['fringes'];

            $bucket = in_array($dept, $atlDepts) ? 'ATL'
                : (in_array($dept, $postDepts) ? 'post_BTL' : 'prod_BTL');

            foreach (['FICA', 'Medicare', 'FUI', 'SUI', 'payroll'] as $type) {
                $key = strtolower($type);
                $totals[$type][$bucket] += $fringes[$key] ?? 0;
            }

            $guild = $data['position']->guild;
            if ($guild === 'WGA') {
                $guildTotals['WGApension'] += $fringes['wga_pension'];
                $guildTotals['WGAhealth'] += $fringes['wga_health'];
            } elseif ($guild === 'DGA_DIR' || $guild === 'DGA_UPM') {
                $guildTotals['DGApension_prod'] += $fringes['dga_pension'];
                $guildTotals['DGAhealth_prod'] += $fringes['dga_health'];
            } elseif ($guild === 'IATSE') {
                $totalIatse = $fringes['iatse'] + $fringes['iatse_hourly_total'];
                if (in_array($dept, $postDepts)) {
                    $guildTotals['IATSE_post'] += $totalIatse;
                } else {
                    $guildTotals['IATSE_prod'] += $totalIatse;
                }
            } elseif ($guild === 'TEAMSTERS') {
                $guildTotals['Teamsters_prod'] += $fringes['teamsters'] + $fringes['teamsters_hourly_total'];
            }
        }

        foreach ($totals as $type => $buckets) {
            $payload[$type . 'total_ATL'] = $buckets['ATL'];
            $payload[$type . 'total_prod_BTL'] = $buckets['prod_BTL'];
            $payload[$type . 'total_post_BTL'] = $buckets['post_BTL'];
        }

        $payload['WGApensiontotal'] = $guildTotals['WGApension'];
        $payload['WGAhealthtotal'] = $guildTotals['WGAhealth'];
        $payload['DGApensiontotal_prod_BTL'] = $guildTotals['DGApension_prod'];
        $payload['DGAhealthtotal_prod_BTL'] = $guildTotals['DGAhealth_prod'];
        $payload['SAGpensiontotal_cast'] = $guildTotals['SAGpension_cast'];
        $payload['IATSEgrandtotal_prod_BTL'] = $guildTotals['IATSE_prod'];
        $payload['IATSEgrandtotal_post_BTL'] = $guildTotals['IATSE_post'];
        $payload['Teamstersgrandtotal_prod_BTL'] = $guildTotals['Teamsters_prod'];

        // Pre-surplus totals
        $payload['presurplus_total_ATL'] = $totals['FICA']['ATL'] + $totals['Medicare']['ATL']
            + $totals['FUI']['ATL'] + $totals['SUI']['ATL'] + $totals['payroll']['ATL'];
        $payload['presurplus_total_BTL'] = $totals['FICA']['prod_BTL'] + $totals['Medicare']['prod_BTL']
            + $totals['FUI']['prod_BTL'] + $totals['SUI']['prod_BTL'] + $totals['payroll']['prod_BTL']
            + $totals['FICA']['post_BTL'] + $totals['Medicare']['post_BTL']
            + $totals['FUI']['post_BTL'] + $totals['SUI']['post_BTL'] + $totals['payroll']['post_BTL'];
    }

    private function calculateAllocations(array &$payload, float $budget, int $budgetClass): void
    {
        $allocations = DepartmentAllocation::where('budget_class', $budgetClass)->get();

        foreach ($allocations as $alloc) {
            $key = 'alloc' . $alloc->department_slug;
            $payload[$key] = $budget * (float) $alloc->percentage;
        }
    }
}
