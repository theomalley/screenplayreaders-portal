<?php

// v1.8 — 2026-06-12 | Add COMPLETION_DRAFT_DEFAULT and getCompletionDraftBody() for admin-editable QC completion email
// v1.7 — 2026-06-10 | Add WATERMARK_DEFAULTS and getWatermarkSettings() for admin-configurable reader download watermark
// v1.6 — 2026-06-07 | Add PAY_PERIOD_DEFAULTS and getPayPeriod() for explicit period start/end configuration
// v1.5 — 2026-06-03 | Add WORD_COUNT_DEFAULTS and getWordCounts() for admin-configurable coverage word count minimums
// v1.4 — 2026-06-02 | Add PAYOUT_SCHEDULE_DEFAULTS and getPayoutSchedule() for admin-configurable payout schedule
// v1.3 — 2026-05-30 | Add EMAIL_NOTIFICATION_DEFAULTS and getEmailNotificationTexts()
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

    public const EMAIL_NOTIFICATION_DEFAULTS = [
        'email_notif_subject_new'         => 'New Assignment Available',
        'email_notif_subject_rush'        => 'Rush Assignment Available',
        'email_notif_subject_request'     => 'Reader Request',
        'email_notif_subject_rush_request'=> 'Rush Reader Request',
        'email_notif_header_new'          => 'New Assignment Available',
        'email_notif_header_rush'         => 'Rush Assignment Available',
        'email_notif_header_request'      => 'Reader Request',
        'email_notif_header_rush_request' => 'Rush Reader Request',
        'email_notif_body_new'            => 'This assignment has been added to the assignments list. First reader to accept it gets it.',
        'email_notif_body_request'        => 'You have been specifically requested for this assignment. Head to the portal to accept it -- it may be opened to other readers if not claimed promptly.',
    ];

    public static function getEmailNotificationTexts(): array
    {
        $keys   = array_keys(self::EMAIL_NOTIFICATION_DEFAULTS);
        $stored = static::whereIn('key', $keys)->pluck('value', 'key');

        $result = [];
        foreach (self::EMAIL_NOTIFICATION_DEFAULTS as $key => $default) {
            $result[$key] = $stored[$key] ?? $default;
        }

        return $result;
    }

    /** Payout schedule defaults. day: 0=Sun … 6=Sat. time: HH:MM (America/Los_Angeles). */
    public const PAYOUT_SCHEDULE_DEFAULTS = [
        'payout_frequency' => 'weekly',
        'payout_day'       => '6',
        'payout_time'      => '08:00',
    ];

    /**
     * Pay period window defaults. start = when a new period opens; end = when it closes.
     * Default: opens Saturday 8:00 AM, closes Saturday 7:00 AM (one hour before next opening).
     * day values: 0=Sun … 6=Sat. time: HH:MM (America/Los_Angeles).
     */
    public const PAY_PERIOD_DEFAULTS = [
        'period_start_day'  => '6',
        'period_start_time' => '08:00',
        'period_end_day'    => '6',
        'period_end_time'   => '07:00',
    ];

    /**
     * Returns the full payout schedule config, merging stored values with defaults.
     * Also returns override date and biweekly anchor (both nullable).
     */
    public static function getPayoutSchedule(): array
    {
        $keys   = array_keys(self::PAYOUT_SCHEDULE_DEFAULTS);
        $keys[] = 'payout_next_override';
        $keys[] = 'payout_biweekly_anchor';

        $stored = static::whereIn('key', $keys)->pluck('value', 'key');

        return [
            'frequency' => $stored['payout_frequency'] ?? self::PAYOUT_SCHEDULE_DEFAULTS['payout_frequency'],
            'day'       => (int) ($stored['payout_day'] ?? self::PAYOUT_SCHEDULE_DEFAULTS['payout_day']),
            'time'      => $stored['payout_time']      ?? self::PAYOUT_SCHEDULE_DEFAULTS['payout_time'],
            'override'  => ($stored['payout_next_override']  ?? '') ?: null,
            'anchor'    => ($stored['payout_biweekly_anchor'] ?? '') ?: null,
        ];
    }

    /**
     * Returns the pay period window config (start day/time, end day/time).
     * Merges stored DB values with PAY_PERIOD_DEFAULTS.
     */
    public static function getPayPeriod(): array
    {
        $keys   = array_keys(self::PAY_PERIOD_DEFAULTS);
        $stored = static::whereIn('key', $keys)->pluck('value', 'key');

        return [
            'start_day'  => (int) ($stored['period_start_day']  ?? self::PAY_PERIOD_DEFAULTS['period_start_day']),
            'start_time' => $stored['period_start_time'] ?? self::PAY_PERIOD_DEFAULTS['period_start_time'],
            'end_day'    => (int) ($stored['period_end_day']    ?? self::PAY_PERIOD_DEFAULTS['period_end_day']),
            'end_time'   => $stored['period_end_time']   ?? self::PAY_PERIOD_DEFAULTS['period_end_time'],
        ];
    }

    /** Default minimum word counts for coverage fields. 0 = no minimum. */
    public const WORD_COUNT_DEFAULTS = [
        'wc_enabled'                     => 1,
        // SR fields
        'wc_sr_logline'                  => 0,
        'wc_sr_synopsis'                 => 600,
        'wc_sr_notes_script_coverage'    => 1200,
        'wc_sr_notes_notes_only'         => 0,
        'wc_sr_notes_short'              => 600,
        'wc_sr_notes_deep_dive'          => 4100,
        'wc_sr_notes_budget'             => 150,
        'wc_sr_notes_book'               => 4100,
        // WD fields
        'wc_wd_logline'                  => 0,
        'wc_wd_synopsis'                 => 450,
        'wc_wd_notes_coverage'           => 1200,
        'wc_wd_notes_development_notes'  => 3700,
    ];

    /**
     * Returns all word count settings as integers, keyed by their DB key.
     * Falls back to WORD_COUNT_DEFAULTS for any missing row.
     */
    public static function getWordCounts(): array
    {
        $keys   = array_keys(self::WORD_COUNT_DEFAULTS);
        $stored = static::whereIn('key', $keys)->pluck('value', 'key');

        $result = [];
        foreach (self::WORD_COUNT_DEFAULTS as $key => $default) {
            $result[$key] = isset($stored[$key]) ? (int) $stored[$key] : (int) $default;
        }

        return $result;
    }

    /** Reader-download watermark: which fields appear, plus an optional custom text. */
    public const WATERMARK_DEFAULTS = [
        'watermark_show_name'     => 1,
        'watermark_show_order'    => 1,
        'watermark_show_datetime' => 1,
        'watermark_show_ref'      => 1,
        'watermark_custom_text'   => '',
    ];

    /**
     * Returns watermark field toggles (booleans) plus the custom text string.
     * Falls back to WATERMARK_DEFAULTS for any missing row.
     */
    public static function getWatermarkSettings(): array
    {
        $keys   = array_keys(self::WATERMARK_DEFAULTS);
        $stored = static::whereIn('key', $keys)->pluck('value', 'key');

        $result = [];
        foreach (self::WATERMARK_DEFAULTS as $key => $default) {
            $result[$key] = $key === 'watermark_custom_text'
                ? ($stored[$key] ?? $default)
                : (bool) ($stored[$key] ?? $default);
        }

        return $result;
    }

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

    /** HelpScout conversation ID used for "send test draft" — a sandbox ticket, never a real customer. */
    public const TEST_HELPSCOUT_CONVERSATION_ID = '3332476826';

    /** Default body for the completion draft sent to customers when coverage is approved. */
    public const COMPLETION_DRAFT_DEFAULT = <<<'HTML'
<p>Hi {%customer.firstName,fallback= %} —</p>
<p>Attached, please find your script coverage.</p>
<p>Thanks sincerely for the opportunity to read and provide feedback on your work. To ask followup questions of your reader, <a href="{{followup_url}}">click here</a>. We're here to help!</p>
<p>Thanks again, {%customer.firstName,fallback= %}.</p>
<p>P.S. If you feel we did a good job, could you take 30 seconds and give us a quick Google Review? We would super appreciate it!</p>
<p>P.P.S. Here's a discount code good for $10.00 off your next order. It's only good for 30 days from your order date but it can be stacked with most other discount codes we send if you're on our mailing list: (INSERT WOO COUPON CODE HERE)</p>
HTML;

    public static function getCompletionDraftBody(): string
    {
        return static::where('key', 'completion_draft_body')->value('value') ?: self::COMPLETION_DRAFT_DEFAULT;
    }

    public static function setCompletionDraftBody(string $body): void
    {
        static::updateOrCreate(['key' => 'completion_draft_body'], ['value' => $body]);
    }
}
