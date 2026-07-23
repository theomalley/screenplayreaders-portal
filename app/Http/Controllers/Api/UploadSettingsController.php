<?php

// v1.0 — 2026-06-15 | Exposes admin-configurable block-reader limits to the
//                     WordPress sr-upload-system.php form. Authenticated via
//                     Bearer token (same PORTAL_WEBHOOK_SECRET as /api/readers).

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class UploadSettingsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Setting::getBlockedReaderLimits());
    }
}
