<?php

// v1.1 — 2026-11-11 | Fix: an early/test join (recording auto-starts the instant anyone enters
//                      the Whereby room, with no time gate) was being treated as "the call
//                      happened" — flipping STATUS_ASSIGNED -> STATUS_QC days before the real
//                      scheduled call, and permanently blocking the real recording from ever
//                      being processed (idempotency keyed on "a path already exists", not on
//                      which recording). Now dedupes by recordingId (video_consulting_
//                      last_recording_id) so a later, different recording always overwrites the
//                      stored path, and only flips status to QC when the recording finished at or
//                      after (slot_start - 10min) — a real call, not a premature peek.
// v1.0 — 2026-11-07 | Video consulting Phase 2 — mirrors CopyRegistrationScriptToSpaces'
//                      shape: fetch a signed download URL (here via Whereby's access-link
//                      endpoint, not a stored URL), stream it into Spaces, stamp the path.
//                      Also flips the assignment into the existing STATUS_QC gate so the
//                      recording gets reviewed the same way coverage does.

namespace App\Jobs;

use App\Models\Assignment;
use App\Services\SpacesStorageService;
use App\Services\WherebyService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DownloadWherebyRecording implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;
    public int $backoff = 60;
    public int $timeout = 180;

    public function __construct(
        public readonly int $assignmentId,
        public readonly string $recordingId,
        public readonly string $filename,
    ) {
    }

    public function handle(WherebyService $whereby, SpacesStorageService $spaces): void
    {
        $assignment = Assignment::findOrFail($this->assignmentId);

        if ($assignment->video_consulting_last_recording_id === $this->recordingId) {
            return; // this exact recording was already processed — idempotent
        }

        $link = $whereby->getRecordingAccessLink($this->recordingId);

        $response = Http::timeout(120)->get($link['accessLink']);

        if (! $response->successful()) {
            Log::error('DownloadWherebyRecording: download failed', [
                'assignment_id' => $assignment->id,
                'recording_id'  => $this->recordingId,
                'status'        => $response->status(),
            ]);
            throw new \RuntimeException("Failed to download recording: HTTP {$response->status()}");
        }

        $ext = pathinfo($this->filename, PATHINFO_EXTENSION) ?: 'mp4';
        $path = "video-consulting/{$assignment->order_number}/{$assignment->id}-recording.{$ext}";

        $spaces->store($path, $response->body(), match ($ext) {
            'mp4'  => 'video/mp4',
            'webm' => 'video/webm',
            default => 'application/octet-stream',
        });

        $slotStart = $assignment->videoConsultingHold?->slot_start;
        $isRealCall = $slotStart === null || now()->greaterThanOrEqualTo($slotStart->copy()->subMinutes(10));

        if ($slotStart === null) {
            Log::warning('DownloadWherebyRecording: no slot_start to gate against, treating as a real call', [
                'assignment_id' => $assignment->id,
            ]);
        }

        DB::transaction(function () use ($assignment, $path, $isRealCall) {
            $fresh = Assignment::lockForUpdate()->findOrFail($assignment->id);

            $update = [
                'video_consulting_recording_path'    => $path,
                'video_consulting_last_recording_id' => $this->recordingId,
            ];

            if ($isRealCall && $fresh->status === Assignment::STATUS_ASSIGNED) {
                $update['status'] = Assignment::STATUS_QC;
                $update['submitted_at'] = now();
            }

            $fresh->update($update);
        });

        Log::info('DownloadWherebyRecording: stored recording', [
            'assignment_id' => $assignment->id,
            'spaces_path'   => $path,
            'recording_id'  => $this->recordingId,
            'routed_to_qc'  => $isRealCall,
        ]);
    }
}
