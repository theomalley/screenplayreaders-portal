<?php

// v1.7 — 2026-09-09 | store(): lock the assignment row (Assignment::lockForUpdate()) and
//                     re-check status inside the transaction — closes a double-submit
//                     race that could create two separate Google Docs/PDFs and duplicate
//                     the note_to_team AssignmentNote. saveDraft(): allowlist now derived
//                     from CoverageSubmission::draftFillable() instead of a hand-typed
//                     duplicate of the model's $fillable.
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
use App\Models\CoverageAttestation;
use App\Models\CoverageSubmission;
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

        $attestations = $view === 'coverage.sr'
            ? CoverageAttestation::orderBy('sort_order')->orderBy('id')->get()
            : collect();

        return view($view, compact('assignment', 'existing', 'showAutofill', 'wordCounts', 'wcExempt', 'readingNotes', 'attestations'));
    }

    public function store(StoreCoverageSubmissionRequest $request, Assignment $assignment)
    {
        $this->authorize('submitCoverage', $assignment);

        $submission       = null;
        $alreadySubmitted = false;

        // FIX: lock the row and re-check status inside the transaction — previously a
        // double-click or retried POST could fire two requests while status was still
        // 'assigned'/'needs_attention'; both would pass submitCoverage() authorization
        // (read before either write), both updateOrCreate() the same coverage_submissions
        // row and set status=qc, and both independently create a separate Google Doc/PDF
        // below — whichever finished last would win the drive_coverage_doc_id/pdf_id
        // columns, silently orphaning the other Doc/PDF pair, and note_to_team would fire
        // twice. Only the first request to acquire the lock now proceeds.
        $assignment = DB::transaction(function () use ($request, $assignment, &$submission, &$alreadySubmitted) {
            $fresh = Assignment::lockForUpdate()->findOrFail($assignment->id);

            if (! in_array($fresh->status, [Assignment::STATUS_ASSIGNED, Assignment::STATUS_NEEDS_ATTENTION], true)) {
                $alreadySubmitted = true;

                return $fresh;
            }

            $data = $request->validated();
            $data['vendor'] = $fresh->vendor;

            if (array_key_exists('attestations', $data)) {
                $attestationIds = $data['attestations'];
                unset($data['attestations']);

                $data['quality_checked'] = true;
                $data['quality_attestations_snapshot'] = CoverageAttestation::whereIn('id', $attestationIds)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->pluck('text')
                    ->values()
                    ->all();
            }

            $submission = $fresh->coverageSubmission()->updateOrCreate(
                ['assignment_id' => $fresh->id],
                $data
            );

            $fresh->update([
                'status'       => Assignment::STATUS_QC,
                'submitted_at' => now(),
            ]);

            // Create a team note if the reader included one
            $noteBody = trim($request->input('note_to_team', ''));
            if ($noteBody !== '') {
                AssignmentNote::create([
                    'assignment_id' => $fresh->id,
                    'user_id'       => auth()->id(),
                    'body'          => $noteBody,
                    'dismissed_by'  => [],
                ]);
            }

            return $fresh;
        });

        if ($alreadySubmitted) {
            return redirect()->route('coverage.submitted')
                ->with('submitted_title', $assignment->script_title);
        }

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

        // FIX: was a hand-typed 60+ field allowlist duplicated from CoverageSubmission's
        // $fillable, which could silently drift if a field was added to one but not the
        // other. Now derived from the model directly.
        $data = $request->only(CoverageSubmission::draftFillable());

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
