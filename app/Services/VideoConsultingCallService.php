<?php

// v1.1 — 2026-11-09 | Video consulting Phase 6 — email the customer the Whereby link once the
//                      room is created. Phase 7's polished call page doesn't exist yet, so this
//                      is the only way the customer ever learns the room exists.
// v1.0 — 2026-11-07 | Video consulting Phase 2 — creates the Whereby room for a
//                      video-consulting Assignment once a reader accepts it. Called from
//                      exactly one place (AssignmentController::accept()) so both a real
//                      future booking and an admin test-booking go through the identical path.

namespace App\Services;

use App\Models\Assignment;
use Illuminate\Support\Facades\Log;

class VideoConsultingCallService
{
    public function __construct(
        private readonly WherebyService $whereby,
        private readonly VideoConsultingNotificationService $notifications,
    ) {
    }

    public function createRoomFor(Assignment $assignment): void
    {
        if ($assignment->whereby_room_url) {
            return; // already created — idempotent
        }

        $slotEnd = $assignment->videoConsultingHold?->slot_end;
        if (! $slotEnd) {
            Log::error('VideoConsultingCallService: assignment has no hold/slot_end, cannot size the room', [
                'assignment_id' => $assignment->id,
            ]);
            return;
        }

        $room = $this->whereby->createRoom($slotEnd);

        $assignment->update([
            'whereby_room_url'  => $room['roomUrl'],
            'whereby_room_name' => $room['roomName'],
        ]);

        Log::info('VideoConsultingCallService: room created', [
            'assignment_id' => $assignment->id,
            'room_name'     => $room['roomName'],
        ]);

        $this->notifications->notifyCustomerCallLinkReady($assignment->fresh());
    }
}
