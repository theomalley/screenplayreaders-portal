<?php

// v1.1 — 2026-06-03 | Server-side word count minimum enforcement (respects global enable flag and per-assignment exemption)
// v1.0 — 2026-05-17 | Validates SR and WD coverage submission forms

namespace App\Http\Requests;

use App\Models\Assignment;
use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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

    /** After standard validation passes, enforce word count minimums. */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                /** @var Assignment $assignment */
                $assignment = $this->route('assignment');
                $wc = Setting::getWordCounts();

                if (!$wc['wc_enabled'] || $assignment->exempt_from_word_counts) {
                    return;
                }

                if ($assignment->vendor === 'wd') {
                    $this->checkWdWordCounts($validator, $wc, $assignment);
                } else {
                    $this->checkSrWordCounts($validator, $wc, $assignment);
                }
            },
        ];
    }

    private function wordCount(?string $text): int
    {
        if (!$text || !trim($text)) return 0;
        return count(preg_split('/\s+/', trim($text)));
    }

    private function checkSrWordCounts(Validator $validator, array $wc, Assignment $assignment): void
    {
        $type = $this->input('sr_assignment_type', $assignment->assignment_type);

        $showLogline  = in_array($type, ['script_coverage', 'short', 'deep_dive', 'book'], true);
        $showSynopsis = in_array($type, ['script_coverage', 'book'], true);

        if ($showLogline && $wc['wc_sr_logline'] > 0) {
            $count = $this->wordCount($this->input('sr_logline'));
            if ($count < $wc['wc_sr_logline']) {
                $validator->errors()->add('sr_logline', "Logline must be at least {$wc['wc_sr_logline']} words (currently {$count}).");
            }
        }

        if ($showSynopsis && $wc['wc_sr_synopsis'] > 0) {
            $count = $this->wordCount($this->input('sr_synopsis'));
            if ($count < $wc['wc_sr_synopsis']) {
                $validator->errors()->add('sr_synopsis', "Synopsis must be at least {$wc['wc_sr_synopsis']} words (currently {$count}).");
            }
        }

        $notesKey = "wc_sr_notes_{$type}";
        $notesMin = $wc[$notesKey] ?? 0;
        if ($notesMin > 0) {
            $count = $this->wordCount($this->input('sr_notes'));
            if ($count < $notesMin) {
                $validator->errors()->add('sr_notes', "Notes must be at least {$notesMin} words (currently {$count}).");
            }
        }
    }

    private function checkWdWordCounts(Validator $validator, array $wc, Assignment $assignment): void
    {
        $type = $this->input('wd_assignment_type', $assignment->assignment_type);

        if ($wc['wc_wd_logline'] > 0) {
            $count = $this->wordCount($this->input('wd_logline'));
            if ($count < $wc['wc_wd_logline']) {
                $validator->errors()->add('wd_logline', "Logline must be at least {$wc['wc_wd_logline']} words (currently {$count}).");
            }
        }

        if ($type === 'coverage' && $wc['wc_wd_synopsis'] > 0) {
            $count = $this->wordCount($this->input('wd_synopsis'));
            if ($count < $wc['wc_wd_synopsis']) {
                $validator->errors()->add('wd_synopsis', "Synopsis must be at least {$wc['wc_wd_synopsis']} words (currently {$count}).");
            }
        }

        $notesKey = $type === 'development_notes' ? 'wc_wd_notes_development_notes' : 'wc_wd_notes_coverage';
        $notesMin = $wc[$notesKey] ?? 0;
        if ($notesMin > 0) {
            $notesFields = ['wd_notes_concept', 'wd_notes_plot', 'wd_notes_pacing', 'wd_notes_format', 'wd_notes_characters', 'wd_notes_dialogue', 'wd_notes_overall'];
            $totalWords  = array_sum(array_map(fn($f) => $this->wordCount($this->input($f)), $notesFields));
            if ($totalWords < $notesMin) {
                $validator->errors()->add('wd_notes_overall', "Total notes must be at least {$notesMin} words (currently {$totalWords}).");
            }
        }
    }

    private function srRules(): array
    {
        $type = $this->input('sr_assignment_type');

        $noPageCount      = $type === 'book';
        $showLogline      = in_array($type, ['script_coverage', 'short', 'deep_dive', 'book'], true);
        $showSynopsis     = in_array($type, ['script_coverage', 'book'], true);
        $pageCount        = (int) $this->input('page_count', 0);
        $showOversized    = $pageCount > 160 && !$noPageCount;

        return [
            'sr_assignment_type'     => ['required', 'in:script_coverage,notes_only,short,deep_dive,budget,book'],
            'writer_name'            => ['required', 'string', 'max:255'],
            'genre'                  => ['required', 'string', 'max:255'],
            'time_period'            => ['required', 'string', 'max:255'],
            'locations'              => ['required', 'string', 'max:255'],
            'estimated_budget'       => ['required', 'string', 'max:100'],

            'page_count'             => $noPageCount ? ['nullable', 'integer'] : ['required', 'integer', 'min:1', 'max:9999'],
            'sr_number_of_readers'   => ['nullable', 'string'],
            'sr_reader_request'      => ['nullable', 'boolean'],
            'sr_proofreading'        => ['nullable', 'boolean'],
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
            'note_to_team'     => ['nullable', 'string', 'max:1000'],
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
