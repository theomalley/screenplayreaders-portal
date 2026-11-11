<?php

// v1.1 — 2026-11-11 | Pass admin-editable Setting::getCallPageSettings() (heading/consent/
//                      not-ready text + logo) into both views, and add showPreview() — a public,
//                      no-data preview of the customer page for admins testing copy/logo changes
//                      from the theme's Tools -> SR Video Consulting page, with no real Assignment
//                      involved.
// v1.0 — 2026-11-11 | Video consulting Phase 7 (partial) — portal-hosted "Join Call" page.
//                      Replaces handing the raw Whereby URL directly to either party (see
//                      VideoConsultingCallLinkMail and assignments/show.blade.php's old inline
//                      link). Public token route for the customer (no login, mirrors
//                      VideoConsultingRescheduleOfferController's pattern), authenticated route
//                      for the reader (gated by AssignmentPolicy::joinVideoConsultingCall()).
//                      The full polished Phase 7 treatment (countdown, richer styling) is still
//                      future work — this is the minimum viable intermediary page.

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Setting;
use Illuminate\View\View;

class VideoConsultingCallController extends Controller
{
    public function showPublic(string $token): View
    {
        $assignment = Assignment::where('video_consulting_call_token', $token)->firstOrFail();

        return view('video-consulting.call-public', [
            'assignment'       => $assignment,
            'counterpart'      => $this->readerDisplayName($assignment),
            'callPageSettings' => Setting::getCallPageSettings(),
        ]);
    }

    public function showForReader(Assignment $assignment): View
    {
        $this->authorize('joinVideoConsultingCall', $assignment);

        return view('video-consulting.call', [
            'assignment'       => $assignment,
            'counterpart'      => $this->customerDisplayName($assignment),
            'callPageSettings' => Setting::getCallPageSettings(),
        ]);
    }

    /** Public, no-login style preview — synthetic sample data only, never a real Assignment. */
    public function showPreview(): View
    {
        $slotStart = now()->addDays(2)->setTime(14, 0);

        $assignment = (object) [
            'script_title'        => 'The Great American Screenplay (Sample)',
            'order_number'        => 'SAMPLE-0001',
            'whereby_room_url'    => '#',
            'videoConsultingHold' => (object) [
                'slot_start' => $slotStart,
                'slot_end'   => $slotStart->copy()->addMinutes(60),
            ],
        ];

        return view('video-consulting.call-public', [
            'assignment'       => $assignment,
            'counterpart'      => 'Jane Reader',
            'callPageSettings' => Setting::getCallPageSettings(),
        ]);
    }

    private function readerDisplayName(Assignment $assignment): string
    {
        $reader = $assignment->assignedReader;
        $profile = $reader?->readerProfile;

        if ($profile?->first_name || $profile?->last_name) {
            return trim("{$profile->first_name} {$profile->last_name}");
        }

        return $reader?->name ?? 'your reader';
    }

    private function customerDisplayName(Assignment $assignment): string
    {
        $name = trim((string) $assignment->writer_name);

        return ($name === '' || str_starts_with($name, 'N/A')) ? 'the customer' : $name;
    }
}
