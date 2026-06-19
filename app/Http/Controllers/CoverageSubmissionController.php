<?php

// v1.6 — 2026-06-12 | store(): delete the previous coverage Doc/PDF from Drive on resubmission
//                     (e.g. after QC send-back) so re-submitting no longer leaves orphaned files.
// v1.4 — 2026-05-28 | saveDraft(): persist coverage fields without advancing status; "Continue Coverage" UX.
// v1.3 — 2026-05-25 | Add coverage preview endpoint (text-only HTML view for admins/editors and reader's own)
// v1.2 — 2026-05-24 | Submit button spinner; redirect to dedicated submitted page with custom HTML
// v1.1 — 2026-05-22 | Fire GoogleDocsService after submission to create coverage doc + draft PDF
// v1.5 — 2026-05-31 | Create AssignmentNote from note_to_team field on submission
// v1.0 — 2026-05-17 | Coverage form show + store for SR and WD vendors

namespace App\Http\Controllers;

use App\Http\Requests\StoreCoverageSubmissionRequest;
use App\Models\Assignment;
use App\Models\AssignmentNote;
use App\Models\ReaderScriptNote;
use App\Models\Setting;
use App\Services\GoogleDocsService;
use App\Services\GoogleDriveService;
use App\Support\FilenameGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class CoverageSubmissionController extends Controller
{
    public function show(Assignment $assignment)
    {
        $this->authorize('submitCoverage', $assignment);

        $user     = auth()->user();
        $existing = $assignment->coverageSubmission;
        $view     = $assignment->vendor === 'wd' ? 'coverage.wd' : 'coverage.sr';

        $autofillKey  = $user->isAdmin() ? 'dev_autofill_admin' : ($user->isEditor() ? 'dev_autofill_editor' : 'dev_autofill_reader');
        $showAutofill = (bool) Setting::getValue($autofillKey, false);
        $wordCounts   = Setting::getWordCounts();
        $wcExempt     = (bool) $assignment->exempt_from_word_counts;

        $readingNotes = ReaderScriptNote::where('assignment_id', $assignment->id)
            ->where('user_id', $user->id)
            ->orderBy('created_at')
            ->get();

        return view($view, compact('assignment', 'existing', 'showAutofill', 'wordCounts', 'wcExempt', 'readingNotes'));
    }

    public function store(StoreCoverageSubmissionRequest $request, Assignment $assignment)
    {
        $this->authorize('submitCoverage', $assignment);

        $submission = null;

        DB::transaction(function () use ($request, $assignment, &$submission) {
            $data = $request->validated();
            $data['vendor'] = $assignment->vendor;

            $submission = $assignment->coverageSubmission()->updateOrCreate(
                ['assignment_id' => $assignment->id],
                $data
            );

            $assignment->update([
                'status'       => Assignment::STATUS_QC,
                'submitted_at' => now(),
            ]);

            // Create a team note if the reader included one
            $noteBody = trim($request->input('note_to_team', ''));
            if ($noteBody !== '') {
                AssignmentNote::create([
                    'assignment_id' => $assignment->id,
                    'user_id'       => auth()->id(),
                    'body'          => $noteBody,
                    'dismissed_by'  => [],
                ]);
            }
        });

        // Create the coverage Google Doc and draft PDF outside the transaction
        // so a Drive API failure doesn't roll back the submitted coverage.
        try {
            $oldDocId = $assignment->drive_coverage_doc_id;
            $oldPdfId = $assignment->drive_coverage_pdf_id;

            $docs     = new GoogleDocsService();
            $docId    = $docs->createFromSubmission($assignment, $submission);
            $assignment->loadMissing('assignedReader.readerProfile');
            $initials = $assignment->assignedReader?->readerProfile?->initials;
            $pdfId    = $docs->exportToPdf($docId, FilenameGenerator::coverageDoc($assignment, $initials));

            $assignment->update([
                'drive_coverage_doc_id' => $docId,
                'drive_coverage_pdf_id' => $pdfId,
            ]);

            // Resubmission (e.g. after QC send-back) — remove the doc/PDF from the
            // previous attempt now that fresh ones have been saved successfully.
            if ($oldDocId || $oldPdfId) {
                $drive = new GoogleDriveService();
                foreach (array_filter([$oldDocId, $oldPdfId]) as $oldFileId) {
                    try {
                        $drive->deleteFile($oldFileId);
                    } catch (\Throwable $e) {
                        Log::warning('Failed to delete previous coverage file on resubmission', [
                            'assignment_id' => $assignment->id,
                            'file_id'       => $oldFileId,
                            'error'         => $e->getMessage(),
                        ]);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('Coverage doc creation failed', [
                'assignment_id' => $assignment->id,
                'error'         => $e->getMessage(),
            ]);
        }

        return redirect()->route('coverage.submitted')
            ->with('submitted_title', $assignment->script_title);
    }

    public function saveDraft(Request $request, Assignment $assignment): JsonResponse
    {
        $this->authorize('submitCoverage', $assignment);

        $data = $request->only([
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
        ]);

        $data['vendor'] = $assignment->vendor;

        $assignment->coverageSubmission()->updateOrCreate(
            ['assignment_id' => $assignment->id],
            $data
        );

        return response()->json(['status' => 'saved']);
    }

    public function submitted(): View
    {
        return view('coverage.submitted');
    }

    public function coveragePreview(Assignment $assignment): View
    {
        $user = auth()->user();
        abort_unless(
            $user->canManageAssignments() || $assignment->assigned_reader_id === $user->id,
            403
        );
        $submission = $assignment->coverageSubmission;
        abort_if(!$submission, 404);

        return view('coverage.preview', compact('assignment', 'submission'));
    }
}
