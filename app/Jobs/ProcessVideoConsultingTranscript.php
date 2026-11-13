<?php

// v1.0 — 2026-11-13 | Dispatched by AssemblyAiWebhookController once a transcription job
//                      finishes. Fetches the transcript text, saves it into the existing
//                      video_consulting_transcript column (same field the manual-paste QC
//                      textarea reads/writes — the admin can still edit it after this fills it
//                      in), then runs it through ContactInfoDetectionService (Claude) to flag a
//                      possible contact-info exchange for the human QC reviewer to check. The
//                      contact-info check is best-effort: if it fails, the transcript is still
//                      saved and the flag just stays unset rather than losing the transcript.

namespace App\Jobs;

use App\Models\Assignment;
use App\Services\AssemblyAiService;
use App\Services\ContactInfoDetectionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

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

    public function handle(AssemblyAiService $assemblyAi, ContactInfoDetectionService $contactInfo): void
    {
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

        try {
            $verdict = $contactInfo->check($transcript);

            $assignment->update([
                'video_consulting_contact_info_flag'     => $verdict['flagged'],
                'video_consulting_contact_info_evidence' => $verdict['evidence'] !== '' ? $verdict['evidence'] : null,
            ]);
        } catch (Throwable $e) {
            Log::error('ProcessVideoConsultingTranscript: contact-info check failed, transcript saved without a verdict', [
                'assignment_id' => $assignment->id,
                'transcript_id' => $this->transcriptId,
                'error'         => $e->getMessage(),
            ]);
        }

        Log::info('ProcessVideoConsultingTranscript: transcript saved', [
            'assignment_id' => $assignment->id,
        ]);
    }
}
