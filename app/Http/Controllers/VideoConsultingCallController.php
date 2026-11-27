<?php

// v1.5 — 2026-11-27 | Add extendCall() (reader-initiated, capped at MAX_EXTEND_MINUTES past the
//                      scheduled end) and status() (public, token-gated poll target so the
//                      customer's independently-loaded page picks up a reader's extension).
//                      showPublic()'s link-expiry check now keys off
//                      Assignment::effectiveVideoConsultingEndAt() instead of the hold's raw
//                      slot_end, so extending the call also extends the public link. Also adds
//                      showPreviewReader() — an admin-only synthetic-data preview of the
//                      reader-styled call page, mirroring showPreview()'s customer-styled one.
// v1.4 — 2026-11-27 | The shared call-details partial now embeds the Whereby room directly
//                      (via the <whereby-embed> web component) instead of linking out to a new
//                      tab, adds a client-side-only countdown timer (no server round-trip, never
//                      ends the call itself), and shows each side's first name. The reader's page
//                      also gets a private scratch-notes panel (localStorage only, no new column
//                      or route). No controller changes were needed — this is view-only.
// v1.3 — 2026-11-23 | showPublic() now expires the public call link — it used to authorize
//                      solely via the bearer token with no time bound at all, so a leaked/
//                      forwarded link stayed valid forever. Expires at scheduled slot_end +
//                      LINK_EXPIRY_GRACE_HOURS (4h — long enough for a legitimate late join or
//                      a dropped-connection reconnect, short enough that an old link is useless
//                      to anyone well after the call). Falls open (never blocks) if the
//                      assignment has no hold/slot_end to compare against.
// v1.2 — 2026-11-19 | showPreview()'s synthetic $assignment now sets assignedReader => null so
//                      the shared call-details partial's audio-only lookup
//                      ($assignment->assignedReader?->readerProfile?->video_consulting_call_mode)
//                      doesn't warn on an undefined property for this non-Eloquent stub.
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
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class VideoConsultingCallController extends Controller
{
    /** How long after the call's effective end the public link keeps working. */
    private const LINK_EXPIRY_GRACE_HOURS = 4;

    /** Each click on "Extend" pushes the effective end this many minutes further out. */
    private const EXTEND_INCREMENT_MINUTES = 5;

    /** Total extension is capped this many minutes past the originally scheduled end. */
    private const MAX_EXTEND_MINUTES = 30;

    public function showPublic(string $token): View
    {
        $assignment = Assignment::where('video_consulting_call_token', $token)->firstOrFail();

        $effectiveEnd = $assignment->effectiveVideoConsultingEndAt();
        if ($effectiveEnd && now()->greaterThan($effectiveEnd->copy()->addHours(self::LINK_EXPIRY_GRACE_HOURS))) {
            abort(410, 'This call link has expired.');
        }

        return view('video-consulting.call-public', [
            'assignment'       => $assignment,
            'counterpart'      => $this->readerDisplayName($assignment),
            'callPageSettings' => Setting::getCallPageSettings(),
            'statusUrl'        => route('video-consulting-call.status', $token),
        ]);
    }

    public function showForReader(Assignment $assignment): View
    {
        $this->authorize('joinVideoConsultingCall', $assignment);

        return view('video-consulting.call', [
            'assignment'       => $assignment,
            'counterpart'      => $this->customerDisplayName($assignment),
            'callPageSettings' => Setting::getCallPageSettings(),
            'extendUrl'        => route('video-consulting-call.extend', $assignment),
        ]);
    }

    /** Reader (or admin/editor) pushes the call's effective end further out, capped. */
    public function extendCall(Assignment $assignment): JsonResponse
    {
        $this->authorize('joinVideoConsultingCall', $assignment);

        $slotEnd = $assignment->videoConsultingHold?->slot_end;
        abort_if(! $slotEnd, 422, 'This call has no scheduled end to extend.');

        $base = $assignment->effectiveVideoConsultingEndAt() ?? $slotEnd;
        $capAt = $slotEnd->copy()->addMinutes(self::MAX_EXTEND_MINUTES);
        $next = min($base->copy()->addMinutes(self::EXTEND_INCREMENT_MINUTES), $capAt);

        if ($next->greaterThan($base)) {
            $assignment->update(['video_consulting_extended_until' => $next]);
        }

        return response()->json([
            'effective_end' => $next->toIso8601String(),
            'capped'        => $next->greaterThanOrEqualTo($capAt),
        ]);
    }

    /** Public, token-gated poll target — lets the customer's page pick up a reader's extension. */
    public function status(string $token): JsonResponse
    {
        $assignment = Assignment::where('video_consulting_call_token', $token)->firstOrFail();

        return response()->json([
            'effective_end' => $assignment->effectiveVideoConsultingEndAt()?->toIso8601String(),
        ]);
    }

    /** Public, no-login style preview — synthetic sample data only, never a real Assignment. */
    public function showPreview(): View
    {
        return view('video-consulting.call-public', [
            'assignment'       => $this->syntheticPreviewAssignment(),
            'counterpart'      => 'Jane Reader',
            'callPageSettings' => Setting::getCallPageSettings(),
            'statusUrl'        => null,
        ]);
    }

    /** Admin-only, no real Assignment — lets admins eyeball the reader-styled page too. */
    public function showPreviewReader(): View
    {
        $this->authorize('manage-test-data');

        return view('video-consulting.call', [
            'assignment'       => $this->syntheticPreviewAssignment(),
            'counterpart'      => 'Jane Writer',
            'callPageSettings' => Setting::getCallPageSettings(),
            'extendUrl'        => null,
            'previewMode'      => true,
        ]);
    }

    private function syntheticPreviewAssignment(): object
    {
        $slotStart = now()->addDays(2)->setTime(14, 0);

        return (object) [
            'id'                  => 0,
            'script_title'        => 'The Great American Screenplay (Sample)',
            'order_number'        => 'SAMPLE-0001',
            'whereby_room_url'    => '#',
            'assignedReader'      => null,
            'videoConsultingHold' => (object) [
                'slot_start' => $slotStart,
                'slot_end'   => $slotStart->copy()->addMinutes(60),
            ],
        ];
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
