<?php

// v1.0 — 2026-05-22 | Create coverage Google Docs from templates; export to PDF.

namespace App\Services;

use App\Models\Assignment;
use App\Models\CoverageSubmission;
use Google\Client;
use Illuminate\Support\Facades\Log;
use Google\Service\Docs;
use Google\Service\Docs\BatchUpdateDocumentRequest;
use Google\Service\Docs\Request as DocsRequest;
use Google\Service\Docs\ReplaceAllTextRequest;
use Google\Service\Docs\SubstringMatchCriteria;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;

class GoogleDocsService
{
    private Drive $drive;
    private Docs  $docs;

    // Template IDs — match SR Templates/COVERAGE/ in Google Drive
    private const TEMPLATE_SR_MAIN       = '12ZW3PQF6rboxcGSmysbMbtZjQ8ZxC5sCDn_8gekaVfQ';
    private const TEMPLATE_SR_NOTES_ONLY = '11JbEq1A79G8F7AZdOQzKL6Q68jOcXmN5c19FVp-lSSY';
    private const TEMPLATE_SR_BUDGET     = '1LYuk1iJvdSpq73SgK1QNv16Sa3Tn1RUsZ00_EdRzbPM';
    private const TEMPLATE_WD            = '1N6gVt_8JpvgxS6rtTHL0pVr36x5_HjGnLgVOQHMdC_8';

    // SR scoresheet field order — must match the {{sr_rs01}}…{{sr_rs22}} order in the template
    private const SR_SCORE_FIELDS = [
        'sr_score_concept', 'sr_score_opening_pages', 'sr_score_theme',
        'sr_score_story_logic', 'sr_score_story_element', 'sr_score_setting',
        'sr_score_story_bogged', 'sr_score_scenes_impact', 'sr_score_stakes',
        'sr_score_tension', 'sr_score_characters_interesting', 'sr_score_characters_choices',
        'sr_score_characters_motivations', 'sr_score_characters_different',
        'sr_score_antagonistic', 'sr_score_dialogue', 'sr_score_action_text',
        'sr_score_climax', 'sr_score_work_feels', 'sr_score_target_audience',
        'sr_score_content', 'sr_score_format',
    ];

    public function __construct()
    {
        $client = new Client();
        $client->useApplicationDefaultCredentials();
        $client->setSubject(config('services.google.impersonate_user'));
        $client->addScope(Drive::DRIVE);
        $client->addScope(Docs::DOCUMENTS);

        $this->drive = new Drive($client);
        $this->docs  = new Docs($client);
    }

    /**
     * Copy the right template, fill all placeholders, return the new Google Doc ID.
     */
    public function createFromSubmission(Assignment $assignment, CoverageSubmission $submission): string
    {
        $assignment->loadMissing('assignedReader');

        $templateId = $this->templateId($assignment);
        $filename   = $this->filename($assignment);
        $folderId   = config('services.google.drive_coverage_folder_id');

        Log::info('GoogleDocsService: starting', [
            'assignment_id'   => $assignment->id,
            'vendor'          => $assignment->vendor,
            'assignment_type' => $assignment->assignment_type,
            'template_id'     => $templateId,
            'folder_id'       => $folderId,
            'filename'        => $filename,
        ]);

        $docId = $this->copyTemplate($templateId, $filename, $folderId);
        Log::info('GoogleDocsService: template copied', ['doc_id' => $docId]);

        $replacements = $assignment->vendor === 'wd'
            ? $this->wdReplacements($assignment, $submission)
            : $this->srReplacements($assignment, $submission);

        Log::info('GoogleDocsService: filling placeholders', ['count' => count($replacements)]);
        $this->fillPlaceholders($docId, $replacements);
        Log::info('GoogleDocsService: placeholders filled');

        return $docId;
    }

    /**
     * Export an existing Google Doc to PDF, save it to the coverage output folder,
     * and return the new PDF file ID.
     */
    public function exportToPdf(string $docId, string $filename): string
    {
        $folderId = config('services.google.drive_coverage_folder_id');

        Log::info('GoogleDocsService: exporting PDF', ['doc_id' => $docId, 'folder_id' => $folderId]);

        $response = $this->drive->files->export(
            $docId,
            'application/pdf',
            ['alt' => 'media']
        );

        $bytes = $response->getBody()->getContents();
        Log::info('GoogleDocsService: PDF exported', ['bytes' => strlen($bytes)]);

        $file = $this->drive->files->create(
            new DriveFile([
                'name'    => $filename . '.pdf',
                'parents' => [$folderId],
            ]),
            [
                'data'              => $bytes,
                'mimeType'          => 'application/pdf',
                'uploadType'        => 'multipart',
                'fields'            => 'id',
                'supportsAllDrives' => true,
            ]
        );

        Log::info('GoogleDocsService: PDF saved to Drive', ['pdf_id' => $file->id]);

        return $file->id;
    }

    // -------------------------------------------------------------------------
    // Template selection
    // -------------------------------------------------------------------------

    private function templateId(Assignment $assignment): string
    {
        if ($assignment->vendor === 'wd') {
            return self::TEMPLATE_WD;
        }

        return match ($assignment->assignment_type) {
            'notes_only' => self::TEMPLATE_SR_NOTES_ONLY,
            'budget'     => self::TEMPLATE_SR_BUDGET,
            default      => self::TEMPLATE_SR_MAIN,
        };
    }

    // -------------------------------------------------------------------------
    // Drive: copy template to output folder
    // -------------------------------------------------------------------------

    private function copyTemplate(string $templateId, string $filename, string $folderId): string
    {
        $copy = $this->drive->files->copy(
            $templateId,
            new DriveFile(['name' => $filename, 'parents' => [$folderId]]),
            ['fields' => 'id', 'supportsAllDrives' => true]
        );

        return $copy->id;
    }

    // -------------------------------------------------------------------------
    // Docs: batch replace all placeholders
    // -------------------------------------------------------------------------

    private function fillPlaceholders(string $docId, array $replacements): void
    {
        $requests = [];

        foreach ($replacements as $placeholder => $value) {
            $requests[] = new DocsRequest([
                'replaceAllText' => new ReplaceAllTextRequest([
                    'containsText' => new SubstringMatchCriteria([
                        'text'      => $placeholder,
                        'matchCase' => true,
                    ]),
                    'replaceText' => (string) $value,
                ]),
            ]);
        }

        $this->docs->documents->batchUpdate(
            $docId,
            new BatchUpdateDocumentRequest(['requests' => $requests])
        );
    }

    // -------------------------------------------------------------------------
    // SR placeholder map
    // -------------------------------------------------------------------------

    private function srReplacements(Assignment $assignment, CoverageSubmission $sub): array
    {
        $type = $sub->sr_assignment_type ?? $assignment->assignment_type;

        $typeLabel = [
            'script_coverage' => 'Script Coverage',
            'notes_only'      => 'Notes-Only Coverage',
            'deep_dive'       => 'Development Notes',
            'short'           => 'Short Coverage',
            'book'            => 'Book Coverage',
            'budget'          => 'Budget Coverage',
        ][$type] ?? ucfirst(str_replace('_', ' ', $type));

        $showLogline  = in_array($type, ['script_coverage', 'short', 'deep_dive', 'book']);
        $showSynopsis = in_array($type, ['script_coverage', 'book']);

        $scoreValues = array_map(fn ($k) => (int) ($sub->$k ?? 75), self::SR_SCORE_FIELDS);
        $finalScore  = (int) round(array_sum($scoreValues) / count($scoreValues));

        $readerCount = Assignment::where('order_number', $assignment->order_number)->count();

        $replacements = [
            '{{sr_HEADERlogline}}'  => $showLogline  ? 'Logline'   : '',
            '{{sr_HEADERsynopsis}}' => $showSynopsis ? 'Synopsis'  : '',
            '{{sr_HEADERnotes}}'    => $type === 'deep_dive' ? 'Development Notes' : 'Notes',
            '{{sr_assignmenttype}}' => $typeLabel,
            '{{sr_title}}'          => $assignment->script_title,
            '{{sr_writer}}'         => $sub->writer_name ?? $assignment->writer_name,
            '{{sr_reader}}'         => $assignment->assignedReader?->name ?? '',
            '{{date}}'              => now()->format('F j, Y'),
            '{{ordernumber}}'       => $assignment->order_number,
            '{{sr_pagecount}}'      => (string) $assignment->page_count,
            '{{sr_genre}}'          => $sub->genre ?? '',
            '{{sr_timeperiod}}'     => $sub->time_period ?? '',
            '{{sr_budget}}'         => $sub->estimated_budget ?? '',
            '{{sr_locations}}'      => $sub->locations ?? '',
            '{{sr_readerquantity}}' => (string) $readerCount,
            '{{sr_logline}}'        => $sub->sr_logline ?? '',
            '{{sr_synopsis}}'       => $sub->sr_synopsis ?? '',
            '{{sr_notes}}'          => $sub->sr_notes ?? '',
            '{{sr_recommendation}}' => $sub->sr_recommendation ?? '',
            '{{sr_bechdel}}'        => $sub->sr_bechdel ?? '',
            '{{sr_diversity}}'      => $sub->sr_diversity ?? '',
            '{{sr_rs_finalscore}}'  => (string) $finalScore,
        ];

        foreach (array_values(self::SR_SCORE_FIELDS) as $i => $field) {
            $token                    = '{{sr_rs' . str_pad($i + 1, 2, '0', STR_PAD_LEFT) . '}}';
            $replacements[$token]     = (string) ($sub->$field ?? 75);
        }

        return $replacements;
    }

    // -------------------------------------------------------------------------
    // WD placeholder map
    // -------------------------------------------------------------------------

    private function wdReplacements(Assignment $assignment, CoverageSubmission $sub): array
    {
        $typeLabel = $sub->wd_assignment_type === 'development_notes'
            ? 'Development Notes'
            : 'Coverage';

        $checkbox = fn (string $field, string $match): string =>
            ($sub->$field === $match) ? 'X' : ' ';

        $replacements = [
            '{{WDTITLE}}'                 => $assignment->script_title,
            '{{WDASSIGNMENTTYPE}}'        => $typeLabel,
            '{{WDWRITER}}'                => $sub->writer_name ?? $assignment->writer_name,
            '{{WDDATE}}'                  => now()->format('F j, Y'),
            '{{WDFORM}}'                  => $sub->wd_form ?? '',
            '{{WDPAGES}}'                 => (string) $assignment->page_count,
            '{{WDTIMEPERIOD}}'            => $sub->time_period ?? '',
            '{{WDGENRE}}'                 => $sub->genre ?? '',
            '{{WDLOCATIONS}}'             => $sub->locations ?? '',
            '{{WDESTBUDGET}}'             => $sub->estimated_budget ?? '',
            '{{WDMPAARATING}}'            => $sub->wd_mpaa_rating ?? '',
            '{{WDLOGLINE}}'               => $sub->wd_logline ?? '',
            '{{WDSYNOPSIS}}'              => $sub->wd_synopsis ?? '',
            '{{WDNOTESCONCEPT}}'          => $sub->wd_notes_concept ?? '',
            '{{WDNOTESPLOT}}'             => $sub->wd_notes_plot ?? '',
            '{{WDNOTESPACING}}'           => $sub->wd_notes_pacing ?? '',
            '{{WDNOTESFORMAT}}'           => $sub->wd_notes_format ?? '',
            '{{WDNOTESCHARACTER}}'        => $sub->wd_notes_characters ?? '',
            '{{WDNOTESDIALOGUE}}'         => $sub->wd_notes_dialogue ?? '',
            '{{WDNOTESOVERALL}}'          => $sub->wd_notes_overall ?? '',
            '{{WDSCRIPTRECOMMENDATIONS}}' => $sub->wd_script_recommendations ?? '',
            '{{WDRECOMMENDSCRIPTPASS}}'      => $checkbox('wd_recommend_material', 'Pass'),
            '{{WDRECOMMENDSCRIPTCONSIDER}}'  => $checkbox('wd_recommend_material', 'Consider'),
            '{{WDRECOMMENDSCRIPTRECOMMEND}}' => $checkbox('wd_recommend_material', 'Recommend'),
            '{{WDRECOMMENDWRITERPASS}}'      => $checkbox('wd_recommend_writer', 'Pass'),
            '{{WDRECOMMENDWRITERCONSIDER}}'  => $checkbox('wd_recommend_writer', 'Consider'),
            '{{WDRECOMMENDWRITERRECOMMEND}}' => $checkbox('wd_recommend_writer', 'Recommend'),
        ];

        $sections = ['concept', 'plot', 'pacing', 'format', 'characters', 'dialogue', 'overall'];
        $ratings  = ['Poor', 'Fair', 'Good', 'Excellent'];

        foreach ($sections as $section) {
            $field  = "wd_score_{$section}";
            $prefix = 'WD' . strtoupper($section);
            foreach ($ratings as $rating) {
                $placeholder              = '{{' . $prefix . strtoupper($rating) . '}}';
                $replacements[$placeholder] = $checkbox($field, $rating);
            }
        }

        return $replacements;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function filename(Assignment $assignment): string
    {
        return "#{$assignment->order_number} - {$assignment->script_title}";
    }
}
