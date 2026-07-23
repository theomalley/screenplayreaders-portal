<?php

// v1.5 — 2026-07-23 | Authorization moved to the manage-profile-approvals Gate ability
//                     (AppServiceProvider), replacing inline abort_unless(isAdmin()) —
//                     part of standardizing on Laravel's Gate/Policy system instead of
//                     three inconsistent ad-hoc authorization patterns. Covered by
//                     tests/Feature/AdminApprovalControllerTest.php.
// v1.4 — 2026-06-15 | Log bio/photo/about-photo approvals & rejections to Notification History
// v1.3 — 2026-06-12 | Sanitize bio HTML when approving bio_pending -> bio
// v1.2 — 2026-06-05 | Add approveAboutPhoto / rejectAboutPhoto
// v1.1 — 2026-05-30 | Return JSON for XHR; accept rejection note; store bio/photo rejection notes
// v1.0 — 2026-05-30 | Admin approval/rejection of pending bio and photo changes

namespace App\Http\Controllers;

use App\Models\NotificationHistory;
use App\Models\User;
use App\Support\Html;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminApprovalController extends Controller
{
    public function approveBio(User $user): JsonResponse
    {
        $this->authorize('manage-profile-approvals');

        $profile = $this->profile($user);

        if ($profile && $profile->bio_pending !== null) {
            $profile->update([
                'bio'              => Html::sanitizeBioHtml($profile->bio_pending),
                'bio_pending'      => null,
                'bio_rejection_note' => null,
            ]);

            NotificationHistory::log($user->id, 'Bio approved', null, route('profile.edit'));
        }

        return response()->json(['status' => 'approved']);
    }

    public function rejectBio(Request $request, User $user): JsonResponse
    {
        $this->authorize('manage-profile-approvals');

        $data    = $request->validate(['note' => 'nullable|string|max:1000']);
        $profile = $this->profile($user);

        if ($profile) {
            $profile->update([
                'bio_pending'        => null,
                'bio_rejection_note' => $data['note'] ?? null,
            ]);

            NotificationHistory::log($user->id, 'Bio rejected', $data['note'] ?? null, route('profile.edit'));
        }

        return response()->json(['status' => 'rejected']);
    }

    public function approvePhoto(User $user): JsonResponse
    {
        $this->authorize('manage-profile-approvals');

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

            NotificationHistory::log($user->id, 'Reader icon approved', null, route('profile.edit'));
        }

        return response()->json(['status' => 'approved']);
    }

    public function rejectPhoto(Request $request, User $user): JsonResponse
    {
        $this->authorize('manage-profile-approvals');

        $data    = $request->validate(['note' => 'nullable|string|max:1000']);
        $profile = $this->profile($user);

        if ($profile && $profile->photo_pending) {
            Storage::disk('public')->delete($profile->photo_pending);
            $profile->update([
                'photo_pending'        => null,
                'photo_rejection_note' => $data['note'] ?? null,
            ]);

            NotificationHistory::log($user->id, 'Reader icon rejected', $data['note'] ?? null, route('profile.edit'));
        }

        return response()->json(['status' => 'rejected']);
    }

    public function approveAboutPhoto(User $user): JsonResponse
    {
        $this->authorize('manage-profile-approvals');

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

            NotificationHistory::log($user->id, 'About photo approved', null, route('profile.edit'));
        }

        return response()->json(['status' => 'approved']);
    }

    public function rejectAboutPhoto(Request $request, User $user): JsonResponse
    {
        $this->authorize('manage-profile-approvals');

        $data    = $request->validate(['note' => 'nullable|string|max:1000']);
        $profile = $this->profile($user);

        if ($profile && $profile->about_photo_pending) {
            Storage::disk('public')->delete($profile->about_photo_pending);
            $profile->update([
                'about_photo_pending'        => null,
                'about_photo_rejection_note' => $data['note'] ?? null,
            ]);

            NotificationHistory::log($user->id, 'About photo rejected', $data['note'] ?? null, route('profile.edit'));
        }

        return response()->json(['status' => 'rejected']);
    }

    private function profile(User $user)
    {
        return $user->isReader() ? $user->readerProfile : $user->editorProfile;
    }
}
