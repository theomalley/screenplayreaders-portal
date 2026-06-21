<?php

// v1.0 — 2026-06-21 | Initial: union/non-union rate tiers per crew position

namespace App\Models\Budget;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RateTier extends Model
{
    protected $table = 'budget_rate_tiers';

    protected $fillable = [
        'crew_position_id',
        'tier_code',
        'rate_type',
        'rate_value',
        'add_pub_fee',
    ];

    protected function casts(): array
    {
        return [
            'tier_code' => 'integer',
            'rate_value' => 'decimal:2',
            'add_pub_fee' => 'boolean',
        ];
    }

    public function crewPosition(): BelongsTo
    {
        return $this->belongsTo(CrewPosition::class, 'crew_position_id');
    }
}
