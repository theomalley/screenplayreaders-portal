<?php

// v1.0 — 2026-05-30 | Admin approval/rejection of pending bio and photo changes

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;

class AdminApprovalController extends Controller
{
    public function approveBio(User $user): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $profile = $this->profile($user);

        if ($profile && $profile->bio_pending !== null) {
            $profile->update(['bio' => $profile->bio_pending, 'bio_pending' => null]);
        }

        return back()->with('success', 'Bio approved.');
    }

    public function rejectBio(User $user): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $profile = $this->profile($user);

        if ($profile) {
            $profile->update(['bio_pending' => null]);
        }

        return back()->with('success', 'Bio change rejected.');
    }

    public function approvePhoto(User $user): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $profile = $this->profile($user);

        if ($profile && $profile->photo_pending) {
            if ($profile->photo) {
                Storage::disk('public')->delete($profile->photo);
            }
            $profile->update(['photo' => $profile->photo_pending, 'photo_pending' => null]);
        }

        return back()->with('success', 'Photo approved.');
    }

    public function rejectPhoto(User $user): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $profile = $this->profile($user);

        if ($profile && $profile->photo_pending) {
            Storage::disk('public')->delete($profile->photo_pending);
            $profile->update(['photo_pending' => null]);
        }

        return back()->with('success', 'Photo change rejected.');
    }

    private function profile(User $user)
    {
        return $user->isReader() ? $user->readerProfile : $user->editorProfile;
    }
}
