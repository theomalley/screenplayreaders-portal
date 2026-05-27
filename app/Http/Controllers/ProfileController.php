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
                    'phone'                  => $validated['phone'] ?? null,
                    'sms_notifications'      => $request->boolean('sms_notifications'),
                    'sms_notify_any'         => $request->boolean('sms_notify_any'),
                    'sms_notify_rush'        => $request->boolean('sms_notify_rush'),
                    'sms_notify_requests'    => $request->boolean('sms_notify_requests'),
                    'email_notifications'    => $request->boolean('email_notifications'),
                    'email_notify_any'       => $request->boolean('email_notify_any'),
                    'email_notify_rush'      => $request->boolean('email_notify_rush'),
                    'email_notify_requests'  => $request->boolean('email_notify_requests'),
                ]
            );
        }

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    public function uploadPhoto(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->isAdminOrEditor(), 403);

        $request->validate(['photo' => 'required|image|mimes:jpeg,jpg,png,webp|max:4096']);

        $user    = $request->user();
        $profile = $user->editorProfile;

        if ($profile?->photo) {
            Storage::disk('public')->delete($profile->photo);
        }

        $path = $request->file('photo')->store('editor-photos', 'public');

        $user->editorProfile()->updateOrCreate(
            ['user_id' => $user->id],
            ['photo'   => $path]
        );

        return back()->with('status', 'photo-updated');
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
