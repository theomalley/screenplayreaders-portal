<?php

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
     * The company's actual cut for a given reader rate — base_rate acts as a floor; above that,
     * base_rate_percent of the reader's own rate wins once it's the larger of the two. Rounded
     * to whole dollars for clean, reconcilable numbers.
     */
    public function effectiveBaseRate(float $readerRate): float
    {
        return max((float) $this->base_rate, round(((float) $this->base_rate_percent / 100) * $readerRate));
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
