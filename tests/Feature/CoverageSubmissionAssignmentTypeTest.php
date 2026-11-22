<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\CoverageAttestation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for the StoreCoverageSubmissionRequest fix (2026-11-22):
 * sr_assignment_type/wd_assignment_type must always come from the assignment's own
 * assignment_type column, never from client input, and wd_synopsis must only be
 * required for the "coverage" WD type, not "development_notes".
 */
class CoverageSubmissionAssignmentTypeTest extends TestCase
{
    use RefreshDatabase;

    private function makeAssignment(array $overrides = []): Assignment
    {
        return Assignment::create(array_merge([
            'order_number'              => 'TEST-' . random_int(100000, 999999),
            'script_title'              => 'Test Script',
            'writer_name'               => 'Test Writer',
            'page_count'                => 100,
            'pay_rate'                  => 50,
            'status'                    => Assignment::STATUS_ASSIGNED,
            'vendor'                    => 'sr',
            'exempt_from_word_counts'   => true, // not under test here
        ], $overrides));
    }

    private function srBaseFields(): array
    {
        // The test sqlite file persists across separate `php artisan test` invocations
        // (RefreshDatabase only wipes it at process start, not per test-run), so clear
        // any attestations left over from an earlier run before creating exactly one.
        CoverageAttestation::query()->delete();
        $attestation = CoverageAttestation::create(['text' => 'Checked.', 'sort_order' => 1]);

        $fields = [
            'writer_name'       => 'Some Writer',
            'genre'             => 'Drama',
            'time_period'       => 'Present',
            'locations'         => 'LA',
            'estimated_budget'  => 'medium',
            'page_count'        => 100,
            'sr_notes'          => 'Notes body.',
            'sr_bechdel'        => 'Yes',
            'sr_diversity'      => 'Diverse',
            'sr_recommendation' => 'Consider',
            'attestations'      => [$attestation->id],
        ];

        foreach ([
            'concept', 'opening_pages', 'theme', 'story_logic', 'story_element', 'setting',
            'story_bogged', 'scenes_impact', 'stakes', 'tension', 'characters_interesting',
            'characters_choices', 'characters_motivations', 'characters_different',
            'antagonistic', 'dialogue', 'action_text', 'climax', 'work_feels',
            'target_audience', 'content', 'format',
        ] as $score) {
            $fields["sr_score_{$score}"] = 75;
        }

        return $fields;
    }

    public function test_tampered_sr_assignment_type_cannot_bypass_the_real_assignments_synopsis_requirement(): void
    {
        $reader     = User::factory()->create(['role' => 'reader']);
        $assignment = $this->makeAssignment([
            'assignment_type'    => 'book', // real type requires sr_synopsis
            'assigned_reader_id' => $reader->id,
        ]);

        // Claims "short" (which would NOT require a synopsis) and omits sr_synopsis —
        // the fix means this is rejected because the server uses the assignment's real
        // "book" type, which does require one, regardless of what's submitted here.
        $payload = array_merge($this->srBaseFields(), [
            'sr_assignment_type' => 'short',
            'sr_logline'         => 'A logline.',
        ]);

        $this->actingAs($reader)
            ->post("/assignments/{$assignment->id}/coverage", $payload)
            ->assertSessionHasErrors('sr_synopsis');
    }

    public function test_the_real_assignment_type_is_still_honored_for_a_legitimate_submission(): void
    {
        $reader     = User::factory()->create(['role' => 'reader']);
        $assignment = $this->makeAssignment([
            'assignment_type'    => 'short', // real type does NOT require sr_synopsis
            'assigned_reader_id' => $reader->id,
        ]);

        $payload = array_merge($this->srBaseFields(), [
            'sr_assignment_type' => 'short',
            'sr_logline'         => 'A logline.',
        ]);

        $this->actingAs($reader)
            ->post("/assignments/{$assignment->id}/coverage", $payload)
            ->assertSessionDoesntHaveErrors();

        $this->assertSame(Assignment::STATUS_QC, $assignment->fresh()->status);
    }

    public function test_wd_development_notes_does_not_require_a_synopsis(): void
    {
        $reader     = User::factory()->create(['role' => 'reader']);
        $assignment = $this->makeAssignment([
            'vendor'             => 'wd',
            'assignment_type'    => 'development_notes',
            'assigned_reader_id' => $reader->id,
        ]);

        $payload = [
            'wd_assignment_type'        => 'development_notes',
            'genre'                     => 'Drama',
            'time_period'               => 'Present',
            'locations'                 => 'LA',
            'estimated_budget'          => 'medium',
            'wd_request'                => false,
            'wd_form'                   => 'Screenplay',
            'wd_mpaa_rating'            => 'PG-13',
            'wd_logline'                => 'A logline.',
            // wd_synopsis intentionally omitted
            'wd_script_recommendations' => 'Heat',
            'wd_score_concept'          => 'Good',
            'wd_notes_concept'          => 'Notes.',
            'wd_score_plot'             => 'Good',
            'wd_notes_plot'             => 'Notes.',
            'wd_score_pacing'           => 'Good',
            'wd_notes_pacing'           => 'Notes.',
            'wd_score_format'           => 'Good',
            'wd_notes_format'           => 'Notes.',
            'wd_score_characters'       => 'Good',
            'wd_notes_characters'       => 'Notes.',
            'wd_score_dialogue'         => 'Good',
            'wd_notes_dialogue'         => 'Notes.',
            'wd_score_overall'          => 'Good',
            'wd_notes_overall'          => 'Notes.',
            'wd_recommend_writer'       => 'Consider',
            'wd_recommend_material'     => 'Consider',
            'quality_checked'           => true,
        ];

        $this->actingAs($reader)
            ->post("/assignments/{$assignment->id}/coverage", $payload)
            ->assertSessionDoesntHaveErrors();

        $this->assertSame(Assignment::STATUS_QC, $assignment->fresh()->status);
    }
}
