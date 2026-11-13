<?php

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
    }
}
