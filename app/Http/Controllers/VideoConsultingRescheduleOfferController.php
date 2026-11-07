<?php

// v1.0 — 2026-11-09 | Video consulting Phase 3 — public customer-facing reschedule link (no
//                      auth required), mirroring FollowupFormController's token pattern.

namespace App\Http\Controllers;

use App\Models\VideoConsultingRescheduleOffer;
use App\Services\VideoConsultingCallService;
use App\Services\VideoConsultingNotificationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VideoConsultingRescheduleOfferController extends Controller
{
    public function show(string $token): View
    {
        $offer = VideoConsultingRescheduleOffer::where('token', $token)->firstOrFail();

        abort_if($offer->status === VideoConsultingRescheduleOffer::STATUS_ACCEPTED, 410, 'A new time has already been picked for this call.');
        abort_if($offer->isExpired(), 410, 'This reschedule link has expired — please contact us directly.');

        $assignment = $offer->assignment;

        return view('video-consulting-reschedule-offer.show', compact('offer', 'assignment', 'token'));
    }

    public function submit(Request $request, string $token, VideoConsultingCallService $callService, VideoConsultingNotificationService $notifier): RedirectResponse
    {
        $offer = VideoConsultingRescheduleOffer::where('token', $token)->firstOrFail();

        abort_if($offer->status === VideoConsultingRescheduleOffer::STATUS_ACCEPTED, 410, 'A new time has already been picked for this call.');
        abort_if($offer->isExpired(), 410, 'This reschedule link has expired — please contact us directly.');

        $data = $request->validate([
            'chosen_slot' => ['required', 'date', 'in:' . implode(',', $offer->candidate_slots)],
        ]);

        $assignment = $offer->assignment;
        $hold       = $assignment->videoConsultingHold;

        abort_unless($hold, 500, 'This booking has no reserved slot on file.');

        $durationMinutes = $hold->slot_start->diffInMinutes($hold->slot_end);
        $newStart        = Carbon::parse($data['chosen_slot']);

        $hold->update([
            'slot_start' => $newStart,
            'slot_end'   => $newStart->copy()->addMinutes($durationMinutes),
        ]);

        $offer->update([
            'chosen_slot' => $newStart,
            'status'      => VideoConsultingRescheduleOffer::STATUS_ACCEPTED,
        ]);

        // The old Whereby room (if one already existed) was sized for the old end time —
        // regenerate it for the new one. createRoomFor() is idempotent/no-op if whereby_room_url
        // is still set, so clear it first.
        if ($assignment->whereby_room_url) {
            $assignment->update(['whereby_room_url' => null, 'whereby_room_name' => null]);
            $callService->createRoomFor($assignment->fresh());
        }

        $notifier->notifyRescheduleConfirmed($assignment->fresh());

        return redirect()->route('video-consulting-reschedule-offer.confirmed', $token);
    }

    public function confirmed(string $token): View
    {
        $offer = VideoConsultingRescheduleOffer::where('token', $token)->firstOrFail();
        abort_unless($offer->status === VideoConsultingRescheduleOffer::STATUS_ACCEPTED, 404);

        $assignment = $offer->assignment;

        return view('video-consulting-reschedule-offer.confirmed', compact('offer', 'assignment'));
    }
}
