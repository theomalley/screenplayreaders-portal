<?php

// v1.2 — 2026-11-21 | effectiveBaseRate() switched from "whichever is higher" (max of the flat
//                      base_rate or base_rate_percent of the reader's rate) to additive (both
//                      always apply together) — the max() version let SR's cut go flat for a
//                      whole band of reader rates (from the floor up to the point where the
//                      percent caught up), during which card-processing + editor-commission
//                      costs kept rising with the (also-rising) customer price while SR's own
//                      take didn't, so net profit actually DECLINED as reader rate rose through
//                      that band before recovering past the old breakeven point. Additive keeps
//                      the percent component always scaling, so profit now rises monotonically
//                      with reader rate everywhere (as long as base_rate_percent stays above
//                      ~10.4%, the rate at which it exactly offsets 2.9%+6.5% downstream costs).
// v1.1 — 2026-11-20 | Added base_rate_percent + effectiveBaseRate() — the company's cut can now
//                      scale with a reader's own rate instead of staying a flat dollar amount,
//                      since reader rates can go as high as $5000. base_rate is now the floor.
// v1.0 — 2026-11-07 | Video consulting Phase 1 — admin-creatable call-length options.

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VideoConsultingDuration extends Model
{
    protected $fillable = [
        'minutes',
        'label',
        'base_rate',
        'base_rate_percent',
        'min_reader_rate',
        'max_reader_rate',
        'enabled',
        'sort_order',
    ];

    protected $casts = [
        'base_rate'         => 'decimal:2',
        'base_rate_percent' => 'decimal:2',
        'min_reader_rate'   => 'decimal:2',
        'max_reader_rate'   => 'decimal:2',
        'enabled'           => 'boolean',
    ];

    /**
     * The company's actual cut for a given reader rate — the flat base_rate PLUS base_rate_percent
     * of the reader's own rate, both always applied together (not whichever is larger). Rounded
     * to whole dollars for clean, reconcilable numbers.
     */
    public function effectiveBaseRate(float $readerRate): float
    {
        return (float) $this->base_rate + round(((float) $this->base_rate_percent / 100) * $readerRate);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('enabled', true)->orderBy('sort_order');
    }

    public function rates(): HasMany
    {
        return $this->hasMany(ReaderVideoConsultingRate::class, 'duration_id');
    }

    public function holds(): HasMany
    {
        return $this->hasMany(VideoConsultingHold::class, 'duration_id');
    }

    /**
     * Soft-disable only, never hard-delete, once this duration has any booking history —
     * the restrict-on-delete FKs on reader_video_consulting_rates/video_consulting_holds
     * already enforce this at the DB layer; this is the app-level check used before even
     * attempting the delete, so the admin gets a clear 422 instead of a DB error.
     */
    public function canBeDeleted(): bool
    {
        return ! $this->rates()->exists() && ! $this->holds()->exists();
    }
}
