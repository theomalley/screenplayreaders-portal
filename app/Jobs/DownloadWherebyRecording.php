<?php

// v1.5 — 2026-11-23 | (1) handle() now takes an atomic Cache::lock() keyed on recordingId
//                      BEFORE the expensive Whereby download/Spaces upload, and re-checks
//                      idempotency while holding it — not just before the final write. The old
//                      idempotency check was a plain read taken well before the only lock in
//                      the method (which only guarded the final write), so a webhook-retried
//                      duplicate dispatch could let both instances do the expensive work (and
//                      both write to Spaces) before either claimed it. (2) Add failed() —
//                      after both retries exhaust, the assignment used to sit at
//                      STATUS_ASSIGNED forever with no signal that the recording never made
//                      it to QC. Mirrors DeliverRegistrationEmail's shape (commit e1fc947):
//                      mark a FAILED-equivalent state + log, instead of leaving it silently stuck.
//                      Flips to STATUS_NEEDS_ATTENTION (not a cancellation — an admin can still
//                      review/approve from there) only if nothing else already moved the
//                      assignment on in the meantime.
// v1.4 — 2026-11-13 | Dispatches TranscribeVideoConsultingRecording once the recording is
//                      stored, so every recording gets submitted to AssemblyAI for transcription
//                      (previously the only path to a transcript was a human pasting one in
//                      manually on the QC page).
// v1.3 — 2026-11-11 | Storage path now includes the recordingId. It previously keyed only on
//                      assignment id, so a second recording for the same assignment (e.g. the
//                      pair reconnects after an earlier real-but-early session already completed
//                      the booking) would silently overwrite the first recording's file in Spaces
//                      — destroying it. Each recording now gets its own file; also logs a warning
//                      when a recording lands for an assignment that already left STATUS_ASSIGNED,
//                      since the single `video_consulting_recording_path` column only ever points
//                      at the latest one and an earlier file could otherwise go unnoticed.
// v1.2 — 2026-11-11 | Removed the slot_start timing gate on QC routing added in v1.1. Once
//                      WherebyService switched recording.startTrigger to
//                      'automatic-2nd-participant' (same day), a recording can only ever exist
//                      if two real people were actually in the room together — there's no more
//                      "was this a premature solo peek" ambiguity to guess at with timing. Per
//                      the user: any real recording MUST reach QC review (contact-info/transcript
//                      monitoring happens there) even if the two parties happened to connect well
//                      before the scheduled slot — silently skipping that review was a worse risk
//                      than a status/timestamp being technically "early".
// v1.1 — 2026-11-11 | Fix: an early/test join (recording auto-starts the instant anyone enters
//                      the Whereby room, with no time gate) was being treated as "the call
//                      happened" — flipping STATUS_ASSIGNED -> STATUS_QC days before the real
//                      scheduled call, and permanently blocking the real recording from ever
//                      being processed (idempotency keyed on "a path already exists", not on
//                      which recording). Now dedupes by recordingId (video_consulting_
//                      last_recording_id) so a later, different recording always overwrites the
//                      stored path.
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
use Illuminate\Support\Facades\Cache;
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

        // Claim this recordingId BEFORE doing any expensive external work (the Whereby
        // download, the Spaces upload) — not just before the final write. The idempotency
        // check below used to be a plain read taken here, with the only lock in the method
        // wrapping just the final DB write; a webhook-retried duplicate dispatch for the same
        // recordingId could get past that read on both instances and both do the full
        // download/upload before either one's write reconciled things. A non-blocking
        // Cache::lock() closes that window: whichever instance doesn't get the lock returns
        // immediately instead of also doing the work. Released in finally{} so a legitimate
        // retry (new job attempt, after this one failed) can still acquire it.
        $lock = Cache::lock("video-consulting-recording-download:{$this->recordingId}", $this->timeout + 30);

        if (! $lock->get()) {
            Log::info('DownloadWherebyRecording: another worker already holds the lock for this recordingId — skipping', [
                'assignment_id' => $assignment->id,
                'recording_id'  => $this->recordingId,
            ]);
            return;
        }

        try {
            // Re-check idempotency now that we hold the lock — a prior holder may have
            // already finished this exact recording while we were waiting.
            $assignment->refresh();
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
            $safeRecordingId = preg_replace('/[^A-Za-z0-9_-]/', '', $this->recordingId);
            $path = "video-consulting/{$assignment->order_number}/{$assignment->id}-{$safeRecordingId}.{$ext}";

            $spaces->store($path, $response->body(), match ($ext) {
                'mp4'  => 'video/mp4',
                'webm' => 'video/webm',
                default => 'application/octet-stream',
            });

            if ($assignment->status !== Assignment::STATUS_ASSIGNED && $assignment->video_consulting_last_recording_id) {
                Log::warning('DownloadWherebyRecording: a later recording arrived for an assignment that already left STATUS_ASSIGNED — the stored recording_path column will only point at this newest file, the earlier one is kept in Spaces but no longer referenced by this column', [
                    'assignment_id'          => $assignment->id,
                    'previous_recording_id'  => $assignment->video_consulting_last_recording_id,
                    'previous_recording_path' => $assignment->video_consulting_recording_path,
                    'new_recording_id'       => $this->recordingId,
                    'new_recording_path'     => $path,
                ]);
            }

            DB::transaction(function () use ($assignment, $path) {
                $fresh = Assignment::lockForUpdate()->findOrFail($assignment->id);

                $update = [
                    'video_consulting_recording_path'    => $path,
                    'video_consulting_last_recording_id' => $this->recordingId,
                ];

                if ($fresh->status === Assignment::STATUS_ASSIGNED) {
                    $update['status'] = Assignment::STATUS_QC;
                    $update['submitted_at'] = now();
                }

                $fresh->update($update);
            });

            Log::info('DownloadWherebyRecording: stored recording and routed to QC', [
                'assignment_id' => $assignment->id,
                'spaces_path'   => $path,
                'recording_id'  => $this->recordingId,
            ]);

            TranscribeVideoConsultingRecording::dispatch($assignment->id, $this->recordingId, $path);
        } finally {
            $lock->release();
        }
    }

    /**
     * Called by the queue worker once both tries are exhausted. Without this, a permanently
     * failed download/upload left the assignment sitting at STATUS_ASSIGNED forever with no
     * signal that the recording never reached QC — mirrors DeliverRegistrationEmail's fix
     * (commit e1fc947): log it, and mark a FAILED-equivalent state (STATUS_NEEDS_ATTENTION;
     * there's no STATUS_FAILED on Assignment) so it surfaces on the QC "Needs Attention" tab.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('DownloadWherebyRecording: permanently failed after all retries', [
            'assignment_id' => $this->assignmentId,
            'recording_id'  => $this->recordingId,
            'error'         => $exception->getMessage(),
        ]);

        $assignment = Assignment::find($this->assignmentId);
        if (! $assignment || $assignment->status !== Assignment::STATUS_ASSIGNED) {
            return;
        }

        if ($assignment->video_consulting_last_recording_id === $this->recordingId && $assignment->video_consulting_recording_path) {
            return; // a retry (or a concurrent attempt) actually completed before this failure signal landed
        }

        $assignment->update([
            'status'                                  => Assignment::STATUS_NEEDS_ATTENTION,
            'video_consulting_needs_attention_reason' => 'recording_download_failed',
            'needs_attention_notes'                   => "Automatic recording download/processing failed after retries (recording {$this->recordingId}) — download it from Whereby and route to QC manually.",
        ]);
    }
}
