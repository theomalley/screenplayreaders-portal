<?php

// v1.0 — 2026-06-21 | Initial: budget percentage allocations per department per budget class

namespace App\Models\Budget;

use Illuminate\Database\Eloquent\Model;

class DepartmentAllocation extends Model
{
    protected $table = 'budget_department_allocations';

    protected $fillable = [
        'department_slug',
        'budget_class',
        'percentage',
    ];

    protected function casts(): array
    {
        return [
            'budget_class' => 'integer',
            'percentage' => 'decimal:6',
        ];
    }
}
