<?php

// v1.1 — 2026-11-23 | (1) handle() now takes an atomic Cache::lock() keyed on recordingId
//                      before submitting to AssemblyAI (a paid call) — the old idempotency
//                      check (comparing against video_consulting_last_recording_id) was a
//                      plain read with no lock at all guarding the submission, so a
//                      webhook-retried duplicate dispatch could let both instances submit
//                      the same recording and pay twice. (2) Add failed() — after both retries
//                      exhaust, nothing signalled that no transcript would ever be requested
//                      for this recording; mirrors DeliverRegistrationEmail's fix (commit
//                      e1fc947) and DownloadWherebyRecording's: log it and flag
//                      STATUS_NEEDS_ATTENTION so QC knows to paste a transcript in manually.
// v1.0 — 2026-11-13 | Dispatched by DownloadWherebyRecording once a recording is stored in
//                      Spaces. Submits it to AssemblyAI with a webhook callback — transcription
//                      of a 30-90min call can take several minutes, too long to block a queue
//                      worker, so this job just submits and returns; ProcessVideoConsultingTranscript
//                      picks up the result when AssemblyAI's webhook lands.

namespace App\Jobs;

use App\Models\Assignment;
use App\Services\AssemblyAiService;
use App\Services\SpacesStorageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class TranscribeVideoConsultingRecording implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;
    public int $backoff = 60;
    public int $timeout = 60;

    public function __construct(
        public readonly int $assignmentId,
        public readonly string $recordingId,
        public readonly string $recordingPath,
    ) {
    }

    public function handle(AssemblyAiService $assemblyAi, SpacesStorageService $spaces): void
    {
        $lock = Cache::lock("video-consulting-transcribe:{$this->recordingId}", $this->timeout + 30);

        if (! $lock->get()) {
            Log::info('TranscribeVideoConsultingRecording: another worker already holds the lock for this recordingId — skipping', [
                'assignment_id' => $this->assignmentId,
                'recording_id'  => $this->recordingId,
            ]);
            return;
        }

        try {
            $assignment = Assignment::findOrFail($this->assignmentId);

            if ($assignment->video_consulting_last_recording_id !== $this->recordingId) {
                return; // a newer recording has already superseded this one — don't transcribe stale audio
            }

            // AssemblyAI needs a URL it can fetch over HTTP; give it enough time to actually
            // download a (potentially large) video file before the link expires.
            $audioUrl = $spaces->temporaryUrl($this->recordingPath, now()->addHours(2));
            $webhookUrl = URL::to('/api/assemblyai-webhook');

            $transcriptId = $assemblyAi->submitTranscription($audioUrl, $webhookUrl);

            $assignment->update(['video_consulting_transcription_id' => $transcriptId]);

            Log::info('TranscribeVideoConsultingRecording: submitted to AssemblyAI', [
                'assignment_id' => $assignment->id,
                'transcript_id' => $transcriptId,
            ]);
        } finally {
            $lock->release();
        }
    }

    /**
     * Called by the queue worker once both tries are exhausted. Without this, a permanently
     * failed AssemblyAI submission left the assignment at STATUS_QC with no transcript and no
     * signal that one would never arrive automatically — a lower-priority sibling of
     * DownloadWherebyRecording::failed() since a manual transcript-entry fallback already
     * exists on the QC page.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('TranscribeVideoConsultingRecording: permanently failed after all retries', [
            'assignment_id' => $this->assignmentId,
            'recording_id'  => $this->recordingId,
            'error'         => $exception->getMessage(),
        ]);

        $assignment = Assignment::find($this->assignmentId);
        if (! $assignment || $assignment->status !== Assignment::STATUS_QC) {
            return;
        }

        if ($assignment->video_consulting_last_recording_id !== $this->recordingId) {
            return; // a newer recording already superseded this one — nothing to flag
        }

        if ($assignment->video_consulting_transcription_id) {
            return; // a retry (or a concurrent attempt) actually submitted it before this failure signal landed
        }

        $assignment->update([
            'status'                                  => Assignment::STATUS_NEEDS_ATTENTION,
            'video_consulting_needs_attention_reason' => 'transcription_submission_failed',
            'needs_attention_notes'                   => "Automatic transcription submission failed after retries (recording {$this->recordingId}) — paste the transcript in manually on the QC page, or retry.",
        ]);
    }
}
