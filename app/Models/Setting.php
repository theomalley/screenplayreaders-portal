<?php

// v1.1 — 2026-05-27 | Add AGE_THRESHOLD_DEFAULTS and getAgeThresholds() for per-type age colours
// v1.0 — 2026-05-17 | Key/value settings store; ratesForForms() shapes rates for JS

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /** All rate keys with their hardcoded defaults (fallback if row is missing). */
    public const RATE_DEFAULTS = [
        'rate_sr_script_coverage'   => 70.00,
        'rate_sr_notes_only'        => 55.00,
        'rate_sr_short'             => 55.00,
        'rate_sr_deep_dive'         => 215.00,
        'rate_sr_budget'            => 55.00,
        'rate_sr_oversized_121_160' => 15.00,
        'rate_sr_rush'              => 50.00,
        'rate_sr_request'           => 40.00,
        'rate_sr_proofreading'      => 100.00,
        'rate_wd_coverage'          => 60.00,
        'rate_wd_development_notes' => 120.00,
        'rate_wd_oversized_121_160' => 15.00,
        'rate_wd_rush'              => 25.00,
        'rate_wd_request'           => 15.00,
        'rate_editor_commission'    => 10.00,
        'rate_editor_weekly_flat'   => 500.00,
    ];

    /**
     * Returns all rates as floats, keyed by their DB key (rate_sr_*, rate_wd_*).
     * Falls back to RATE_DEFAULTS for any missing row.
     */
    public static function ratesForForms(): array
    {
        $stored = static::whereIn('key', array_keys(self::RATE_DEFAULTS))
            ->pluck('value', 'key')
            ->toArray();

        $rates = [];
        foreach (self::RATE_DEFAULTS as $key => $default) {
            $rates[$key] = isset($stored[$key]) ? (float) $stored[$key] : $default;
        }

        return $rates;
    }

    /** Assignment types that have configurable age-colour thresholds. */
    public const AGE_THRESHOLD_TYPES = [
        'script_coverage' => 'Script Coverage',
        'notes_only'      => 'Notes-Only',
        'deep_dive'       => 'Deep-Dive',
        'short'           => 'Short',
        'budget'          => 'Budget',
        'formatting'      => 'Formatting',
        'proofreading'    => 'Proofreading',
    ];

    /** Default thresholds (days) — yellow / orange / red. */
    public const AGE_THRESHOLD_DEFAULTS = ['yellow' => 4, 'orange' => 8, 'red' => 14];

    /**
     * Returns age-colour thresholds keyed by assignment type.
     * Falls back to AGE_THRESHOLD_DEFAULTS for any missing setting.
     */
    public static function getAgeThresholds(): array
    {
        $keys = [];
        foreach (array_keys(self::AGE_THRESHOLD_TYPES) as $type) {
            foreach (['yellow', 'orange', 'red'] as $band) {
                $keys[] = "age_{$band}_{$type}";
            }
        }

        $stored = static::whereIn('key', $keys)->pluck('value', 'key');

        $result = [];
        foreach (array_keys(self::AGE_THRESHOLD_TYPES) as $type) {
            $result[$type] = [
                'yellow' => (int) ($stored["age_yellow_{$type}"] ?? self::AGE_THRESHOLD_DEFAULTS['yellow']),
                'orange' => (int) ($stored["age_orange_{$type}"] ?? self::AGE_THRESHOLD_DEFAULTS['orange']),
                'red'    => (int) ($stored["age_red_{$type}"]    ?? self::AGE_THRESHOLD_DEFAULTS['red']),
            ];
        }

        return $result;
    }

    public static function getValue(string $key, mixed $default = null): mixed
    {
        return static::where('key', $key)->value('value') ?? $default;
    }

    public static function setValue(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => (string) $value]);
    }
}
