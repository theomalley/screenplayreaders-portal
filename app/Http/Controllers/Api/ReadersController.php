<?php

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
            ->map(fn ($u) => [
                'initials'       => $u->readerProfile->initials,
                'availability'   => $u->readerProfile->availability ?? 'available',
                'message'        => $u->readerProfile->availability_message,
                'upload_warning' => $u->readerProfile->upload_warning,
            ])
            ->sortBy('initials')
            ->values();

        return response()->json($readers);
    }
}
