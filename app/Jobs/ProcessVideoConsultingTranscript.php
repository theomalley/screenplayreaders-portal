<?php

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
    }
}
