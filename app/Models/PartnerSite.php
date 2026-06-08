<?php

// v1.0 — 2026-06-08 | Partner backlink monitor — partner site config

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartnerSite extends Model
{
    protected $fillable = [
        'name', 'url', 'check_interval_minutes', 'active', 'notes', 'next_check_at',
    ];

    protected $casts = [
        'active'       => 'boolean',
        'next_check_at'=> 'datetime',
    ];

    public function checks(): HasMany
    {
        return $this->hasMany(PartnerLinkCheck::class);
    }

    public function latestCheck(): HasMany
    {
        return $this->hasMany(PartnerLinkCheck::class)->latestOfMany('checked_at');
    }

    /** Uptime % over the given Carbon date range (null = all time). */
    public function uptimePercent(?\Carbon\Carbon $start, ?\Carbon\Carbon $end): ?float
    {
        $query = $this->checks();

        if ($start) $query->where('checked_at', '>=', $start);
        if ($end)   $query->where('checked_at', '<=', $end);

        $total = $query->count();
        if ($total === 0) return null;

        $up = (clone $query)->where('is_up', true)->count();

        return round(($up / $total) * 100, 1);
    }

    /** True if this site is due for a check. */
    public function isDue(): bool
    {
        return $this->active && (is_null($this->next_check_at) || $this->next_check_at->isPast());
    }
}
