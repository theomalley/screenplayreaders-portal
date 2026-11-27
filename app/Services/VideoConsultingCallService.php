<?php

// v1.3 — 2026-11-27 | Add getOrCreateTestRoomUrl() — a persistent, non-recording Whereby room
//                      per reader/admin/editor for testing camera/mic, stored on ReaderProfile
//                      rather than Assignment. Reused (not recreated) until it's within 7 days
//                      of Whereby's endDate, then silently renewed for another 6 months.
// v1.2 — 2026-11-11 | Video consulting Phase 7 (partial) — generate video_consulting_call_token
//                      alongside the room so both the customer email and the reader's portal
//                      link can point at the new portal-hosted call page instead of handing out
//                      the raw Whereby URL directly. Reused across reschedule's room
//                      regeneration too (token stays stable — only the room is recreated).
// v1.1 — 2026-11-09 | Video consulting Phase 6 — email the customer the Whereby link once the
//                      room is created. Phase 7's polished call page doesn't exist yet, so this
//                      is the only way the customer ever learns the room exists.
// v1.0 — 2026-11-07 | Video consulting Phase 2 — creates the Whereby room for a
//                      video-consulting Assignment once a reader accepts it. Called from
//                      exactly one place (AssignmentController::accept()) so both a real
//                      future booking and an admin test-booking go through the identical path.

namespace App\Services;

use App\Models\Assignment;
use App\Models\ReaderProfile;
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
            'whereby_room_url'            => $room['roomUrl'],
            'whereby_room_name'           => $room['roomName'],
            'video_consulting_call_token' => $assignment->video_consulting_call_token ?? bin2hex(random_bytes(32)),
        ]);

        Log::info('VideoConsultingCallService: room created', [
            'assignment_id' => $assignment->id,
            'room_name'     => $room['roomName'],
        ]);

        $this->notifications->notifyCustomerCallLinkReady($assignment->fresh());
    }

    /**
     * This reader/admin/editor's always-available Whereby room for checking their own
     * camera/mic — reused across visits (idempotent, like createRoomFor()) and renewed for
     * another 6 months once it's within a week of Whereby's endDate, rather than created fresh
     * on every click. No recording — only the owner ever joins it.
     */
    public function getOrCreateTestRoomUrl(ReaderProfile $profile): string
    {
        if ($profile->test_room_url && $profile->test_room_expires_at?->isAfter(now()->addDays(7))) {
            return $profile->test_room_url;
        }

        $endDate = now()->addMonths(6);
        $room = $this->whereby->createRoom($endDate, recording: false);

        $profile->update([
            'test_room_url'        => $room['roomUrl'],
            'test_room_name'       => $room['roomName'],
            'test_room_expires_at' => $endDate,
        ]);

        Log::info('VideoConsultingCallService: test room created/renewed', [
            'reader_profile_id' => $profile->id,
            'room_name'          => $room['roomName'],
        ]);

        return $room['roomUrl'];
    }
}
