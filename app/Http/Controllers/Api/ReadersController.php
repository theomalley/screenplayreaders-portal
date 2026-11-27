<?php

// v1.2 — 2026-11-27 | Add id/name/photo_url/about_photo_url/favorite_genres/favorite_films —
//                     needed by the theme's script-coverage form to show full reader cards
//                     (photo/bio/genres/titles) for the complete reader-request pool, not just
//                     the smaller video-consulting subset VideoConsultingBookingController::
//                     readers() already exposes this data for. Same ReaderProfile fields, same
//                     asset('storage/...') URL building, no videoConsultingRates filter/join.
// v1.1 — 2026-05-24 | Add upload_warning to response for per-reader customer-facing warning on upload form.
// v1.0 — 2026-05-24 | Returns reader availability list for WordPress upload form.
//                     Called by sr-upload-system.php on a 5-minute transient cache.
//                     Authenticated via Bearer token (same PORTAL_WEBHOOK_SECRET).

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReadersController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $readers = User::where('role', 'reader')
            ->with('readerProfile')
            ->get()
            ->filter(fn ($u) => $u->readerProfile !== null)
            ->map(function ($u) {
                $profile    = $u->readerProfile;
                $photo      = $profile->photo;
                $aboutPhoto = $profile->about_photo;

                return [
                    'id'              => $u->id,
                    'name'            => $u->name,
                    'initials'        => $profile->initials,
                    'availability'    => $profile->availability ?? 'available',
                    'message'         => $profile->availability_message,
                    'upload_warning'  => $profile->upload_warning,
                    'photo_url'       => $photo ? asset('storage/' . $photo) : null,
                    'about_photo_url' => $aboutPhoto ? asset('storage/' . $aboutPhoto) : null,
                    'favorite_genres' => $profile->favorite_genres ?? [],
                    'favorite_films'  => $profile->favorite_films ?? [],
                ];
            })
            ->sortBy('initials')
            ->values();

        return response()->json($readers);
    }
}
