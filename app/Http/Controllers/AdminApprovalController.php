<?php

// v1.1 — 2026-05-30 | Return JSON for XHR; accept rejection note; store bio/photo rejection notes
// v1.0 — 2026-05-30 | Admin approval/rejection of pending bio and photo changes

namespace App\Http\Controllers;

use App\Models\User;
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
                'bio'              => $profile->bio_pending,
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

    private function profile(User $user)
    {
        return $user->isReader() ? $user->readerProfile : $user->editorProfile;
    }
}
