<?php

// v1.0 — 2026-07-20 | Directional matrix: from_tier_id readers can view/accept to_tier_id's
// assignment pool. Managed entirely via Tools > Settings > Tiers (App\Http\Controllers\TierController).

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TierCrossVisibility extends Model
{
    protected $table = 'tier_cross_visibility';

    protected $fillable = [
        'from_tier_id',
        'to_tier_id',
        'can_view',
        'can_accept',
    ];

    protected function casts(): array
    {
        return [
            'can_view'   => 'boolean',
            'can_accept' => 'boolean',
        ];
    }

    public function fromTier(): BelongsTo
    {
        return $this->belongsTo(Tier::class, 'from_tier_id');
    }

    public function toTier(): BelongsTo
    {
        return $this->belongsTo(Tier::class, 'to_tier_id');
    }
}
