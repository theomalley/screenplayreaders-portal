<?php

// v1.1 — 2026-11-23 | submit() now re-validates the chosen slot for a reader collision
//                      (VideoConsultingAvailabilityService::hasConflictingBooking()) before
//                      committing it to the hold — it previously overwrote slot_start with
//                      ZERO check, so an unrelated booking taking that exact time between when
//                      the offer was created and when the customer accepted it would silently
//                      double-book the reader. Also changes the "no hold on file" abort from a
//                      500 (an expected business condition, not a server error) to a 409.
// v1.0 — 2026-11-09 | Video consulting Phase 3 — public customer-facing reschedule link (no
//                      auth required), mirroring FollowupFormController's token pattern.

namespace App\Http\Controllers;

use App\Models\VideoConsultingRescheduleOffer;
use App\Services\VideoConsultingAvailabilityService;
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

    public function submit(Request $request, string $token, VideoConsultingCallService $callService, VideoConsultingNotificationService $notifier, VideoConsultingAvailabilityService $availability): RedirectResponse
    {
        $offer = VideoConsultingRescheduleOffer::where('token', $token)->firstOrFail();

        abort_if($offer->status === VideoConsultingRescheduleOffer::STATUS_ACCEPTED, 410, 'A new time has already been picked for this call.');
        abort_if($offer->isExpired(), 410, 'This reschedule link has expired — please contact us directly.');

        $data = $request->validate([
            'chosen_slot' => ['required', 'date', 'in:' . implode(',', $offer->candidate_slots)],
        ]);

        $assignment = $offer->assignment;
        $hold       = $assignment->videoConsultingHold;

        // An expected business condition (nothing was ever reserved for this booking), not a
        // server error — 409 Conflict, not abort(500).
        abort_unless($hold, 409, 'This booking has no reserved slot on file.');

        $durationMinutes = $hold->slot_start->diffInMinutes($hold->slot_end);
        $newStart        = Carbon::parse($data['chosen_slot']);
        $newEnd          = $newStart->copy()->addMinutes($durationMinutes);

        // Re-check for a collision at commit time, not just when the offer was created — time
        // has passed since the reader proposed this slot, and an unrelated checkout could have
        // taken it in the meantime.
        if ($availability->hasConflictingBooking($hold->reader_id, $newStart, $newEnd, $hold->id)) {
            abort(409, 'That time is no longer available — please contact us to pick another one.');
        }

        $hold->update([
            'slot_start' => $newStart,
            'slot_end'   => $newEnd,
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
