<?php

// v1.12 — 2026-11-27 | updateBio() now enforces wordLimitRule(200) on bio for a reader (self-
//                      service). Not applied to admin/editor self-edits through this same
//                      method — see profile/edit.blade.php's conditional word-count display.
// v1.11 — 2026-11-27 | Add testRoom() — opens (lazily creating/renewing) this reader/admin/
//                      editor's always-available Whereby test room, so they can check their
//                      camera/mic any time without a real booking. See VideoConsultingCallService::
//                      getOrCreateTestRoomUrl().
// v1.10 — 2026-11-23 | updateReaderRequestEligible() now also allows a reader (previously
//                      admin/editor-only) — the Reader Request fee waiver eligibility toggle
//                      is no longer hardcoded true for readers; see
//                      VideoConsultingAccess::eligibleForReaderRequestWaiver().
// v1.9 — 2026-11-23 | updateVideoConsultingListing(): video_consulting_note/
//                      video_consulting_blurb_header now capped at 30 words,
//                      video_consulting_blurb at 70 words (wordLimitRule()); all three now go
//                      to video_consulting_listing_pending for admin approval instead of
//                      saving live, unless the submitter is an admin (same bypass as
//                      updateBio()). video_consulting_call_mode is unaffected (not free text).
// v1.8 — 2026-11-23 | updateVideoConsultingListing() now also accepts
//                      video_consulting_blurb_header/video_consulting_blurb — a reader-authored
//                      header + paragraph replacing the generic bio shown on the Script
//                      Consulting page.
// v1.7 — 2026-11-22 | FIX: uploadPhoto() wrote a reader/editor's self-uploaded "Reader
//                      Icon" straight to the live `photo` column with no approval step —
//                      inconsistent with updateBio()/uploadAboutPhoto(), which route
//                      non-admin submissions through *_pending for admin review, and a real
//                      gap given StaffProfileController's public /staff/{user} API (consumed
//                      by the theme's [sr_staff_photo] shortcode) returns this same `photo`
//                      column by default. Now mirrors uploadAboutPhoto()'s admin-vs-non-admin
//                      branching: admins write straight to `photo`, readers/editors write to
//                      the existing (previously unused) `photo_pending` column instead.
// v1.6 — 2026-11-19 | Removed updateVideoConsultingCoupon() and the reader discount-code
//                      feature — readers will instead note a sale in their existing note field
//                      and lower their own rate.
// v1.5 — 2026-11-19 | Add updateVideoConsultingListing() (note + video/audio call mode) — same
//                      reader-or-admin/editor self-serve shape as updateFavorites().
// v1.4 — 2026-11-18 | updateReaderRequestEligible() corrected: editors do NOT always write
//                      coverage, so they get this toggle too, not just admin — gate changed
//                      from isAdmin() to isAdminOrEditor().
// v1.3 — 2026-11-18 | Add updateReaderRequestEligible() — admin-only toggle for whether this
//                      admin can be counted as one of a customer's requested readers when
//                      booking a video call with them (default off; readers/editors are
//                      always eligible and don't get this control). New
//                      video_consulting_reader_request_eligible column on ReaderProfile.
// v1.2 — 2026-11-18 | updateFavorites() now also allows admin/editor (favorite_genres/
//                      favorite_films are shown on the Script Consulting page for any
//                      role that can appear there, not just readers) — self-edit view now
//                      shows the card for isAdminOrEditor() too, reading from their
//                      ReaderProfile (via $videoConsultingOverrideSource) since $profile is
//                      their EditorProfile for that role, which has no favorites columns.
// v1.1 — 2026-11-18 | Admin/editor can now opt into video consulting from their OWN Profile
//                      page (enable/disable + availability + rate), not just via another admin
//                      editing their profile at /admin/editors/{user}. New
//                      updateVideoConsultingEnabled(); edit() now always surfaces the
//                      override/availability/rate data for admin/editor, not just once already
//                      enabled, since there was previously no self-serve way to flip it on in
//                      the first place. Shares App\Support\VideoConsultingAccess::
//                      updateReaderProfileShell() with EditorProfileController.

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\ReaderAvailabilityRule;
use App\Models\ReaderBlackoutDate;
use App\Models\ReaderProfile;
use App\Models\ReaderVideoConsultingRate;
use App\Models\VideoConsultingDuration;
use App\Services\VideoConsultingCallService;
use App\Support\Html;
use App\Support\VideoConsultingAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $user    = $request->user();
        $profile = $user->isAdminOrEditor() ? $user->editorProfile : $user->readerProfile;

        // video_consulting_min_lead_hours/rate/availability/blackouts always live on
        // ReaderProfile regardless of role — an admin/editor taking calls themselves has no
        // EditorProfile columns for these, so $profile (above) is the wrong source for them.
        //
        // A reader's Rate/Availability cards stay gated on enabledFor() unchanged (whether
        // video consulting is on for them is either the sitewide default or an admin override —
        // nothing on this page itself can flip it). An admin/editor has no sitewide fallback and
        // no other admin-facing page to flip it from except this one, so their data (including
        // the current override, for the enable/disable select) is always included here —
        // otherwise there would be no self-serve way to ever turn it on in the first place.
        $videoConsultingData = [];
        if ($user->isReader() && VideoConsultingAccess::enabledFor($user)) {
            $videoConsultingData = [
                'videoConsultingDurations'    => VideoConsultingDuration::active()->get(),
                'videoConsultingMyRates'      => ReaderVideoConsultingRate::where('reader_id', $user->id)->pluck('rate', 'duration_id'),
                'videoConsultingRules'        => ReaderAvailabilityRule::where('reader_id', $user->id)->get()->keyBy('day_of_week'),
                'videoConsultingBlackouts'    => ReaderBlackoutDate::where('reader_id', $user->id)->orderBy('start_at')->get(),
                'videoConsultingMinLeadHours' => (int) ($profile?->video_consulting_min_lead_hours ?? 0),
                'videoConsultingLeadHoursBounds' => \App\Models\Setting::getVideoConsultingLeadHoursBounds(),
            ];
        } elseif ($user->isAdminOrEditor()) {
            $readerProfile = $user->readerProfile;
            $videoConsultingData = [
                'videoConsultingOverrideSource'  => $readerProfile,
                'videoConsultingDurations'       => VideoConsultingDuration::active()->get(),
                'videoConsultingMyRates'         => ReaderVideoConsultingRate::where('reader_id', $user->id)->pluck('rate', 'duration_id'),
                'videoConsultingRules'           => ReaderAvailabilityRule::where('reader_id', $user->id)->get()->keyBy('day_of_week'),
                'videoConsultingBlackouts'       => ReaderBlackoutDate::where('reader_id', $user->id)->orderBy('start_at')->get(),
                'videoConsultingMinLeadHours'    => (int) ($readerProfile?->video_consulting_min_lead_hours ?? 0),
                'videoConsultingLeadHoursBounds' => \App\Models\Setting::getVideoConsultingLeadHoursBounds(),
            ];
        }

        return view('profile.edit', array_merge([
            'user'    => $user,
            'profile' => $profile,
            'isSelf'  => true,
        ], $videoConsultingData));
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $request->user()->fill($validated);

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        if ($request->user()->isReader()) {
            $request->user()->readerProfile()->updateOrCreate(
                ['user_id' => $request->user()->id],
                [
                    'phone'    => $validated['phone'] ?? null,
                    'timezone' => $validated['timezone'] ?? null,
                ]
            );
        } elseif ($request->user()->isAdminOrEditor()) {
            $request->user()->editorProfile()->updateOrCreate(
                ['user_id' => $request->user()->id],
                ['timezone' => $validated['timezone'] ?? null]
            );
        }

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    public function updateNotifications(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isReader() || $user->isAdmin(), 403);

        if ($user->isReader()) {
            // A reader who has opted into offering video consulting calls (set a rate for at
            // least one duration) must be notified by email when one comes in — forced true
            // here regardless of what was submitted, not just disabled client-side, since the
            // checkbox being un-interactive in the form is a UX signal, not the real guarantee.
            $offersVideoConsulting = \App\Models\ReaderVideoConsultingRate::where('reader_id', $user->id)->exists();

            $user->readerProfile()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'sms_notifications'      => $request->boolean('sms_notifications'),
                    'sms_notify_any'         => $request->boolean('sms_notify_any'),
                    'sms_notify_rush'        => $request->boolean('sms_notify_rush'),
                    'sms_notify_requests'    => $request->boolean('sms_notify_requests'),
                    'email_notifications'    => $request->boolean('email_notifications'),
                    'email_notify_any'       => $request->boolean('email_notify_any'),
                    'email_notify_rush'      => $request->boolean('email_notify_rush'),
                    'email_notify_requests'  => $request->boolean('email_notify_requests'),
                    'email_notify_video_consulting' => $offersVideoConsulting ? true : $request->boolean('email_notify_video_consulting'),
                    'sms_notify_followup'    => $request->boolean('sms_notify_followup'),
                    'notify_only_if_under_capacity' => $request->boolean('notify_only_if_under_capacity'),
                ]
            );
        } else {
            // Admin-only: lets an admin preview the same new-assignment email readers get,
            // for testing deliverability and dialing in the MailerSend template. Editors
            // don't get this — see ReaderNotificationService::notifyOptedInAdmins().
            $user->editorProfile()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'email_notifications'   => $request->boolean('email_notifications'),
                    'email_notify_any'      => $request->boolean('email_notify_any'),
                    'email_notify_rush'     => $request->boolean('email_notify_rush'),
                    'email_notify_requests' => $request->boolean('email_notify_requests'),
                ]
            );
        }

        return Redirect::route('profile.edit')->with('status', 'notifications-updated');
    }

    /**
     * Self-serve enable/disable override for an admin/editor opting into (or out of) taking
     * video consulting calls themselves — mirrors the override EditorProfileController::
     * update() already lets another admin set on their behalf, just reachable from the
     * admin/editor's own Profile page too now. Readers don't get this control here: their
     * tri-state override (follow-sitewide / always-on / always-off) stays admin-only, set from
     * the reader-edit page, not self-serve.
     */
    public function updateVideoConsultingEnabled(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isAdminOrEditor(), 404);

        VideoConsultingAccess::updateReaderProfileShell($user, [
            'video_consulting_enabled' => VideoConsultingAccess::parseOverrideInput($request->input('video_consulting_enabled')),
        ]);

        return Redirect::route('profile.edit')->with('status', 'video-consulting-enabled-updated');
    }

    /**
     * Reader/admin/editor self-serve: whether this person can be counted as one of a
     * customer's requested readers when the customer books a video call with them (the
     * theme's Reader Request fee waiver — see VideoConsultingAccess::
     * eligibleForReaderRequestWaiver()). Defaults to true for every role; updateReaderProfileShell()
     * is a no-op shell-seed for a reader (their readerProfile already exists) so it works
     * unconditionally for all three roles without branching here.
     */
    public function updateReaderRequestEligible(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isReader() || $user->isAdminOrEditor(), 404);

        VideoConsultingAccess::updateReaderProfileShell($user, [
            'video_consulting_reader_request_eligible' => $request->boolean('video_consulting_reader_request_eligible'),
        ]);

        return Redirect::route('profile.edit')->with('status', 'reader-request-eligible-updated');
    }

    /**
     * Opens this reader/admin/editor's always-available Whereby test room (lazily created or
     * renewed on first/next use) so they can check their camera/mic without a real booking.
     * Same role gate as the rest of the Video Consulting Settings card — anyone who can see
     * that card can use this. Uses VideoConsultingAccess::updateReaderProfileShell()'s
     * create-or-fetch so an admin/editor with no ReaderProfile row yet still gets one.
     */
    public function testRoom(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isReader() || $user->isAdminOrEditor(), 403);

        $profile = VideoConsultingAccess::updateReaderProfileShell($user, []);
        $url = app(VideoConsultingCallService::class)->getOrCreateTestRoomUrl($profile);

        return Redirect::away($url);
    }

    /**
     * Reader/admin/editor self-serve: a free-text note shown on the public video-consulting
     * listing even when they currently have no open slots (readers() still returns them in
     * that case — it only ever drops someone whose video_consulting_enabled override is off),
     * whether their calls run as video or audio-only (VideoConsultingAccess::enabledFor()
     * gates whether this card is shown at all, same as the Rate/Availability cards), and the
     * listing header + blurb shown on the Script Consulting page in place of their bio.
     *
     * The three free-text fields (header/blurb/note) go through the same admin-approval gate
     * as bio/photo/about_photo — a non-admin's submission is held in
     * video_consulting_listing_pending (reviewed as one bundle, since they're all submitted
     * from this same form) until an admin approves/rejects it via AdminApprovalController; an
     * admin's own submission goes live immediately, same as updateBio(). call_mode isn't
     * free text and always saves live regardless of role.
     */
    public function updateVideoConsultingListing(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isReader() || $user->isAdminOrEditor(), 403);

        $data = $request->validate([
            'video_consulting_note'         => ['nullable', 'string', 'max:500', self::wordLimitRule(30)],
            'video_consulting_call_mode'    => ['required', 'in:video,audio'],
            'video_consulting_blurb_header' => ['nullable', 'string', 'max:150', self::wordLimitRule(30)],
            'video_consulting_blurb'        => ['nullable', 'string', 'max:1000', self::wordLimitRule(70)],
        ]);

        $note        = trim((string) ($data['video_consulting_note'] ?? '')) ?: null;
        $blurbHeader = trim((string) ($data['video_consulting_blurb_header'] ?? '')) ?: null;
        $blurb       = trim((string) ($data['video_consulting_blurb'] ?? '')) ?: null;

        if ($user->isAdmin()) {
            $payload = [
                'video_consulting_call_mode'    => $data['video_consulting_call_mode'],
                'video_consulting_note'         => $note,
                'video_consulting_blurb_header' => $blurbHeader,
                'video_consulting_blurb'        => $blurb,
            ];
            $status = 'video-consulting-listing-updated';
        } else {
            $payload = [
                'video_consulting_call_mode'               => $data['video_consulting_call_mode'],
                'video_consulting_listing_pending'         => [
                    'video_consulting_note'         => $note,
                    'video_consulting_blurb_header' => $blurbHeader,
                    'video_consulting_blurb'        => $blurb,
                ],
                'video_consulting_listing_rejection_note'  => null,
            ];
            $status = 'video-consulting-listing-pending';
        }

        if ($user->isReader()) {
            $user->readerProfile()->updateOrCreate(['user_id' => $user->id], $payload);
        } else {
            VideoConsultingAccess::updateReaderProfileShell($user, $payload);
        }

        return Redirect::route('profile.edit')->with('status', $status);
    }

    /**
     * Rejects a nullable text value with more than $max whitespace-separated words. Public —
     * ReaderProfileController/EditorProfileController reuse this for the same listing fields
     * when an admin edits another person's Listing directly from the admin-edit page.
     */
    public static function wordLimitRule(int $max): \Closure
    {
        return function (string $attribute, $value, \Closure $fail) use ($max) {
            if ($value === null || $value === '') {
                return;
            }

            $wordCount = count(preg_split('/\s+/', trim((string) $value), -1, PREG_SPLIT_NO_EMPTY));
            if ($wordCount > $max) {
                $fail("The {$attribute} must not be more than {$max} words.");
            }
        };
    }

    public function updateCustomMessage(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isAdminOrEditor() || $user->isReader(), 403);

        $data = $request->validate(['custom_message' => 'nullable|string|max:200']);
        $msg  = isset($data['custom_message']) ? trim($data['custom_message']) ?: null : null;

        if ($user->isReader()) {
            $user->readerProfile()->updateOrCreate(['user_id' => $user->id], ['custom_message' => $msg]);
        } else {
            $user->editorProfile()->updateOrCreate(['user_id' => $user->id], ['custom_message' => $msg]);
        }

        return back()->with('status', 'custom-message-updated');
    }

    public function updateFavorites(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isReader() || $user->isAdminOrEditor(), 403);

        $data = $request->validate([
            'favorite_genres'   => 'nullable|array|max:5',
            'favorite_genres.*' => 'nullable|string|max:60',
            'favorite_films'    => 'nullable|array|max:5',
            'favorite_films.*'  => 'nullable|string|max:60',
        ]);

        $favorites = [
            'favorite_genres' => \App\Models\ReaderProfile::parseFavoritesInput($data['favorite_genres'] ?? []),
            'favorite_films'  => \App\Models\ReaderProfile::parseFavoritesInput($data['favorite_films'] ?? []),
        ];

        // Favorites always live on ReaderProfile. A reader already has that row; an
        // admin/editor may not yet (it's lazily created — see updateVideoConsultingEnabled()
        // above), so the shared shell helper seeds its NOT-NULL identity columns if needed.
        if ($user->isReader()) {
            $user->readerProfile()->updateOrCreate(['user_id' => $user->id], $favorites);
        } else {
            VideoConsultingAccess::updateReaderProfileShell($user, $favorites);
        }

        return back()->with('status', 'favorites-updated');
    }

    public function updateRefreshInterval(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'refresh_interval_seconds' => 'required|integer|min:30|max:3600',
        ]);

        $request->user()->update(['refresh_interval_seconds' => $data['refresh_interval_seconds']]);

        return back()->with('status', 'refresh-interval-updated');
    }

    public function updateSessionTimeoutOverride(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isAdmin(), 403);

        $data = $request->validate([
            'session_timeout_minutes' => 'nullable|integer|min:5|max:1440',
        ]);

        $user->update(['session_timeout_minutes' => $data['session_timeout_minutes'] ?? null]);

        return back()->with('status', 'session-timeout-updated');
    }

    public function updateBio(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isAdminOrEditor() || $user->isReader(), 403);

        $bioRules = ['nullable', 'string', 'max:5000'];
        if ($user->isReader()) {
            $bioRules[] = self::wordLimitRule(200);
        }
        $data  = $request->validate(['bio' => $bioRules]);
        $bio   = $user->isAdmin()
            ? Html::sanitizeBioHtml($data['bio'] ?? null)
            : Html::sanitizeBioPlainText($data['bio'] ?? null);
        $field = $user->isAdmin() ? 'bio' : 'bio_pending';

        $extra = $user->isAdmin() ? [] : ['bio_rejection_note' => null];

        if ($user->isReader()) {
            $user->readerProfile()->updateOrCreate(['user_id' => $user->id], array_merge([$field => $bio], $extra));
        } else {
            $user->editorProfile()->updateOrCreate(['user_id' => $user->id], array_merge([$field => $bio], $extra));
        }

        return back()->with('status', $user->isAdmin() ? 'bio-updated' : 'bio-pending');
    }

    public function uploadPhoto(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isAdminOrEditor() || $user->isReader(), 403);

        $request->validate([
            'photo' => 'required|image|mimes:jpeg,jpg,png,webp|max:8192|dimensions:min_width=600,min_height=600',
        ]);

        if ($user->isReader()) {
            $path    = $request->file('photo')->store('reader-photos', 'public');
            $profile = $user->readerProfile;

            if ($profile?->photo_pending) {
                Storage::disk('public')->delete($profile->photo_pending);
            }
            $user->readerProfile()->updateOrCreate(
                ['user_id' => $user->id],
                ['photo_pending' => $path, 'photo_rejection_note' => null]
            );
        } else {
            $path    = $request->file('photo')->store('editor-photos', 'public');
            $profile = $user->editorProfile;

            if ($user->isAdmin()) {
                if ($profile?->photo) Storage::disk('public')->delete($profile->photo);
                $user->editorProfile()->updateOrCreate(['user_id' => $user->id], ['photo' => $path]);
            } else {
                if ($profile?->photo_pending) {
                    Storage::disk('public')->delete($profile->photo_pending);
                }
                $user->editorProfile()->updateOrCreate(
                    ['user_id' => $user->id],
                    ['photo_pending' => $path, 'photo_rejection_note' => null]
                );
            }
        }

        return back()->with('status', $user->isAdmin() ? 'photo-updated' : 'photo-pending');
    }

    public function uploadAboutPhoto(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isAdminOrEditor() || $user->isReader(), 403);

        $request->validate([
            'about_photo' => 'required|image|mimes:jpeg,jpg,png,webp|max:8192|dimensions:min_width=600,min_height=600',
        ]);

        if ($user->isReader()) {
            $path    = $request->file('about_photo')->store('reader-photos', 'public');
            $profile = $user->readerProfile;

            if ($profile?->about_photo_pending) {
                Storage::disk('public')->delete($profile->about_photo_pending);
            }
            $user->readerProfile()->updateOrCreate(
                ['user_id' => $user->id],
                ['about_photo_pending' => $path, 'about_photo_rejection_note' => null]
            );
        } else {
            $path    = $request->file('about_photo')->store('editor-photos', 'public');
            $profile = $user->editorProfile;

            if ($user->isAdmin()) {
                if ($profile?->about_photo) Storage::disk('public')->delete($profile->about_photo);
                $user->editorProfile()->updateOrCreate(
                    ['user_id' => $user->id],
                    ['about_photo' => $path]
                );
            } else {
                if ($profile?->about_photo_pending) {
                    Storage::disk('public')->delete($profile->about_photo_pending);
                }
                $user->editorProfile()->updateOrCreate(
                    ['user_id' => $user->id],
                    ['about_photo_pending' => $path, 'about_photo_rejection_note' => null]
                );
            }
        }

        return back()->with('status', $user->isAdmin() ? 'about-photo-updated' : 'about-photo-pending');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
