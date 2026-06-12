<?php

// v1.3 — 2026-06-12 | Sanitize bio HTML when approving bio_pending -> bio
// v1.2 — 2026-06-05 | Add approveAboutPhoto / rejectAboutPhoto
// v1.1 — 2026-05-30 | Return JSON for XHR; accept rejection note; store bio/photo rejection notes
// v1.0 — 2026-05-30 | Admin approval/rejection of pending bio and photo changes

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Html;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminApprovalController extends Controller
{
    public function approveBio(User $user): JsonResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $profile = $this->profile($user);

        if ($profile && $profile->bio_pending !== null) {
            $profile->update([
                'bio'              => Html::sanitizeBioHtml($profile->bio_pending),
                'bio_pending'      => null,
                'bio_rejection_note' => null,
            ]);
        }

        return response()->json(['status' => 'approved']);
    }

    public function rejectBio(Request $request, User $user): JsonResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $data    = $request->validate(['note' => 'nullable|string|max:1000']);
        $profile = $this->profile($user);

        if ($profile) {
            $profile->update([
                'bio_pending'        => null,
                'bio_rejection_note' => $data['note'] ?? null,
            ]);
        }

        return response()->json(['status' => 'rejected']);
    }

    public function approvePhoto(User $user): JsonResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $profile = $this->profile($user);

        if ($profile && $profile->photo_pending) {
            if ($profile->photo) {
                Storage::disk('public')->delete($profile->photo);
            }
            $profile->update([
                'photo'                => $profile->photo_pending,
                'photo_pending'        => null,
                'photo_rejection_note' => null,
            ]);
        }

        return response()->json(['status' => 'approved']);
    }

    public function rejectPhoto(Request $request, User $user): JsonResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $data    = $request->validate(['note' => 'nullable|string|max:1000']);
        $profile = $this->profile($user);

        if ($profile && $profile->photo_pending) {
            Storage::disk('public')->delete($profile->photo_pending);
            $profile->update([
                'photo_pending'        => null,
                'photo_rejection_note' => $data['note'] ?? null,
            ]);
        }

        return response()->json(['status' => 'rejected']);
    }

    public function approveAboutPhoto(User $user): JsonResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $profile = $this->profile($user);

        if ($profile && $profile->about_photo_pending) {
            if ($profile->about_photo) {
                Storage::disk('public')->delete($profile->about_photo);
            }
            $profile->update([
                'about_photo'                => $profile->about_photo_pending,
                'about_photo_pending'        => null,
                'about_photo_rejection_note' => null,
            ]);
        }

        return response()->json(['status' => 'approved']);
    }

    public function rejectAboutPhoto(Request $request, User $user): JsonResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $data    = $request->validate(['note' => 'nullable|string|max:1000']);
        $profile = $this->profile($user);

        if ($profile && $profile->about_photo_pending) {
            Storage::disk('public')->delete($profile->about_photo_pending);
            $profile->update([
                'about_photo_pending'        => null,
                'about_photo_rejection_note' => $data['note'] ?? null,
            ]);
        }

        return response()->json(['status' => 'rejected']);
    }

    private function profile(User $user)
    {
        return $user->isReader() ? $user->readerProfile : $user->editorProfile;
    }
}
