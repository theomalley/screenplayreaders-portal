<?php

// v1.0 — 2026-06-21 | Initial: maps guild + budget class to the rate tier code used

namespace App\Models\Budget;

use Illuminate\Database\Eloquent\Model;

class GuildTierMapping extends Model
{
    protected $table = 'budget_guild_tier_mappings';

    protected $fillable = [
        'guild',
        'budget_class',
        'tier_code',
    ];

    protected function casts(): array
    {
        return [
            'budget_class' => 'integer',
            'tier_code' => 'integer',
        ];
    }
}
