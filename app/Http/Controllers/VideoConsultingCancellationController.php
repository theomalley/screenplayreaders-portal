<?php

// v1.0 — 2026-11-09 | Video consulting Phase 3 — reader-initiated cancellation. Distinct from
//                      AssignmentController::cancel() (which "returns to the pool" for a
//                      reassignable coverage assignment) — a video-consulting booking is tied
//                      to a specific customer/slot, so cancelling it ends the booking outright.

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Services\VideoConsultingNotificationService;
use Illuminate\Http\Request;

class VideoConsultingCancellationController extends Controller
{
    public function store(Request $request, Assignment $assignment, VideoConsultingNotificationService $notifier)
    {
        $this->authorize('cancelVideoConsulting', $assignment);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $assignment->update([
            'status'              => Assignment::STATUS_CANCELLED,
            'cancellation_reason' => ($data['reason'] ?? '') ?: 'Cancelled by reader.',
        ]);

        $notifier->notifyCancellation($assignment->fresh());

        return redirect()->route('assignments.index')->with('success', 'Call cancelled.');
    }
}
