<?php

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
use Illuminate\View\View;

class VideoConsultingCallController extends Controller
{
    public function showPublic(string $token): View
    {
        $assignment = Assignment::where('video_consulting_call_token', $token)->firstOrFail();

        return view('video-consulting.call-public', [
            'assignment'     => $assignment,
            'counterpart'    => $this->readerDisplayName($assignment),
        ]);
    }

    public function showForReader(Assignment $assignment): View
    {
        $this->authorize('joinVideoConsultingCall', $assignment);

        return view('video-consulting.call', [
            'assignment'  => $assignment,
            'counterpart' => $this->customerDisplayName($assignment),
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
