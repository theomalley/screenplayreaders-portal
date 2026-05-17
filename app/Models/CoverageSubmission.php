<?php

// v1.1 — 2026-05-17 | Full fillable + casts from expanded schema

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoverageSubmission extends Model
{
    protected $fillable = [
        'assignment_id',
        'vendor',
        'writer_name', 'genre', 'time_period', 'locations', 'estimated_budget',
        'quality_checked',

        // SR metadata
        'sr_assignment_type', 'sr_number_of_readers', 'sr_reader_request',
        'sr_proofreading', 'sr_net15', 'sr_custom_oversized_fee', 'sr_book_pay_rate',

        // SR content
        'sr_logline', 'sr_synopsis', 'sr_notes',

        // SR scoresheet
        'sr_score_concept', 'sr_score_opening_pages', 'sr_score_theme',
        'sr_score_story_logic', 'sr_score_story_element', 'sr_score_setting',
        'sr_score_story_bogged', 'sr_score_scenes_impact', 'sr_score_stakes',
        'sr_score_tension', 'sr_score_characters_interesting', 'sr_score_characters_choices',
        'sr_score_characters_motivations', 'sr_score_characters_different',
        'sr_score_antagonistic', 'sr_score_dialogue', 'sr_score_action_text',
        'sr_score_climax', 'sr_score_work_feels', 'sr_score_target_audience',
        'sr_score_content', 'sr_score_format',

        // SR meta
        'sr_bechdel', 'sr_diversity', 'sr_recommendation',

        // WD metadata
        'wd_assignment_type', 'wd_form', 'wd_mpaa_rating', 'wd_request',
        'wd_script_recommendations',

        // WD content
        'wd_logline', 'wd_synopsis',

        // WD note sections
        'wd_score_concept', 'wd_notes_concept',
        'wd_score_plot', 'wd_notes_plot',
        'wd_score_pacing', 'wd_notes_pacing',
        'wd_score_format', 'wd_notes_format',
        'wd_score_characters', 'wd_notes_characters',
        'wd_score_dialogue', 'wd_notes_dialogue',
        'wd_score_overall', 'wd_notes_overall',

        // WD recommendations
        'wd_recommend_writer', 'wd_recommend_material',
    ];

    protected function casts(): array
    {
        return [
            'quality_checked'    => 'boolean',
            'sr_reader_request'  => 'boolean',
            'sr_proofreading'    => 'boolean',
            'sr_net15'           => 'boolean',
            'wd_request'         => 'boolean',
            'sr_custom_oversized_fee' => 'decimal:2',
            'sr_book_pay_rate'   => 'decimal:2',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }
}
