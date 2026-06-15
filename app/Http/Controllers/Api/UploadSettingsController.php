<?php

// v1.0 — 2026-06-15 | Exposes admin-configurable block-reader limits to the
//                     WordPress sr-upload-system.php form. Authenticated via
//                     Bearer token (same PORTAL_WEBHOOK_SECRET as /api/readers).

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UploadSettingsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! $this->authorized($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return response()->json(Setting::getBlockedReaderLimits());
    }

    private function authorized(Request $request): bool
    {
        $secret = config('services.portal.webhook_secret');
        return ! empty($secret) && hash_equals($secret, $request->bearerToken() ?? '');
    }
}
