<?php

// v1.1 — 2026-06-05 | Add about_photo_url to response
// v1.0 — 2026-05-30 | Public endpoint — returns staff bio + photo URL for WordPress shortcodes

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class StaffProfileController extends Controller
{
    public function show(User $user): JsonResponse
    {
        abort_unless(
            $user->isReader() || $user->isAdminOrEditor(),
            404
        );

        if ($user->isReader()) {
            $profile       = $user->readerProfile;
            $photoRel      = $profile?->photo;
            $aboutPhotoRel = $profile?->about_photo;
        } else {
            $profile       = $user->editorProfile;
            $photoRel      = $profile?->photo;
            $aboutPhotoRel = $profile?->about_photo;
        }

        return response()->json([
            'id'             => $user->id,
            'name'           => $profile?->displayName() ?? $user->name,
            'initials'       => $profile?->initials ?? strtoupper(substr($user->name, 0, 2)),
            'title'          => $profile?->title,
            'bio'            => $profile?->bio,
            'photo_url'      => $photoRel      ? asset('storage/' . $photoRel)      : null,
            'about_photo_url'=> $aboutPhotoRel ? asset('storage/' . $aboutPhotoRel) : null,
            'role'           => $user->role,
        ]);
    }
}
