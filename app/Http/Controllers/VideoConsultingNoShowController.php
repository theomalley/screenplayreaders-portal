<?php

// v1.0 — 2026-11-09 | Video consulting Phase 3 — reader-reported no-show, admin confirm/dismiss.
//                      No auto tier-suspension (see the Phase 3 plan) — confirming just flips
//                      status, zeroes pay, and logs it; the admin still edits the reader's
//                      tiers by hand on the reader-edit page if suspension is warranted.

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Services\VideoConsultingNotificationService;

class VideoConsultingNoShowController extends Controller
{
    public function report(Assignment $assignment, VideoConsultingNotificationService $notifier)
    {
        $this->authorize('manageVideoConsultingBooking', $assignment);

        $assignment->update([
            'status'                                  => Assignment::STATUS_NEEDS_ATTENTION,
            'video_consulting_needs_attention_reason'  => 'no_show_reported_by_reader',
            'needs_attention_notes'                    => 'No-show reported by reader — customer did not join the call.',
        ]);

        $notifier->notifyNoShowReported($assignment->fresh());

        return redirect()->route('assignments.index')->with('success', 'No-show reported. An admin will review it.');
    }

    public function confirm(Assignment $assignment)
    {
        $this->authorize('resolveVideoConsultingAttention', $assignment);

        $assignment->update([
            'status'                                  => Assignment::STATUS_CANCELLED,
            'cancellation_reason'                      => 'No-show confirmed by admin.',
            'pay_rate'                                 => 0,
            'video_consulting_needs_attention_reason'  => null,
        ]);

        return redirect()->route('qc.index')->with('success', 'No-show confirmed and reader pay zeroed. Remember to update the reader\'s tiers by hand if suspension is warranted.');
    }

    public function dismiss(Assignment $assignment)
    {
        $this->authorize('resolveVideoConsultingAttention', $assignment);

        $assignment->update([
            'status'                                  => Assignment::STATUS_ASSIGNED,
            'video_consulting_needs_attention_reason'  => null,
            'needs_attention_notes'                    => null,
        ]);

        return redirect()->route('qc.index')->with('success', 'Dismissed — booking is back to Assigned.');
    }
}
