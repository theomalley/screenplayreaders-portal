<?php

// v1.0 — 2026-06-21 | Initial: crew positions with rate tiers and phase config

namespace App\Models\Budget;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrewPosition extends Model
{
    protected $table = 'budget_crew_positions';

    protected $fillable = [
        'line_item_id',
        'slug',
        'name',
        'department',
        'guild',
        'phase_config',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'phase_config' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function rateTiers(): HasMany
    {
        return $this->hasMany(RateTier::class, 'crew_position_id');
    }
}
