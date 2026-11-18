<?php

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
use App\Models\ReaderVideoConsultingRate;
use App\Models\VideoConsultingDuration;
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

        $data  = $request->validate(['bio' => 'nullable|string|max:5000']);
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
            $profile = $user->readerProfile;
            $path    = $request->file('photo')->store('reader-photos', 'public');
            if ($profile?->photo) Storage::disk('public')->delete($profile->photo);
            $user->readerProfile()->updateOrCreate(['user_id' => $user->id], ['photo' => $path]);
        } else {
            $profile = $user->editorProfile;
            $path    = $request->file('photo')->store('editor-photos', 'public');
            if ($profile?->photo) Storage::disk('public')->delete($profile->photo);
            $user->editorProfile()->updateOrCreate(['user_id' => $user->id], ['photo' => $path]);
        }

        return back()->with('status', 'photo-updated');
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
