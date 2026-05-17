<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coverage_submissions', function (Blueprint $table) {
            // ── Vendor ────────────────────────────────────────────────────────────────
            $table->enum('vendor', ['sr', 'wd'])->after('assignment_id');

            // ── Shared metadata ───────────────────────────────────────────────────────
            $table->string('writer_name')->nullable()->after('vendor');
            $table->string('genre')->nullable()->after('writer_name');
            $table->string('time_period')->nullable()->after('genre');
            $table->string('locations')->nullable()->after('time_period');
            $table->string('estimated_budget')->nullable()->after('locations');

            // ── SR-only metadata ──────────────────────────────────────────────────────
            $table->string('sr_assignment_type')->nullable()->after('estimated_budget');
            $table->string('sr_number_of_readers')->nullable()->after('sr_assignment_type');
            $table->boolean('sr_reader_request')->nullable()->after('sr_number_of_readers');
            $table->boolean('sr_proofreading')->nullable()->after('sr_reader_request');
            $table->boolean('sr_net15')->nullable()->after('sr_proofreading');
            $table->decimal('sr_custom_oversized_fee', 8, 2)->nullable()->after('sr_net15');
            $table->decimal('sr_book_pay_rate', 8, 2)->nullable()->after('sr_custom_oversized_fee');

            // ── SR content ────────────────────────────────────────────────────────────
            $table->text('sr_logline')->nullable()->after('sr_book_pay_rate');
            $table->text('sr_synopsis')->nullable()->after('sr_logline');
            $table->text('sr_notes')->nullable()->after('sr_synopsis');

            // ── SR scoresheet — 22 scores, integer 50–100 ────────────────────────────
            $table->unsignedSmallInteger('sr_score_concept')->nullable()->after('sr_notes');
            $table->unsignedSmallInteger('sr_score_opening_pages')->nullable()->after('sr_score_concept');
            $table->unsignedSmallInteger('sr_score_theme')->nullable()->after('sr_score_opening_pages');
            $table->unsignedSmallInteger('sr_score_story_logic')->nullable()->after('sr_score_theme');
            $table->unsignedSmallInteger('sr_score_story_element')->nullable()->after('sr_score_story_logic');
            $table->unsignedSmallInteger('sr_score_setting')->nullable()->after('sr_score_story_element');
            $table->unsignedSmallInteger('sr_score_story_bogged')->nullable()->after('sr_score_setting');
            $table->unsignedSmallInteger('sr_score_scenes_impact')->nullable()->after('sr_score_story_bogged');
            $table->unsignedSmallInteger('sr_score_stakes')->nullable()->after('sr_score_scenes_impact');
            $table->unsignedSmallInteger('sr_score_tension')->nullable()->after('sr_score_stakes');
            $table->unsignedSmallInteger('sr_score_characters_interesting')->nullable()->after('sr_score_tension');
            $table->unsignedSmallInteger('sr_score_characters_choices')->nullable()->after('sr_score_characters_interesting');
            $table->unsignedSmallInteger('sr_score_characters_motivations')->nullable()->after('sr_score_characters_choices');
            $table->unsignedSmallInteger('sr_score_characters_different')->nullable()->after('sr_score_characters_motivations');
            $table->unsignedSmallInteger('sr_score_antagonistic')->nullable()->after('sr_score_characters_different');
            $table->unsignedSmallInteger('sr_score_dialogue')->nullable()->after('sr_score_antagonistic');
            $table->unsignedSmallInteger('sr_score_action_text')->nullable()->after('sr_score_dialogue');
            $table->unsignedSmallInteger('sr_score_climax')->nullable()->after('sr_score_action_text');
            $table->unsignedSmallInteger('sr_score_work_feels')->nullable()->after('sr_score_climax');
            $table->unsignedSmallInteger('sr_score_target_audience')->nullable()->after('sr_score_work_feels');
            $table->unsignedSmallInteger('sr_score_content')->nullable()->after('sr_score_target_audience');
            $table->unsignedSmallInteger('sr_score_format')->nullable()->after('sr_score_content');

            // ── SR meta ───────────────────────────────────────────────────────────────
            $table->string('sr_bechdel')->nullable()->after('sr_score_format');
            $table->string('sr_diversity')->nullable()->after('sr_bechdel');
            $table->string('sr_recommendation')->nullable()->after('sr_diversity');

            // ── WD-only metadata ──────────────────────────────────────────────────────
            $table->string('wd_assignment_type')->nullable()->after('sr_recommendation');
            $table->string('wd_form')->nullable()->after('wd_assignment_type');
            $table->string('wd_mpaa_rating')->nullable()->after('wd_form');
            $table->boolean('wd_request')->nullable()->after('wd_mpaa_rating');
            $table->text('wd_script_recommendations')->nullable()->after('wd_request');

            // ── WD content ────────────────────────────────────────────────────────────
            $table->text('wd_logline')->nullable()->after('wd_script_recommendations');
            $table->text('wd_synopsis')->nullable()->after('wd_logline');

            // ── WD notes — 7 sections (score + textarea each) ────────────────────────
            $table->string('wd_score_concept')->nullable()->after('wd_synopsis');
            $table->text('wd_notes_concept')->nullable()->after('wd_score_concept');
            $table->string('wd_score_plot')->nullable()->after('wd_notes_concept');
            $table->text('wd_notes_plot')->nullable()->after('wd_score_plot');
            $table->string('wd_score_pacing')->nullable()->after('wd_notes_plot');
            $table->text('wd_notes_pacing')->nullable()->after('wd_score_pacing');
            $table->string('wd_score_format')->nullable()->after('wd_notes_pacing');
            $table->text('wd_notes_format')->nullable()->after('wd_score_format');
            $table->string('wd_score_characters')->nullable()->after('wd_notes_format');
            $table->text('wd_notes_characters')->nullable()->after('wd_score_characters');
            $table->string('wd_score_dialogue')->nullable()->after('wd_notes_characters');
            $table->text('wd_notes_dialogue')->nullable()->after('wd_score_dialogue');
            $table->string('wd_score_overall')->nullable()->after('wd_notes_dialogue');
            $table->text('wd_notes_overall')->nullable()->after('wd_score_overall');

            // ── WD recommendations ────────────────────────────────────────────────────
            $table->string('wd_recommend_writer')->nullable()->after('wd_notes_overall');
            $table->string('wd_recommend_material')->nullable()->after('wd_recommend_writer');

            // ── Both forms ────────────────────────────────────────────────────────────
            $table->boolean('quality_checked')->default(false)->after('wd_recommend_material');
        });
    }

    public function down(): void
    {
        Schema::table('coverage_submissions', function (Blueprint $table) {
            $table->dropColumn([
                'vendor',
                'writer_name', 'genre', 'time_period', 'locations', 'estimated_budget',
                'sr_assignment_type', 'sr_number_of_readers', 'sr_reader_request',
                'sr_proofreading', 'sr_net15', 'sr_custom_oversized_fee', 'sr_book_pay_rate',
                'sr_logline', 'sr_synopsis', 'sr_notes',
                'sr_score_concept', 'sr_score_opening_pages', 'sr_score_theme',
                'sr_score_story_logic', 'sr_score_story_element', 'sr_score_setting',
                'sr_score_story_bogged', 'sr_score_scenes_impact', 'sr_score_stakes',
                'sr_score_tension', 'sr_score_characters_interesting',
                'sr_score_characters_choices', 'sr_score_characters_motivations',
                'sr_score_characters_different', 'sr_score_antagonistic',
                'sr_score_dialogue', 'sr_score_action_text', 'sr_score_climax',
                'sr_score_work_feels', 'sr_score_target_audience',
                'sr_score_content', 'sr_score_format',
                'sr_bechdel', 'sr_diversity', 'sr_recommendation',
                'wd_assignment_type', 'wd_form', 'wd_mpaa_rating', 'wd_request',
                'wd_script_recommendations', 'wd_logline', 'wd_synopsis',
                'wd_score_concept', 'wd_notes_concept',
                'wd_score_plot', 'wd_notes_plot',
                'wd_score_pacing', 'wd_notes_pacing',
                'wd_score_format', 'wd_notes_format',
                'wd_score_characters', 'wd_notes_characters',
                'wd_score_dialogue', 'wd_notes_dialogue',
                'wd_score_overall', 'wd_notes_overall',
                'wd_recommend_writer', 'wd_recommend_material',
                'quality_checked',
            ]);
        });
    }
};
