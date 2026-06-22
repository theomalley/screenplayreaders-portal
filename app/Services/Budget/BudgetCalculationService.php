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
            // Writer (210) is handled entirely by calculateWriterProducer —
            // the JS outputs _210writerrate as a flat fee, not per-phase rates.
            if ($position->line_item_id === '210') {
                continue;
            }

            $guildCode = $resolver->guildCodeForPosition($position->guild);
            $nonUnionBase = $resolver->rateNonunionKey;

            $result = $crewCalc->calculatePosition(
                $position, $guildCode, $nonUnionBase, $budgetClass,
                $resolver->weeksPREP, $resolver->weeksSHOOT,
                $resolver->weeksWRAP, $resolver->weeksPOST
            );

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

            $prefix = '_' . $position->line_item_id . $position->slug;
            $this->addPositionToPayload($payload, $prefix, $result, $fringes, $position->guild);
        }

        // 6. Calculate fringe totals (ATL vs BTL production vs BTL post)
        $this->calculateFringeTotals($payload, $positionResults);

        // 7. Cast member calculations (before totals — cast labor counts toward ATL)
        $this->calculateCastMembers($payload, $input, $resolver, $fringeCalc);

        // 7b. Add cast fringes to ATL totals and compute SAGpensiontotal_cast.
        // JS: FICAtotal_ATL = writerFICA + directorFICA + FICAtotal_cast
        $castSize = (int) ($input['usercastsize'] ?? 0);
        for ($i = 1; $i <= $castSize; $i++) {
            $prefix = '_' . (510 + ($i * 2)) . 'cast' . str_pad($i, 2, '0', STR_PAD_LEFT);
            $payload['FICAtotal_ATL'] = ($payload['FICAtotal_ATL'] ?? 0) + (float) ($payload[$prefix . 'FICA'] ?? 0);
            $payload['Medicaretotal_ATL'] = ($payload['Medicaretotal_ATL'] ?? 0) + (float) ($payload[$prefix . 'Medicare'] ?? 0);
            $payload['FUItotal_ATL'] = ($payload['FUItotal_ATL'] ?? 0) + (float) ($payload[$prefix . 'FUI'] ?? 0);
            $payload['SUItotal_ATL'] = ($payload['SUItotal_ATL'] ?? 0) + (float) ($payload[$prefix . 'SUI'] ?? 0);
            $payload['payrolltotal_ATL'] = ($payload['payrolltotal_ATL'] ?? 0) + (float) ($payload[$prefix . 'payroll'] ?? 0);
            $payload['SAGpensiontotal_cast'] = ($payload['SAGpensiontotal_cast'] ?? 0) + (float) ($payload[$prefix . 'SAGpension'] ?? 0);
        }

        // 8. Writer and producer variables (before totals — ATL labor)
        $this->calculateWriterProducer($payload, $input, $resolver, $crewCalc, $fringeCalc, $positions);

        // 9. Department allocation amounts
        $this->calculateAllocations($payload, $budget, $budgetClass);

        // 10. Non-labor allocation line items (equipment, rentals, etc.)
        // These must be computed BEFORE surplus — they're part of presurplus totals
        $this->calculateNonLaborItems($payload, $positionResults, $budget, $budgetClass);

        // 11. Calculate pre-surplus totals
        $preSurplusTotal = $this->computePreSurplusTotal($payload, $positionResults, $budget, $budgetClass);

        // 11b. Scale down to fit within budget - contingency.
        // First reduce non-labor, then if still over, reduce all variable components
        // proportionally. This handles low-budget cases where cast labor alone
        // exceeds the available amount.
        $available = $budget * 0.9;
        if ($preSurplusTotal > $available) {
            $nonLaborItems = require database_path('seeders/data/budget_nonlabor_items.php');
            $nonLaborTotal = 0;
            foreach ($nonLaborItems as $varName => $_) {
                $nonLaborTotal += (float) ($payload[$varName] ?? 0);
            }

            $excess = $preSurplusTotal - $available;

            if ($nonLaborTotal >= $excess) {
                // Non-labor reduction is enough
                $scaleFactor = ($nonLaborTotal - $excess) / $nonLaborTotal;
                foreach ($nonLaborItems as $varName => $_) {
                    $payload[$varName] = (float) ($payload[$varName] ?? 0) * $scaleFactor;
                }
            } else {
                // Zero out all non-labor items
                foreach ($nonLaborItems as $varName => $_) {
                    $payload[$varName] = 0;
                }
                // Scale remaining excess from all variable payload items (labor, fringes)
                $remainingExcess = $excess - $nonLaborTotal;
                $variableTotal = $preSurplusTotal - $nonLaborTotal;
                if ($variableTotal > 0) {
                    $laborScale = max(0, ($variableTotal - $remainingExcess) / $variableTotal);

                    // Scale crew position labor
                    foreach ($positionResults as $data) {
                        $pos = $data['position'];
                        $prefix = '_' . $pos->line_item_id . $pos->slug;
                        foreach (['prep', 'shoot', 'wrap', 'post'] as $phase) {
                            $rateKey = $prefix . 'rate' . $phase;
                            if (isset($payload[$rateKey]) && is_numeric($payload[$rateKey])) {
                                $payload[$rateKey] = (float) $payload[$rateKey] * $laborScale;
                            }
                        }
                        $payload[$prefix . 'labortotal'] = (float) ($payload[$prefix . 'labortotal'] ?? 0) * $laborScale;
                    }

                    // Scale cast labor
                    for ($i = 1; $i <= 25; $i++) {
                        $prefix = '_' . (510 + ($i * 2)) . 'cast' . str_pad($i, 2, '0', STR_PAD_LEFT);
                        $payload[$prefix . 'rate'] = (float) ($payload[$prefix . 'rate'] ?? 0) * $laborScale;
                        $payload[$prefix . 'labortotal'] = (float) ($payload[$prefix . 'labortotal'] ?? 0) * $laborScale;
                    }

                    // Scale writer/producer
                    foreach (['_210writerrate', '_210writerlabortotal', '_310producersrate', '_310producerslabortotal'] as $k) {
                        $payload[$k] = (float) ($payload[$k] ?? 0) * $laborScale;
                    }

                    // Scale fringe totals
                    $fringeKeys = [
                        'FICAtotal_ATL', 'Medicaretotal_ATL', 'FUItotal_ATL', 'SUItotal_ATL', 'payrolltotal_ATL',
                        '_210writerWGApension', '_210writerWGAhealth', 'SAGpensiontotal_cast',
                        '_410directorDGApension', '_410directorDGAhealth',
                        'FICAtotal_prod_BTL', 'Medicaretotal_prod_BTL', 'FUItotal_prod_BTL',
                        'SUItotal_prod_BTL', 'payrolltotal_prod_BTL',
                        'DGApensiontotal_prod_BTL', 'DGAhealthtotal_prod_BTL',
                        'IATSEgrandtotal_prod_BTL', 'Teamstersgrandtotal_prod_BTL',
                        'FICAtotal_post_BTL', 'Medicaretotal_post_BTL', 'FUItotal_post_BTL',
                        'SUItotal_post_BTL', 'payrolltotal_post_BTL',
                        'IATSEgrandtotal_post_BTL',
                    ];
                    foreach ($fringeKeys as $k) {
                        $payload[$k] = (float) ($payload[$k] ?? 0) * $laborScale;
                    }
                }
            }

            $preSurplusTotal = $this->computePreSurplusTotal($payload, $positionResults, $budget, $budgetClass);
        }

        // 12. Surplus distribution (customization points allocate what's left)
        // GF form computes defaults when user picks "No, Screenplay Readers do it":
        //   Budget < $500K: cast=8.5, stunts=0.5, spfx=0.5, mufx=0.5
        //   Budget >= $500K: cast=4, stunts=1, spfx=1, mufx=1, vfx=3
        $surplusPoints = [
            'cast' => (float) ($input['usercast'] ?? ($budget < 500000 ? 8.5 : 4)),
            'stunts' => (float) ($input['userstunts'] ?? ($budget < 500000 ? 0.5 : 1)),
            'travel' => (float) ($input['usertravel'] ?? 0),
            'spfx' => (float) ($input['userspfx'] ?? ($budget < 500000 ? 0.5 : 1)),
            'mufx' => (float) ($input['usermufx'] ?? ($budget < 500000 ? 0.5 : 1)),
            'animals' => (float) ($input['useranimals'] ?? 0),
            'vfx' => (float) ($input['uservfx'] ?? ($budget < 500000 ? 0 : 3)),
        ];

        $allocCalc = new AllocationCalculator();
        $surplusResult = $allocCalc->calculate($budget, $budgetClass, $preSurplusTotal, $surplusPoints);

        // Merge surplus line items into payload
        foreach ($surplusResult['line_items'] as $key => $value) {
            $payload[$key] = $value;
        }

        // 13. Text/label variables
        $this->calculateTextVariables($payload, $resolver);

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

        // Normalize null values to empty string, but keep numeric 0 as "0"
        // so spreadsheet formulas can compute on them
        foreach ($payload as $key => $value) {
            if ($value === null) {
                $payload[$key] = '';
            }
        }

        return $payload;
    }

    private function computePreSurplusTotal(
        array &$payload, array $positionResults, float $budget, int $budgetClass
    ): float {
        // Sum what the spreadsheet's SUM(G61:G171) + SUM(G177:G1029) captures:
        // - Crew position labor (per-phase weeks × rate) → column G detail rows
        // - Non-labor allocation items → column G via =F formulas
        // - Department fringe totals → column G fringe section
        // Writer, producer, and cast labor go into column H via section formulas,
        // so the grand total picks them up through the H-chain, not through SUM(G...).
        // We must match the spreadsheet's accounting to make surplus correct.

        // Crew position labor (from payload, so scaled values are used after step 11b)
        $crewLabor = 0;
        foreach ($positionResults as $data) {
            $pos = $data['position'];
            $prefix = '_' . $pos->line_item_id . $pos->slug;
            $crewLabor += (float) ($payload[$prefix . 'labortotal'] ?? 0);
        }

        // Non-labor items
        $nonLaborItems = require database_path('seeders/data/budget_nonlabor_items.php');
        $lineItemsTotal = 0;
        foreach ($nonLaborItems as $varName => $_) {
            $lineItemsTotal += (float) ($payload[$varName] ?? 0);
        }

        // Department fringe totals
        $fringeTotal = 0;
        $fringeKeys = [
            'FICAtotal_ATL', 'Medicaretotal_ATL', 'FUItotal_ATL', 'SUItotal_ATL', 'payrolltotal_ATL',
            '_210writerWGApension', '_210writerWGAhealth', 'SAGpensiontotal_cast',
            '_410directorDGApension', '_410directorDGAhealth',
            'FICAtotal_prod_BTL', 'Medicaretotal_prod_BTL', 'FUItotal_prod_BTL',
            'SUItotal_prod_BTL', 'payrolltotal_prod_BTL',
            'DGApensiontotal_prod_BTL', 'DGAhealthtotal_prod_BTL',
            'IATSEgrandtotal_prod_BTL', 'Teamstersgrandtotal_prod_BTL',
            'FICAtotal_post_BTL', 'Medicaretotal_post_BTL', 'FUItotal_post_BTL',
            'SUItotal_post_BTL', 'payrolltotal_post_BTL',
            'IATSEgrandtotal_post_BTL',
        ];
        foreach ($fringeKeys as $key) {
            $fringeTotal += (float) ($payload[$key] ?? 0);
        }

        // Writer, producer, cast labor — these go into the template's H column
        // via section subtotal formulas. The grand total at H1040 chains through
        // H1037 → H1034+H1035 → SUM(G...). The G column picks up the per-phase
        // rate values for crew positions, and the section subtotals in H pick up
        // writer/producer/cast. So we must include them for the surplus to be
        // correct (budget = all_labor + fringes + non_labor + contingency + surplus).
        $atlLabor = 0;
        $atlLabor += (float) ($payload['_210writerlabortotal'] ?? 0);
        $atlLabor += (float) ($payload['_310producerslabortotal'] ?? 0);
        for ($i = 1; $i <= 25; $i++) {
            $prefix = '_' . (510 + ($i * 2)) . 'cast' . str_pad($i, 2, '0', STR_PAD_LEFT);
            $atlLabor += (float) ($payload[$prefix . 'labortotal'] ?? 0);
        }

        $total = $crewLabor + $atlLabor + $lineItemsTotal + $fringeTotal;

        $payload['presurplus_total_FINAL'] = $total;

        return $total;
    }

    private function calculateCastMembers(
        array &$payload, array $input,
        BudgetClassResolver $resolver, FringeCalculator $fringeCalc
    ): void {
        $castSize = (int) ($input['usercastsize'] ?? 0);
        $sagRate = $resolver->rateSAG;
        $nonUnionKey = $resolver->rateNonunionKey;
        $sagCode = $resolver->guildCodeSAG;
        $bc = $resolver->getBudgetClass();
        $weeksSHOOT = $resolver->weeksSHOOT;

        for ($i = 1; $i <= 25; $i++) {
            $num = str_pad($i, 2, '0', STR_PAD_LEFT);
            $lineId = 510 + ($i * 2);
            $prefix = '_' . $lineId . 'cast' . $num;

            if ($i > $castSize) {
                $payload[$prefix . 'weeksshoot'] = '';
                $payload[$prefix . 'rate'] = '';
                $payload[$prefix . 'labortotal'] = '';
                $payload[$prefix . 'SAGpension'] = '';
                $payload[$prefix . 'FICA'] = '';
                $payload[$prefix . 'Medicare'] = '';
                $payload[$prefix . 'FUI'] = '';
                $payload[$prefix . 'SUI'] = '';
                $payload[$prefix . 'payroll'] = '';
                continue;
            }

            $rate = ($sagCode == 0 || $sagCode == 999) ? $nonUnionKey : $sagRate;
            $weeks = $weeksSHOOT;
            $labor = $weeks * $rate;

            $payload[$prefix . 'weeksshoot'] = $weeks;
            $payload[$prefix . 'rate'] = $rate;
            $payload[$prefix . 'labortotal'] = $labor;

            $sagFringe = ($sagCode != 0 && $sagCode != 999)
                ? $fringeCalc->calculateSAGFringes($labor, $sagCode)['sag'] : 0;
            $payload[$prefix . 'SAGpension'] = $sagFringe;

            $baseFringes = $fringeCalc->calculateFringes($labor, 'SAG', $sagCode, $weeks);
            $payload[$prefix . 'FICA'] = $baseFringes['fica'];
            $payload[$prefix . 'Medicare'] = $baseFringes['medicare'];
            $payload[$prefix . 'FUI'] = $baseFringes['fui'];
            $payload[$prefix . 'SUI'] = $baseFringes['sui'];
            $payload[$prefix . 'payroll'] = $baseFringes['payroll'];
        }
    }

    private function calculateWriterProducer(
        array &$payload, array $input,
        BudgetClassResolver $resolver, CrewRateCalculator $crewCalc,
        FringeCalculator $fringeCalc, $positions
    ): void {
        $bc = $resolver->getBudgetClass();
        $budget = $resolver->getBudget();

        // Writer (210) — flat WGA fee
        $writerPos = $positions->firstWhere('line_item_id', '210');
        if ($writerPos) {
            $wgaCode = $resolver->guildCodeWGA;
            $nonWgaAmount = $budget * (DepartmentAllocation::where('department_slug', 'writer')
                ->where('budget_class', $bc)->value('percentage') ?? 0);

            $pubFee = match ($wgaCode) {
                202 => 6250, 203, 299 => 12500, default => 0,
            };

            $writerFee = $crewCalc->wgaWriterFee($wgaCode, $nonWgaAmount, $pubFee, $writerPos);
            $payload['_210writerrate'] = $writerFee;
            $payload['_210writerlabortotal'] = $writerFee;

            $wFringes = $fringeCalc->calculateFringes($writerFee, 'WGA', $wgaCode);
            $payload['_210writerFICA'] = $wFringes['fica'];
            $payload['_210writerMedicare'] = $wFringes['medicare'];
            $payload['_210writerFUI'] = $wFringes['fui'];
            $payload['_210writerSUI'] = $wFringes['sui'];
            $payload['_210writerpayroll'] = $wFringes['payroll'];
            $payload['_210writerWGApension'] = $wFringes['wga_pension'];
            $payload['_210writerWGAhealth'] = $wFringes['wga_health'];

            // Writer is excluded from the main crew loop, so add fringes to ATL totals here.
            // Matches JS: FICAtotal_ATL = _210writerFICA + _410directorFICA + FICAtotal_cast
            $payload['FICAtotal_ATL'] = ($payload['FICAtotal_ATL'] ?? 0) + $wFringes['fica'];
            $payload['Medicaretotal_ATL'] = ($payload['Medicaretotal_ATL'] ?? 0) + $wFringes['medicare'];
            $payload['FUItotal_ATL'] = ($payload['FUItotal_ATL'] ?? 0) + $wFringes['fui'];
            $payload['SUItotal_ATL'] = ($payload['SUItotal_ATL'] ?? 0) + $wFringes['sui'];
            $payload['payrolltotal_ATL'] = ($payload['payrolltotal_ATL'] ?? 0) + $wFringes['payroll'];

            $payload['text_writeroriginalscreenplay'] = ($wgaCode != 0 && $wgaCode != 999)
                ? 'Original Screenplay incl. Treatment' : 'Writer(s)';
            $payload['text_scriptpublicationfee'] = $pubFee > 0
                ? '$' . number_format($pubFee, 2) : '';
        }

        // Producer (310) — allocation-based
        $producerAlloc = $budget * (DepartmentAllocation::where('department_slug', 'producers')
            ->where('budget_class', $bc)->value('percentage') ?? 0);
        $payload['_310producersrate'] = $producerAlloc;
        $payload['_310producerslabortotal'] = $producerAlloc;
    }

    private function calculateNonLaborItems(
        array &$payload, array $positionResults,
        float $budget, int $budgetClass
    ): void {
        $nonLaborItems = require database_path('seeders/data/budget_nonlabor_items.php');

        // Compute allocafterlabor for each department:
        // allocafterlabor = max(0, department_allocation - (labor + fringes))
        $deptLaborAndFringes = [];
        foreach ($positionResults as $data) {
            $dept = $data['position']->department;
            $labor = $data['result']['labor_total'];
            $fringeTotal = $data['fringes']['fringe_total'] ?? 0;
            $deptLaborAndFringes[$dept] = ($deptLaborAndFringes[$dept] ?? 0) + $labor + $fringeTotal;
        }

        // Add cast labor+fringes to 'cast' department
        for ($i = 1; $i <= 25; $i++) {
            $num = str_pad($i, 2, '0', STR_PAD_LEFT);
            $lineId = 510 + ($i * 2);
            $prefix = '_' . $lineId . 'cast' . $num;
            $castLabor = (float) ($payload[$prefix . 'labortotal'] ?? 0);
            $castFringes = (float) ($payload[$prefix . 'SAGpension'] ?? 0)
                + (float) ($payload[$prefix . 'FICA'] ?? 0)
                + (float) ($payload[$prefix . 'Medicare'] ?? 0)
                + (float) ($payload[$prefix . 'FUI'] ?? 0)
                + (float) ($payload[$prefix . 'SUI'] ?? 0)
                + (float) ($payload[$prefix . 'payroll'] ?? 0);
            $deptLaborAndFringes['cast'] = ($deptLaborAndFringes['cast'] ?? 0) + $castLabor + $castFringes;
        }

        // Add writer to 'writing', producer to 'producers' (ATL departments)
        $writerTotal = (float) ($payload['_210writerlabortotal'] ?? 0)
            + (float) ($payload['_210writerFICA'] ?? 0) + (float) ($payload['_210writerMedicare'] ?? 0)
            + (float) ($payload['_210writerFUI'] ?? 0) + (float) ($payload['_210writerSUI'] ?? 0)
            + (float) ($payload['_210writerpayroll'] ?? 0)
            + (float) ($payload['_210writerWGApension'] ?? 0) + (float) ($payload['_210writerWGAhealth'] ?? 0);
        $deptLaborAndFringes['writing'] = ($deptLaborAndFringes['writing'] ?? 0) + $writerTotal;
        $deptLaborAndFringes['producers'] = ($deptLaborAndFringes['producers'] ?? 0) + (float) ($payload['_310producerslabortotal'] ?? 0);

        // Map: crew department slug → [JS alloc name, DB department_allocations slug]
        // JS uses names like "allocafterlabor_prod" while DB uses "production"
        $deptAllocMap = [
            'cast'             => ['cast', 'cast'],
            'production'       => ['prod', 'production'],
            'camera'           => ['camera', 'camera'],
            'second_unit'      => ['secondunit', 'second_unit'],
            'production_sound' => ['prodsound', 'production_sound'],
            'grip'             => ['grip', 'grip'],
            'electric'         => ['electric', 'electric'],
            'location'         => ['location', 'location'],
            'transportation'   => ['transportation', 'transportation'],
            'art'              => ['art', 'art'],
            'construction'     => ['setconstruction', 'set_construction'],
            'set_dressing'     => ['setdressing', 'set_dressing'],
            'property'         => ['property', 'property'],
            'wardrobe'         => ['wardrobe', 'wardrobe'],
            'hair_makeup'      => ['makeuphair', 'hair_makeup'],
            'post_production'  => ['editing', 'editing'],
            'post_sound'       => ['postsound', 'post_sound'],
        ];

        $allocAfterLabor = [];
        $allocations = DepartmentAllocation::where('budget_class', $budgetClass)->get()->keyBy('department_slug');

        foreach ($deptAllocMap as $dept => [$jsName, $dbSlug]) {
            $totalAlloc = $budget * (float) ($allocations[$dbSlug]->percentage ?? 0);
            $laborAndFringes = $deptLaborAndFringes[$dept] ?? 0;
            $allocAfterLabor['allocafterlabor_' . $jsName] = max(0, $totalAlloc - $laborAndFringes);
        }

        // Also add direct allocations using JS names (no underscores)
        $directAllocMap = [
            'development' => 'development', 'cast' => 'cast', 'production' => 'production',
            'camera' => 'camera', 'prodsound' => 'production_sound', 'grip' => 'grip',
            'electric' => 'electric', 'location' => 'location', 'transportation' => 'transportation',
            'art' => 'art', 'setconstruction' => 'set_construction', 'setdressing' => 'set_dressing',
            'property' => 'property', 'wardrobe' => 'wardrobe', 'makeuphair' => 'hair_makeup',
            'editing' => 'editing', 'postsound' => 'post_sound', 'music' => 'music',
            'backgroundtalent' => 'background_talent', 'publicity' => 'publicity',
            'insurance' => 'insurance', 'otherexpenses' => 'other_expenses',
            'secondunit' => 'second_unit', 'writer' => 'writer', 'producers' => 'producers',
            'director' => 'director',
        ];
        foreach ($directAllocMap as $jsName => $dbSlug) {
            $pct = (float) ($allocations[$dbSlug]->percentage ?? 0);
            $allocAfterLabor['alloc' . $jsName] = $budget * $pct;
        }

        // Calculate each non-labor line item
        foreach ($nonLaborItems as $varName => [$allocSource, $multipliers]) {
            $mult = $multipliers[$budgetClass] ?? 0;
            $sourceAmount = $allocAfterLabor[$allocSource] ?? 0;
            $payload[$varName] = $mult * $sourceAmount;
        }
    }

    private function calculateTextVariables(array &$payload, BudgetClassResolver $resolver): void
    {
        $dgaDirCode = $resolver->guildCodeDGADIR;
        $payload['text_410directorrateflat'] = ($dgaDirCode == 303) ? 'Flat Rate:' : '';
        $payload['_410directorrateflat'] = ($dgaDirCode == 303) ? 75000 : '';

        $payload['text_taxincentive'] = $payload['text_taxincentive'] ?? '';
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
            } elseif ($guild === 'DGA_UPM') {
                // JS only includes UPM/ADs in DGApensiontotal_prod_BTL.
                // Director DGA fringes are output as separate per-position tokens.
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
