<?php

// v1.0 — 2026-06-21 | Initial: payroll tax and union fringe benefit rates

namespace App\Models\Budget;

use Illuminate\Database\Eloquent\Model;

class FringeRate extends Model
{
    protected $table = 'budget_fringe_rates';

    protected $fillable = [
        'slug',
        'name',
        'rate',
        'ceiling',
        'hourly_addon',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:6',
            'ceiling' => 'decimal:2',
            'hourly_addon' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }
}
