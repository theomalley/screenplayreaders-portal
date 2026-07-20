<?php

// v1.0 — 2026-07-20 | Dynamic reader tiers — replaces the hardcoded tier_0/tier_1/tier_2
// concept. See App\Support\TierAccess for the cross-visibility/escalation access logic and
// App\Console\Commands\EscalateTierTimeouts for the timeout->escalates_to_tier_id job.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tier extends Model
{
    protected $fillable = [
        'name',
        'position',
        'is_onboarding',
        'timeout_hours',
        'escalates_to_tier_id',
        'allowed_assignment_types',
    ];

    protected function casts(): array
    {
        return [
            'is_onboarding'             => 'boolean',
            'position'                  => 'integer',
            'timeout_hours'             => 'integer',
            'allowed_assignment_types'  => 'array',
        ];
    }

    public function assignments(): BelongsToMany
    {
        return $this->belongsToMany(Assignment::class, 'assignment_tier')->withTimestamps();
    }

    public function readerProfiles(): BelongsToMany
    {
        return $this->belongsToMany(ReaderProfile::class, 'reader_profile_tier')->withTimestamps();
    }

    public function escalatesTo(): BelongsTo
    {
        return $this->belongsTo(Tier::class, 'escalates_to_tier_id');
    }

    /** Cross-visibility rows where readers of this tier gain access into another tier. */
    public function crossVisibilityFrom(): HasMany
    {
        return $this->hasMany(TierCrossVisibility::class, 'from_tier_id');
    }

    /** Cross-visibility rows where another tier has been granted access into this tier. */
    public function crossVisibilityTo(): HasMany
    {
        return $this->hasMany(TierCrossVisibility::class, 'to_tier_id');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('position')->orderBy('id');
    }

    /** True if this tier has no assignment-type restriction, or $type is in its allowlist. */
    public function allowsType(?string $type): bool
    {
        return empty($this->allowed_assignment_types) || in_array($type, $this->allowed_assignment_types, true);
    }

    public static function onboarding(): ?self
    {
        return static::where('is_onboarding', true)->first();
    }
}
