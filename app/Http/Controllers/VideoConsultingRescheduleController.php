<?php

// v1.1 — 2026-11-23 | store() now rejects any candidate slot that collides with another
//                      active hold/booking for this reader (VideoConsultingAvailabilityService
//                      ::hasConflictingBooking()) — it previously accepted any 5 future,
//                      distinct dates with zero collision check, so a reader could propose (and
//                      a customer could later accept, in the sibling OfferController::submit())
//                      a time that was never actually free.
// v1.0 — 2026-11-09 | Video consulting Phase 3 — reader proposes 5 alternate slots; the
//                      original booking/hold is untouched until the customer actually
//                      responds (see Public\VideoConsultingRescheduleOfferController).

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\VideoConsultingRescheduleOffer;
use App\Services\VideoConsultingAvailabilityService;
use App\Services\VideoConsultingNotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class VideoConsultingRescheduleController extends Controller
{
    public function create(Assignment $assignment)
    {
        $this->authorize('manageVideoConsultingBooking', $assignment);

        return view('video-consulting-reschedule.create', compact('assignment'));
    }

    public function store(Request $request, Assignment $assignment, VideoConsultingNotificationService $notifier, VideoConsultingAvailabilityService $availability)
    {
        $this->authorize('manageVideoConsultingBooking', $assignment);

        $data = $request->validate([
            'slots'   => ['required', 'array', 'size:5'],
            'slots.*' => ['required', 'date', 'after:now', 'distinct'],
        ]);

        $hold = $assignment->videoConsultingHold;
        if ($hold) {
            $durationMinutes = $hold->slot_start->diffInMinutes($hold->slot_end);

            foreach ($data['slots'] as $index => $slot) {
                $slotStart = Carbon::parse($slot);
                $slotEnd = $slotStart->copy()->addMinutes($durationMinutes);

                if ($availability->hasConflictingBooking($hold->reader_id, $slotStart, $slotEnd, $hold->id)) {
                    return back()
                        ->withErrors(["slots.{$index}" => 'This time conflicts with another booking for this reader — pick a different time.'])
                        ->withInput();
                }
            }
        }

        $offer = VideoConsultingRescheduleOffer::create([
            'assignment_id'   => $assignment->id,
            'token'           => bin2hex(random_bytes(32)),
            'candidate_slots' => $data['slots'],
            'status'          => VideoConsultingRescheduleOffer::STATUS_PENDING,
            'expires_at'      => now()->addHours(48),
        ]);

        $notifier->notifyRescheduleOffer($assignment, $offer);

        return redirect()->route('assignments.show', $assignment)
            ->with('success', $assignment->video_consulting_customer_email
                ? 'Reschedule offer sent to the customer.'
                : 'Reschedule offer created — no customer email is on file for this booking, so nothing was emailed.');
    }
}
