<?php

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
            $profile  = $user->readerProfile;
            $photoRel = $profile?->photo;
        } else {
            $profile  = $user->editorProfile;
            $photoRel = $profile?->photo;
        }

        return response()->json([
            'id'        => $user->id,
            'name'      => $profile?->displayName() ?? $user->name,
            'initials'  => $profile?->initials ?? strtoupper(substr($user->name, 0, 2)),
            'title'     => $profile?->title,
            'bio'       => $profile?->bio,
            'photo_url' => $photoRel ? asset('storage/' . $photoRel) : null,
            'role'      => $user->role,
        ]);
    }
}
