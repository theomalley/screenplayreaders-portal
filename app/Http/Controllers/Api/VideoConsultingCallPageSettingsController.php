<?php

// v1.0 — 2026-11-11 | Video consulting Phase 7 (partial) — admin-editable heading/consent/
//                      not-ready text + logo for the call page.
//                      PORTAL INTEGRATION: called by the theme's "Tools -> SR Video Consulting"
//                      wp-admin page, gated by the shared-secret 'portal.secret' middleware like
//                      every other admin-tool-facing endpoint — the WP admin session itself is
//                      what authorizes the staff member on the theme side.

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VideoConsultingCallPageSettingsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Setting::getCallPageSettings());
    }

    public function update(Request $request): JsonResponse
    {
        // An empty logo_url means "clear the logo" — swap it to null before validating so
        // 'nullable' lets it through without tripping the 'url' rule, then restore it to ''
        // afterward (Setting values are always stored as strings).
        $logoBlank = $request->has('call_page_logo_url') && trim((string) $request->input('call_page_logo_url')) === '';
        if ($logoBlank) {
            $request->merge(['call_page_logo_url' => null]);
        }

        $data = $request->validate([
            'call_page_heading'        => ['sometimes', 'string', 'max:120'],
            'call_page_consent_text'   => ['sometimes', 'string', 'max:500'],
            'call_page_not_ready_text' => ['sometimes', 'string', 'max:500'],
            'call_page_logo_url'       => ['sometimes', 'nullable', 'string', 'max:2048', 'url:http,https'],
        ]);

        if ($logoBlank) {
            $data['call_page_logo_url'] = '';
        }

        foreach ($data as $key => $value) {
            Setting::setValue($key, $value);
        }

        return response()->json(Setting::getCallPageSettings());
    }
}
