<?php

// v1.0 — 2026-06-21 | Initial: per-state SUI rates, wage ceilings, minimum wage, tax incentives

namespace App\Models\Budget;

use Illuminate\Database\Eloquent\Model;

class StateRate extends Model
{
    protected $table = 'budget_state_rates';

    protected $fillable = [
        'state_name',
        'sui_rate',
        'sui_ceiling',
        'minimum_wage',
        'tax_incentive_text',
    ];

    protected function casts(): array
    {
        return [
            'sui_rate' => 'decimal:6',
            'sui_ceiling' => 'decimal:2',
            'minimum_wage' => 'decimal:2',
        ];
    }
}
