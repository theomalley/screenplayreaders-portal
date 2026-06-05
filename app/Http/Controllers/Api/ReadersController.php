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
        if (! $this->authorized($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

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

    private function authorized(Request $request): bool
    {
        $secret = config('services.portal.webhook_secret');
        return ! empty($secret) && hash_equals($secret, $request->bearerToken() ?? '');
    }
}
