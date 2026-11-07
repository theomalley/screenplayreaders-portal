<?php

namespace App\Jobs;

use App\Models\Assignment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ResetTestAssignment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $assignmentId) {}

    public function handle(): void
    {
        $assignment = Assignment::find($this->assignmentId);

        if (! $assignment || ! $assignment->is_test) {
            return;
        }

        if ($assignment->status !== Assignment::STATUS_COMPLETED) {
            // Already reset or changed — nothing to do
            return;
        }

        // Delete any coverage submission
        $assignment->coverageSubmission?->delete();

        // Video consulting: the hold row is spent test data (converted_assignment_id would
        // auto-null via the FK's nullOnDelete only if the Assignment itself were deleted,
        // which it isn't here) — drop it so a fresh one is created next time this test
        // booking is accepted.
        if ($assignment->video_consulting_hold_id) {
            \App\Models\VideoConsultingHold::where('id', $assignment->video_consulting_hold_id)->delete();
        }

        $assignment->update([
            'status'                    => Assignment::STATUS_UNASSIGNED,
            'assigned_reader_id'        => null,
            'accepted_at'               => null,
            'submitted_at'              => null,
            'completed_at'              => null,
            'reader_paid_at'            => null,
            'helpscout_draft_sent_at'   => null,
            'drive_coverage_doc_id'     => null,
            'drive_coverage_pdf_id'     => null,
            'needs_attention_notes'     => null,
            'unassigned_at'             => now(),
            'video_consulting_hold_id'          => null,
            'whereby_room_url'                  => null,
            'whereby_room_name'                 => null,
            'video_consulting_recording_path'   => null,
            'video_consulting_transcript'       => null,
        ]);

        Log::info('Test assignment auto-reset', ['assignment_id' => $this->assignmentId]);
    }
}
