<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
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
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
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
        abort_unless($user->isReader(), 403);

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
                'email_notify_followup'  => $request->boolean('email_notify_followup'),
                'sms_notify_followup'    => $request->boolean('sms_notify_followup'),
            ]
        );

        return Redirect::route('profile.edit')->with('status', 'notifications-updated');
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

    public function updateBio(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isAdminOrEditor() || $user->isReader(), 403);

        $data  = $request->validate(['bio' => 'nullable|string|max:5000']);
        $bio   = $data['bio'] ?? null;
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
            $folder = 'reader-photos';
            $path   = $request->file('photo')->store($folder, 'public');

            if ($user->isAdmin()) {
                $profile = $user->readerProfile;
                if ($profile?->photo) Storage::disk('public')->delete($profile->photo);
                $user->readerProfile()->updateOrCreate(['user_id' => $user->id], ['photo' => $path]);
            } else {
                $profile = $user->readerProfile;
                if ($profile?->photo_pending) Storage::disk('public')->delete($profile->photo_pending);
                $user->readerProfile()->updateOrCreate(['user_id' => $user->id], ['photo_pending' => $path, 'photo_rejection_note' => null]);
            }
        } else {
            $folder = 'editor-photos';
            $path   = $request->file('photo')->store($folder, 'public');

            if ($user->isAdmin()) {
                $profile = $user->editorProfile;
                if ($profile?->photo) Storage::disk('public')->delete($profile->photo);
                $user->editorProfile()->updateOrCreate(['user_id' => $user->id], ['photo' => $path, 'initials' => '', 'first_name' => '', 'last_name' => '']);
            } else {
                $profile = $user->editorProfile;
                if ($profile?->photo_pending) Storage::disk('public')->delete($profile->photo_pending);
                $user->editorProfile()->updateOrCreate(['user_id' => $user->id], ['photo_pending' => $path, 'photo_rejection_note' => null]);
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
