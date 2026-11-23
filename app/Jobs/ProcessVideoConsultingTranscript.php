<?php

// v1.2 — 2026-11-23 | (1) handle() now takes an atomic Cache::lock() keyed on transcriptId
//                      before fetching/saving — the old idempotency check (comparing against
//                      video_consulting_transcription_id) was a plain read with no lock
//                      guarding the fetch, so a duplicate webhook delivery for the same
//                      transcriptId could let both instances fetch and write concurrently.
//                      (2) Add failed() — after all retries exhaust, nothing signalled that
//                      the transcript never got saved; lower-priority sibling of
//                      TranscribeVideoConsultingRecording::failed() since a manual
//                      transcript-entry fallback already exists on the QC page.
// v1.1 — 2026-11-13 | Removed the Claude-based contact-info-exchange check — user decided not
//                      to pay for a second AI vendor and will review transcripts by hand in QC.
//                      This job now only fetches and saves the transcript text.
// v1.0 — 2026-11-13 | Dispatched by AssemblyAiWebhookController once a transcription job
//                      finishes. Fetches the transcript text and saves it into the existing
//                      video_consulting_transcript column (same field the manual-paste QC
//                      textarea reads/writes — the admin can still edit it after this fills it in).

namespace App\Jobs;

use App\Models\Assignment;
use App\Services\AssemblyAiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessVideoConsultingTranscript implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 30;
    public int $timeout = 120;

    public function __construct(
        public readonly int $assignmentId,
        public readonly string $transcriptId,
        public readonly string $status,
    ) {
    }

    public function handle(AssemblyAiService $assemblyAi): void
    {
        $lock = Cache::lock("video-consulting-process-transcript:{$this->transcriptId}", $this->timeout + 30);

        if (! $lock->get()) {
            Log::info('ProcessVideoConsultingTranscript: another worker already holds the lock for this transcriptId — skipping', [
                'assignment_id' => $this->assignmentId,
                'transcript_id' => $this->transcriptId,
            ]);
            return;
        }

        try {
            $assignment = Assignment::findOrFail($this->assignmentId);

            if ($assignment->video_consulting_transcription_id !== $this->transcriptId) {
                return; // stale webhook for a transcription this assignment no longer tracks
            }

            $result = $assemblyAi->fetchTranscript($this->transcriptId);

            if ($this->status === 'error' || $result['status'] === 'error') {
                Log::error('ProcessVideoConsultingTranscript: AssemblyAI transcription failed', [
                    'assignment_id' => $assignment->id,
                    'transcript_id' => $this->transcriptId,
                    'error'         => $result['error'] ?? 'unknown error',
                ]);
                return;
            }

            $transcript = AssemblyAiService::formatUtterances($result['utterances']);
            $transcript = $transcript !== '' ? $transcript : (string) ($result['text'] ?? '');

            if ($transcript === '') {
                Log::warning('ProcessVideoConsultingTranscript: transcript came back empty', [
                    'assignment_id' => $assignment->id,
                    'transcript_id' => $this->transcriptId,
                ]);
                return;
            }

            $assignment->update(['video_consulting_transcript' => $transcript]);

            Log::info('ProcessVideoConsultingTranscript: transcript saved', [
                'assignment_id' => $assignment->id,
            ]);
        } finally {
            $lock->release();
        }
    }

    /**
     * Called by the queue worker once all retries are exhausted. Without this, a permanently
     * failed fetch/save left the assignment with no transcript and no signal that one would
     * never arrive automatically. Lower priority than DownloadWherebyRecording's/
     * TranscribeVideoConsultingRecording's equivalents since a manual transcript-entry
     * fallback already exists on the QC page.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessVideoConsultingTranscript: permanently failed after all retries', [
            'assignment_id' => $this->assignmentId,
            'transcript_id' => $this->transcriptId,
            'error'         => $exception->getMessage(),
        ]);

        $assignment = Assignment::find($this->assignmentId);
        if (! $assignment || $assignment->status !== Assignment::STATUS_QC) {
            return;
        }

        if ($assignment->video_consulting_transcription_id !== $this->transcriptId) {
            return; // stale — a newer transcription already superseded this one
        }

        if ($assignment->video_consulting_transcript) {
            return; // a retry (or a concurrent attempt) actually saved it before this failure signal landed
        }

        $assignment->update([
            'status'                                  => Assignment::STATUS_NEEDS_ATTENTION,
            'video_consulting_needs_attention_reason' => 'transcript_fetch_failed',
            'needs_attention_notes'                   => "Automatic transcript fetch/save failed after retries (transcript {$this->transcriptId}) — paste the transcript in manually on the QC page, or retry.",
        ]);
    }
}
