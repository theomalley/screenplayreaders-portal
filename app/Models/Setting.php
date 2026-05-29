<?php

// v1.2 — 2026-05-28 | Add getAppTimezone() for admin-configurable display timezone
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

    /** Default thresholds (hours) — yellow / orange / red. */
    public const AGE_THRESHOLD_DEFAULTS = ['yellow' => 96, 'orange' => 192, 'red' => 336];

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

    public static function getAppTimezone(): string
    {
        return static::where('key', 'app_timezone')->value('value') ?? 'UTC';
    }

    public static function getValue(string $key, mixed $default = null): mixed
    {
        return static::where('key', $key)->value('value') ?? $default;
    }

    public static function setValue(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => (string) $value]);
    }

    /** Default QC saved replies seeded from the 23 reader rules. */
    public const QC_SAVED_REPLY_DEFAULTS = [
        ['name' => 'Missing actionable feedback',  'body' => 'Your feedback needs to be more actionable, insightful, and constructive. Please ensure every note gives the writer something specific they can do to improve the script.'],
        ['name' => 'Skimming detected',             'body' => 'It appears the script may not have been read carefully. Please re-read the script in full before revising your coverage.'],
        ['name' => 'No examples or suggestions',   'body' => 'When you criticize an element of the script, please cite a specific example from the script to illustrate your point, and provide a specific, actionable suggestion on how to fix it.'],
        ['name' => 'Too much formatting talk',      'body' => 'You wrote too many words discussing the script\'s presentation, format, spelling, grammar, or punctuation. Mention it briefly, cite one or two examples with page numbers, and move on.'],
        ['name' => 'Missing positives at top',      'body' => 'Please open your feedback with at least three specific examples of where the script goes right. This helps the writer more readily process the criticism that follows.'],
        ['name' => 'Feedback not actionable',       'body' => 'Please make sure your feedback includes specific, actionable things the writer can do to fix the script.'],
        ['name' => 'Padding or filler',             'body' => 'Your coverage contains padding or filler — words and phrases that don\'t help the writer. Please trim unnecessary language and use contractions where natural (they\'re, it\'ll, there\'s, etc.).'],
        ['name' => 'Bias showing',                  'body' => 'Please avoid expressing political, cultural, or religious bias. If a story element is problematic, frame it as a strategic note — e.g., that some readers or gatekeepers may take offense — rather than as a personal judgment.'],
        ['name' => 'Fact-nitpicking',               'body' => 'Please avoid nitpicking on facts and trivia. Focus on the craft elements that affect the story.'],
        ['name' => 'Needs more creativity',         'body' => 'Please be more creative in your suggestions. Analysis can feel cold without a few imaginative alternatives for the writer to consider.'],
        ['name' => 'Could reference more films',    'body' => 'Feel free to reference other films or draw on your knowledge of cinema. Framing your expertise helps the client better understand your feedback.'],
        ['name' => 'Recycled text suspected',       'body' => 'Please do not reuse or repurpose any text from other notes or coverage, even if the feedback seems applicable to this script.'],
        ['name' => 'Too much writer address',       'body' => 'You addressed the writer a bit too directly or frequently. Keep encouragement and educational asides to a minimum, and focus on specific, actionable craft notes.'],
        ['name' => 'Unprofessional tone',           'body' => 'Please avoid sarcasm, condescension, or any non-professional tone throughout your coverage.'],
        ['name' => 'Plot recap in feedback',        'body' => 'The feedback section should not rehash the plot summary. Please focus on craft notes rather than retelling the story.'],
        ['name' => 'Bold assumptions',              'body' => 'Please avoid making assumptions or bold judgment calls about marketability or commercial prospects (e.g., "There\'s no way Netflix will buy this.").'],
        ['name' => 'Loaded language',               'body' => 'Please avoid emotionally loaded words and phrases (e.g., messy, convoluted, boring, terrible, fails, muddled, lame). Keep your language neutral and professional.'],
        ['name' => 'Spelling/grammar errors',       'body' => 'Please review and correct your spelling, grammar, usage, and punctuation — it should be 100% correct before resubmitting.'],
        ['name' => 'Run-on paragraphs',             'body' => 'Please break up your paragraphs and run-on sentences so the coverage is easier to read.'],
        ['name' => 'Missing paragraph breaks',      'body' => 'Please hit Enter twice between paragraphs when using the online Reader Assignment Form.'],
        ['name' => 'All-caps emphasis',             'body' => 'Please avoid using all caps to emphasize words (e.g., "RIDICULOUSLY unbelievable"). Use other phrasing instead.'],
        ['name' => 'Unhelpful content',             'body' => 'Please review your coverage with the client in mind. If you can\'t imagine a word, phrase, or line helping the writer, please modify or remove it.'],
        ['name' => 'AI usage suspected',            'body' => 'Please do not use AI or LLM tools to compose any part of your coverage, including synopsis, logline, comments, or notes.'],
    ];

    public static function getSavedReplies(): array
    {
        $raw = static::where('key', 'qc_saved_replies')->value('value');
        if ($raw) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && count($decoded) > 0) {
                return $decoded;
            }
        }
        return self::QC_SAVED_REPLY_DEFAULTS;
    }

    public static function setSavedReplies(array $replies): void
    {
        static::updateOrCreate(['key' => 'qc_saved_replies'], ['value' => json_encode(array_values($replies))]);
    }
}
