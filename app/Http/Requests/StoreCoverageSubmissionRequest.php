<?php

// v1.0 — 2026-05-17 | Validates SR and WD coverage submission forms

namespace App\Http\Requests;

use App\Models\Assignment;
use Illuminate\Foundation\Http\FormRequest;

class StoreCoverageSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $assignment = $this->route('assignment');
        return $this->user()->can('submitCoverage', $assignment);
    }

    public function rules(): array
    {
        /** @var Assignment $assignment */
        $assignment = $this->route('assignment');

        if ($assignment->vendor === 'wd') {
            return $this->wdRules();
        }

        return $this->srRules();
    }

    private function srRules(): array
    {
        $type = $this->input('sr_assignment_type');

        $noPageCount      = $type === 'book';
        $noReaders        = in_array($type, ['deep_dive', 'short', 'book', 'budget'], true);
        $noReaderRequest  = $type === 'deep_dive';
        $noProofreading   = in_array($type, ['book', 'short'], true);
        $bookOnly         = $type === 'book';
        $showLogline      = in_array($type, ['script_coverage', 'short', 'deep_dive', 'book'], true);
        $showSynopsis     = in_array($type, ['script_coverage', 'book'], true);
        $pageCount        = (int) $this->input('page_count', 0);
        $showOversized    = $pageCount > 160 && !$bookOnly;

        return [
            'sr_assignment_type'     => ['required', 'in:script_coverage,notes_only,short,deep_dive,budget,book'],
            'writer_name'            => ['required', 'string', 'max:255'],
            'genre'                  => ['required', 'string', 'max:255'],
            'time_period'            => ['required', 'string', 'max:255'],
            'locations'              => ['required', 'string', 'max:255'],
            'estimated_budget'       => ['required', 'string', 'max:100'],
            'sr_net15'               => ['required', 'boolean'],

            'page_count'             => $noPageCount ? ['nullable', 'integer'] : ['required', 'integer', 'min:1', 'max:9999'],
            'sr_number_of_readers'   => $noReaders   ? ['nullable', 'string']  : ['required', 'in:1 Reader,2 Readers,3 Readers,other'],
            'sr_reader_request'      => $noReaderRequest ? ['nullable', 'boolean'] : ['required', 'boolean'],
            'sr_proofreading'        => $noProofreading  ? ['nullable', 'boolean'] : ['required', 'boolean'],
            'sr_book_pay_rate'       => $bookOnly    ? ['required', 'numeric', 'min:0'] : ['nullable', 'numeric'],
            'sr_custom_oversized_fee'=> $showOversized ? ['nullable', 'numeric', 'min:0'] : ['nullable', 'numeric'],

            'sr_logline'             => $showLogline  ? ['required', 'string'] : ['nullable', 'string'],
            'sr_synopsis'            => $showSynopsis ? ['required', 'string'] : ['nullable', 'string'],
            'sr_notes'               => ['required', 'string'],

            // 22 scores — all required, 50–100
            'sr_score_concept'                => ['required', 'integer', 'min:50', 'max:100'],
            'sr_score_opening_pages'          => ['required', 'integer', 'min:50', 'max:100'],
            'sr_score_theme'                  => ['required', 'integer', 'min:50', 'max:100'],
            'sr_score_story_logic'            => ['required', 'integer', 'min:50', 'max:100'],
            'sr_score_story_element'          => ['required', 'integer', 'min:50', 'max:100'],
            'sr_score_setting'                => ['required', 'integer', 'min:50', 'max:100'],
            'sr_score_story_bogged'           => ['required', 'integer', 'min:50', 'max:100'],
            'sr_score_scenes_impact'          => ['required', 'integer', 'min:50', 'max:100'],
            'sr_score_stakes'                 => ['required', 'integer', 'min:50', 'max:100'],
            'sr_score_tension'                => ['required', 'integer', 'min:50', 'max:100'],
            'sr_score_characters_interesting' => ['required', 'integer', 'min:50', 'max:100'],
            'sr_score_characters_choices'     => ['required', 'integer', 'min:50', 'max:100'],
            'sr_score_characters_motivations' => ['required', 'integer', 'min:50', 'max:100'],
            'sr_score_characters_different'   => ['required', 'integer', 'min:50', 'max:100'],
            'sr_score_antagonistic'           => ['required', 'integer', 'min:50', 'max:100'],
            'sr_score_dialogue'               => ['required', 'integer', 'min:50', 'max:100'],
            'sr_score_action_text'            => ['required', 'integer', 'min:50', 'max:100'],
            'sr_score_climax'                 => ['required', 'integer', 'min:50', 'max:100'],
            'sr_score_work_feels'             => ['required', 'integer', 'min:50', 'max:100'],
            'sr_score_target_audience'        => ['required', 'integer', 'min:50', 'max:100'],
            'sr_score_content'                => ['required', 'integer', 'min:50', 'max:100'],
            'sr_score_format'                 => ['required', 'integer', 'min:50', 'max:100'],

            'sr_bechdel'       => ['required', 'in:Not applicable,Yes,No'],
            'sr_diversity'     => ['required', 'in:Not applicable,Diverse,Moderately Diverse,Could use more Diversity'],
            'sr_recommendation'=> ['required', 'in:Pass,Consider,Consider with Reservations,Recommend'],
            'quality_checked'  => ['required', 'accepted'],
        ];
    }

    private function wdRules(): array
    {
        $type = $this->input('wd_assignment_type');

        return [
            'wd_assignment_type'      => ['required', 'in:coverage,development_notes'],
            'genre'                   => ['required', 'string', 'max:255'],
            'time_period'             => ['required', 'string', 'max:255'],
            'locations'               => ['required', 'string', 'max:255'],
            'estimated_budget'        => ['required', 'string', 'max:100'],
            'wd_request'              => ['required', 'boolean'],
            'wd_form'                 => ['required', 'string', 'max:255'],
            'wd_mpaa_rating'          => ['required', 'string', 'max:100'],
            'wd_logline'              => ['required', 'string'],
            'wd_synopsis'             => $type === 'coverage' ? ['required', 'string'] : ['nullable', 'string'],
            'wd_script_recommendations' => ['required', 'string', 'max:500'],

            'wd_score_concept'    => ['required', 'in:Poor,Fair,Good,Excellent'],
            'wd_notes_concept'    => ['required', 'string'],
            'wd_score_plot'       => ['required', 'in:Poor,Fair,Good,Excellent'],
            'wd_notes_plot'       => ['required', 'string'],
            'wd_score_pacing'     => ['required', 'in:Poor,Fair,Good,Excellent'],
            'wd_notes_pacing'     => ['required', 'string'],
            'wd_score_format'     => ['required', 'in:Poor,Fair,Good,Excellent'],
            'wd_notes_format'     => ['required', 'string'],
            'wd_score_characters' => ['required', 'in:Poor,Fair,Good,Excellent'],
            'wd_notes_characters' => ['required', 'string'],
            'wd_score_dialogue'   => ['required', 'in:Poor,Fair,Good,Excellent'],
            'wd_notes_dialogue'   => ['required', 'string'],
            'wd_score_overall'    => ['required', 'in:Poor,Fair,Good,Excellent'],
            'wd_notes_overall'    => ['required', 'string'],

            'wd_recommend_writer'   => ['required', 'in:Pass,Consider,Recommend'],
            'wd_recommend_material' => ['required', 'in:Pass,Consider,Recommend'],
            'quality_checked'       => ['required', 'accepted'],
        ];
    }
}
