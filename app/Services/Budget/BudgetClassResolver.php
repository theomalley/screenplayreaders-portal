<?php

// v1.0 — 2026-06-21 | Initial: determines budget class, guild codes, non-union rates, schedule weeks
// Ported from step-02-budget-calculations.js lines 1062-2323

namespace App\Services\Budget;

use App\Models\Budget\StateRate;

class BudgetClassResolver
{
    private float $budget;
    private int $budgetClass;

    public int $guildCodeSAG = 0;
    public int $guildCodeWGA = 0;
    public int $guildCodeDGADIR = 0;
    public int $guildCodeDGAUPM = 0;
    public int $guildCodeIATSE = 0;
    public int $guildCodeTEAMSTERS = 0;

    public float $weeksPREP = 0;
    public float $weeksSHOOT = 0;
    public float $weeksWRAP = 0;
    public float $weeksPOST = 0;

    public float $minimumWage = 15.50;
    public float $minimumWageWeekly = 620;
    public float $workWeekHours = 40;

    public float $rateNonunionKey = 0;
    public float $rateNonunion2nd = 0;
    public float $rateNonunion3rd = 0;
    public float $rateNonunionAsst = 0;
    public float $rateSAG = 0;

    public string $textStipendWarning = ' ';

    public string $guildCodeWGAText = 'Non-WGA';
    public string $guildCodeSAGText = 'Non-SAG';
    public string $guildCodeDGADIRText = 'Non-DGA Director';
    public string $guildCodeDGAUPMText = 'Non-DGA';
    public string $guildCodeIATSEText = 'Non-IATSE';
    public string $guildCodeTEAMSTERSText = 'Non-Teamsters';

    private const BUDGET_CLASS_RANGES = [
        ['min' => 25000,    'max' => 49999.99,    'cls' => 1],
        ['min' => 50000,    'max' => 199999.99,   'cls' => 2],
        ['min' => 200000,   'max' => 499999.99,   'cls' => 3],
        ['min' => 500000,   'max' => 1999999.99,  'cls' => 4],
        ['min' => 2000000,  'max' => 3499999.99,  'cls' => 5],
        ['min' => 3500000,  'max' => 10999999.99, 'cls' => 6],
        ['min' => 11000000, 'max' => 24999999.99, 'cls' => 7],
        ['min' => 25000000, 'max' => 250000000,   'cls' => 8],
    ];

    private const SAG_RATES = [
        'short' => 1245, 'ulow' => 1245, 'mlow' => 1514,
        'low' => 2812, 'full' => 4326,
    ];

    private const NONUNION_RATE_MODIFIERS = [
        ['min' => 10000,   'max' => 14999.99,    'mod' => 0.1],
        ['min' => 15000,   'max' => 19999.99,    'mod' => 0.125],
        ['min' => 20000,   'max' => 24999.99,    'mod' => 0.15],
        ['min' => 25000,   'max' => 29999.99,    'mod' => 0.175],
        ['min' => 30000,   'max' => 34999.99,    'mod' => 0.2],
        ['min' => 35000,   'max' => 39999.99,    'mod' => 0.225],
        ['min' => 40000,   'max' => 44999.99,    'mod' => 0.25],
        ['min' => 45000,   'max' => 49999.99,    'mod' => 0.275],
        ['min' => 50000,   'max' => 59999.99,    'mod' => 0.3],
        ['min' => 60000,   'max' => 69999.99,    'mod' => 0.325],
        ['min' => 70000,   'max' => 79999.99,    'mod' => 0.375],
        ['min' => 80000,   'max' => 89999.99,    'mod' => 0.4],
        ['min' => 90000,   'max' => 99999.99,    'mod' => 0.45],
        ['min' => 100000,  'max' => 124999.99,   'mod' => 0.5],
        ['min' => 125000,  'max' => 149999.99,   'mod' => 0.55],
        ['min' => 150000,  'max' => 8499999.99,  'mod' => 0.6],
        ['min' => 8500000, 'max' => 250000000,   'mod' => 0.62],
    ];

    private const WEEKS_CONFIG = [
        'prep'  => [
            'defaults' => [1 => 0, 2 => 0, 3 => 0, 4 => 1, 5 => 2, 6 => 3, 7 => 6, 8 => 12],
            'min'      => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 1, 7 => 1, 8 => 4],
            'max'      => [1 => 0, 2 => 0.5, 3 => 1, 4 => 2, 5 => 3, 6 => 4, 7 => 12, 8 => 18],
        ],
        'shoot' => [
            'defaults' => [1 => 2, 2 => 3, 3 => 4, 4 => 4, 5 => 5, 6 => 6, 7 => 8, 8 => 16],
            'min'      => [1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 1, 6 => 1, 7 => 1, 8 => 4],
            'max'      => [1 => 3, 2 => 4, 3 => 4, 4 => 5, 5 => 6, 6 => 8, 7 => 10, 8 => 18],
        ],
        'wrap'  => [
            'defaults' => [1 => 0, 2 => 0, 3 => 0, 4 => 0.5, 5 => 0.5, 6 => 1, 7 => 2, 8 => 4],
            'min'      => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0, 7 => 1, 8 => 2],
            'max'      => [1 => 0, 2 => 0, 3 => 0, 4 => 0.5, 5 => 1, 6 => 3, 7 => 3, 8 => 6],
        ],
        'post'  => [
            'defaults' => [1 => 0, 2 => 0, 3 => 8, 4 => 8, 5 => 8, 6 => 12, 7 => 12, 8 => 12],
            'min'      => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0, 7 => 2, 8 => 4],
            'max'      => [1 => 0, 2 => 2, 3 => 8, 4 => 10, 5 => 10, 6 => 12, 7 => 12, 8 => 18],
        ],
    ];

    private const GUILD_TEXT = [
        'WGA' => [
            200 => 'WGA Low-Budget Agreement (Up to $200K)',
            201 => 'WGA Low-Budget Agreement ($200k-$500k)',
            202 => 'WGA Low-Budget Agreement ($500k-$1.2M)',
            203 => 'WGA Theatrical LOW ($1.2M-$5M)',
            299 => 'WGA Theatrical HIGH ($5M and up)',
        ],
        'SAG' => [
            100 => 'SAG Student Film Agreement (Pay may be deferred)',
            101 => 'SAG Short Film Agreement',
            102 => 'SAG Ultra Low-Budget',
            103 => 'SAG Moderate Low-Budget',
            104 => 'SAG Low-Budget',
            199 => 'SAG Theatrical',
        ],
        'DGA_DIR' => [
            301 => 'DGA Director Rate 1B', 302 => 'DGA Director Rate 2',
            303 => 'DGA Director Rate 3 (Minimum $75,000)',
            304 => 'DGA Director Rate 4A', 305 => 'DGA Director Rate 4B',
            306 => 'DGA Director Rate 4C', 399 => 'DGA Director Full Rate',
        ],
        'DGA_UPM' => [
            400 => 'DGA AD-UPM Rate 1A', 401 => 'DGA AD-UPM Rate 1B',
            402 => 'DGA AD-UPM Rate 2', 403 => 'DGA AD-UPM Rate 3',
            404 => 'DGA AD-UPM Rate 4A', 405 => 'DGA AD-UPM Rate 4B',
            406 => 'DGA AD-UPM Rate 4C', 499 => 'DGA AD-UPM Full Rate',
        ],
        'IATSE' => [
            500 => 'IATSE Ultra-Low (under $3M)',
            501 => 'IATSE Low Budget Tier 1A ($3M-$6.25M)',
            502 => 'IATSE Low Budget Tier 1B ($6.25M-$9M)',
            503 => 'IATSE Low Budget Tier 2 ($9M-$12.5M)',
            504 => 'IATSE Low Budget Tier 3 ($12.5M-$15M)',
            599 => 'IATSE Theatrical (over $15M)',
        ],
    ];

    public function resolve(array $input): self
    {
        $this->budget = (float) ($input['budget'] ?? 0);
        $this->budgetClass = $this->determineBudgetClass();

        $this->resolveMinimumWage($input['shootingstate'] ?? '0');
        $this->resolveWeeks($input, (int) ($input['userusetimedefaults'] ?? 1) === 1);
        $this->resolveGuildCodes($input);
        $this->resolveGuildTexts();
        $this->resolveNonUnionRates();
        $this->resolveSAGRate();

        if ($this->rateNonunionKey < $this->minimumWageWeekly) {
            $this->textStipendWarning = 'Any labor rates listed that are less than minimum wage should be considered stipends only.';
        }

        return $this;
    }

    public function getBudget(): float
    {
        return $this->budget;
    }

    public function getBudgetClass(): int
    {
        return $this->budgetClass;
    }

    private function determineBudgetClass(): int
    {
        foreach (self::BUDGET_CLASS_RANGES as $r) {
            if ($this->budget >= $r['min'] && $this->budget <= $r['max']) {
                return $r['cls'];
            }
        }
        return 1;
    }

    private function resolveMinimumWage(string $state): void
    {
        if ($state === '0' || $state === '') {
            $this->minimumWage = 15.50;
        } else {
            $stateRate = StateRate::where('state_name', $state)->first();
            $this->minimumWage = $stateRate ? (float) $stateRate->minimum_wage : 15.50;
        }
        $this->minimumWageWeekly = $this->minimumWage * $this->workWeekHours;
    }

    private function resolveWeeks(array $input, bool $useDefaults): void
    {
        $bc = $this->budgetClass;

        foreach (['prep', 'shoot', 'wrap', 'post'] as $phase) {
            $cfg = self::WEEKS_CONFIG[$phase];
            $userKey = 'userweeks' . $phase;
            $userValue = (float) ($input[$userKey] ?? 0);

            if ($useDefaults) {
                $weeks = $cfg['defaults'][$bc] ?? 0;
            } else {
                $weeks = max($cfg['min'][$bc] ?? 0, min($userValue, $cfg['max'][$bc] ?? 0));
            }

            $prop = 'weeks' . strtoupper($phase);
            $this->$prop = $weeks;
        }
    }

    private function resolveGuildCodes(array $input): void
    {
        $b = $this->budget;
        $bc = $this->budgetClass;

        $useSag = (int) ($input['usersag'] ?? 0);
        $useSagStudent = (int) ($input['usersagstudent'] ?? 0);
        $useSagShort = (int) ($input['usersagshort'] ?? 0);
        $useWga = (int) ($input['userwga'] ?? 0);
        $useDga = (int) ($input['userdga'] ?? 0);
        $useIatse = (int) ($input['useriatse'] ?? 0);
        $useTeamsters = (int) ($input['userteamsters'] ?? 0);

        // SAG — additive guildchecks (only one should be non-zero)
        $this->guildCodeSAG = 0;
        if ($useSagStudent == 1 && $b <= 35000 && $bc == 1) $this->guildCodeSAG = 100;
        elseif ($useSagShort == 1 && $b <= 50000 && $bc == 1) $this->guildCodeSAG = 101;
        elseif ($useSag == 1 && $b <= 300000 && $bc >= 1 && $bc <= 3) $this->guildCodeSAG = 102;
        elseif ($useSag == 1 && $b >= 300000.01 && $b <= 700000 && $bc >= 2 && $bc <= 4) $this->guildCodeSAG = 103;
        elseif ($useSag == 1 && $b >= 700000.01 && $b <= 2000000 && ($bc == 4 || $bc == 5)) $this->guildCodeSAG = 104;
        elseif ($useSag == 1 && $b >= 2000000.01 && $bc >= 5 && $bc <= 8) $this->guildCodeSAG = 199;

        // WGA
        $this->guildCodeWGA = 0;
        if ($useWga == 1) {
            if ($b <= 199999.99 && $bc >= 1 && $bc <= 3) $this->guildCodeWGA = 999;
            elseif ($b >= 200000 && $b <= 499999.99 && $bc >= 1 && $bc <= 3) $this->guildCodeWGA = 999;
            elseif ($b >= 500000 && $b <= 1199999.99) {
                if ($bc >= 1 && $bc <= 3) $this->guildCodeWGA = 999;
                elseif ($bc == 4 || $bc == 5) $this->guildCodeWGA = 202;
            } elseif ($b >= 1200000 && $b <= 4999999.99) {
                if ($bc >= 1 && $bc <= 3) $this->guildCodeWGA = 999;
                elseif ($bc >= 4 && $bc <= 6) $this->guildCodeWGA = 203;
            } elseif ($b >= 5000000) {
                if ($bc >= 1 && $bc <= 4) $this->guildCodeWGA = 999;
                elseif ($bc >= 6 && $bc <= 8) $this->guildCodeWGA = 299;
            }
        }

        // DGA Director
        $this->guildCodeDGADIR = 0;
        if ($useDga == 1) {
            if ($b <= 2600000) {
                if ($bc >= 1 && $bc <= 4) $this->guildCodeDGADIR = 999;
                elseif ($bc == 5) $this->guildCodeDGADIR = 301;
                elseif ($bc == 6) $this->guildCodeDGADIR = 302;
            } elseif ($b <= 3750000) {
                if ($bc >= 1 && $bc <= 4) $this->guildCodeDGADIR = 999;
                elseif ($bc == 5 || $bc == 6) $this->guildCodeDGADIR = 303;
            } elseif ($b <= 5500000) {
                if ($bc >= 1 && $bc <= 4) $this->guildCodeDGADIR = 999;
                elseif ($bc == 6) $this->guildCodeDGADIR = 304;
            } elseif ($b <= 8500000) {
                if ($bc >= 1 && $bc <= 4) $this->guildCodeDGADIR = 999;
                elseif ($bc == 6) $this->guildCodeDGADIR = 305;
            } elseif ($b <= 11000000) {
                if ($bc >= 1 && $bc <= 4) $this->guildCodeDGADIR = 999;
                elseif ($bc == 6 || $bc == 7) $this->guildCodeDGADIR = 306;
            } else {
                if ($bc >= 1 && $bc <= 4) $this->guildCodeDGADIR = 999;
                elseif ($bc == 7 || $bc == 8) $this->guildCodeDGADIR = 399;
            }
        }

        // DGA UPM/ADs
        $this->guildCodeDGAUPM = 0;
        if ($useDga == 1) {
            if ($b <= 500000) {
                if ($bc >= 1 && $bc <= 4) $this->guildCodeDGAUPM = 999;
            } elseif ($b <= 1100000) {
                if ($bc >= 1 && $bc <= 4) $this->guildCodeDGAUPM = 999;
                elseif ($bc == 5) $this->guildCodeDGAUPM = 401;
            } elseif ($b <= 2600000) {
                if ($bc >= 1 && $bc <= 4) $this->guildCodeDGAUPM = 999;
                elseif ($bc == 5) $this->guildCodeDGAUPM = 402;
            } elseif ($b <= 3750000) {
                if ($bc >= 1 && $bc <= 4) $this->guildCodeDGAUPM = 999;
                elseif ($bc == 5 || $bc == 6) $this->guildCodeDGAUPM = 403;
            } elseif ($b <= 5500000) {
                if ($bc >= 1 && $bc <= 4) $this->guildCodeDGAUPM = 999;
                elseif ($bc == 6) $this->guildCodeDGAUPM = 404;
            } elseif ($b <= 8500000) {
                if ($bc >= 1 && $bc <= 4) $this->guildCodeDGAUPM = 999;
                elseif ($bc == 6) $this->guildCodeDGAUPM = 405;
            } elseif ($b <= 11000000) {
                if ($bc >= 1 && $bc <= 4) $this->guildCodeDGAUPM = 999;
                elseif ($bc == 6 || $bc == 7) $this->guildCodeDGAUPM = 406;
            } else {
                if ($bc >= 1 && $bc <= 4) $this->guildCodeDGAUPM = 999;
                elseif ($bc == 7 || $bc == 8) $this->guildCodeDGAUPM = 499;
            }
        }

        // IATSE
        $this->guildCodeIATSE = 0;
        if ($useIatse == 1) {
            if ($b <= 3000000) {
                if ($bc >= 1 && $bc <= 3) $this->guildCodeIATSE = 999;
                elseif ($bc == 4 || $bc == 5) $this->guildCodeIATSE = 500;
            } elseif ($b <= 6250000) {
                if ($bc >= 1 && $bc <= 4) $this->guildCodeIATSE = 999;
                elseif ($bc == 5 || $bc == 6) $this->guildCodeIATSE = 501;
            } elseif ($b <= 9000000) {
                if ($bc >= 1 && $bc <= 5) $this->guildCodeIATSE = 999;
                elseif ($bc == 6) $this->guildCodeIATSE = 502;
            } elseif ($b <= 12500000) {
                if ($bc >= 1 && $bc <= 5) $this->guildCodeIATSE = 999;
                elseif ($bc == 6) $this->guildCodeIATSE = 503;
            } elseif ($b <= 15000000) {
                if ($bc >= 1 && $bc <= 6) $this->guildCodeIATSE = 999;
                elseif ($bc == 7) $this->guildCodeIATSE = 504;
            } else {
                if ($bc >= 1 && $bc <= 6) $this->guildCodeIATSE = 999;
                elseif ($bc == 7 || $bc == 8) $this->guildCodeIATSE = 599;
            }
        }

        // Teamsters
        $this->guildCodeTEAMSTERS = 0;
        if ($useTeamsters == 1) {
            if ($bc >= 1 && $bc <= 5) $this->guildCodeTEAMSTERS = 999;
            elseif ($bc >= 6 && $bc <= 8) $this->guildCodeTEAMSTERS = 699;
        }
    }

    private function resolveGuildTexts(): void
    {
        $this->guildCodeWGAText = self::GUILD_TEXT['WGA'][$this->guildCodeWGA] ?? 'Non-WGA';
        $this->guildCodeSAGText = self::GUILD_TEXT['SAG'][$this->guildCodeSAG] ?? 'Non-SAG';
        $this->guildCodeDGADIRText = self::GUILD_TEXT['DGA_DIR'][$this->guildCodeDGADIR] ?? 'Non-DGA Director';
        $this->guildCodeDGAUPMText = self::GUILD_TEXT['DGA_UPM'][$this->guildCodeDGAUPM] ?? 'Non-DGA';
        $this->guildCodeIATSEText = self::GUILD_TEXT['IATSE'][$this->guildCodeIATSE] ?? 'Non-IATSE';
        $this->guildCodeTEAMSTERSText = $this->guildCodeTEAMSTERS == 699 ? 'Teamsters' : 'Non-Teamsters';
    }

    private function resolveNonUnionRates(): void
    {
        $b = $this->budget;
        $mww = $this->minimumWageWeekly;

        // SAG basis
        $sagBasis = match (true) {
            $b >= 10000 && $b <= 199999.99  => self::SAG_RATES['ulow'],
            $b >= 200000 && $b <= 749999.99 => self::SAG_RATES['mlow'],
            $b >= 750000 && $b <= 2499999.99 => self::SAG_RATES['low'],
            $b >= 2500000 => self::SAG_RATES['full'],
            default => 0,
        };

        // UPM basis
        $upmBasis = match (true) {
            $b >= 10000 && $b <= 499999.99    => $mww,
            $b >= 500000 && $b <= 1099999.99  => 2153,
            $b >= 1100000 && $b <= 2599999.99 => 3262,
            $b >= 2600000 && $b <= 3749999.99 => 3914,
            $b >= 3750000 && $b <= 5499999.99 => 6393,
            $b >= 5500000 && $b <= 8499999.99 => 7306,
            $b >= 8500000 && $b <= 10999999.99 => 8220,
            $b >= 11000000 => 8698,
            default => 0,
        };

        // DP basis
        $dpBasis = match (true) {
            $b >= 10000 && $b <= 5499999.99    => 2244,
            $b >= 5500000 && $b <= 8499999.99  => 2800,
            $b >= 8500000 && $b <= 10999999.99 => 3200,
            $b >= 11000000 => 4771.44,
            default => 0,
        };

        $nonunionBasis = ($sagBasis + $upmBasis + $dpBasis) / 3;

        // Rate modifier
        $modifier = 0;
        foreach (self::NONUNION_RATE_MODIFIERS as $r) {
            if ($b >= $r['min'] && $b <= $r['max']) {
                $modifier = $r['mod'];
                break;
            }
        }

        $this->rateNonunionKey = $nonunionBasis * $modifier;
        $this->rateNonunion2nd = $this->rateNonunionKey * 0.7;
        $this->rateNonunion3rd = $this->rateNonunionKey * 0.6;
        $this->rateNonunionAsst = $this->rateNonunionKey * 0.4;
    }

    private function resolveSAGRate(): void
    {
        $this->rateSAG = match ($this->guildCodeSAG) {
            0 => $this->rateNonunionKey,
            100 => $this->minimumWage,
            101 => self::SAG_RATES['short'],
            102 => self::SAG_RATES['ulow'],
            103 => self::SAG_RATES['mlow'],
            104 => self::SAG_RATES['low'],
            199 => self::SAG_RATES['full'],
            default => 0,
        };
    }

    public function guildCodeForPosition(string $guild): int
    {
        return match ($guild) {
            'WGA' => $this->guildCodeWGA,
            'DGA_DIR' => $this->guildCodeDGADIR,
            'DGA_UPM' => $this->guildCodeDGAUPM,
            'IATSE' => $this->guildCodeIATSE,
            'TEAMSTERS' => $this->guildCodeTEAMSTERS,
            default => 0,
        };
    }
}
