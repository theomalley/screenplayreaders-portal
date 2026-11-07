<?php

// v1.0 — 2026-11-07 | Video consulting Phase 1 — admin CRUD for call-length durations.
//                      PORTAL INTEGRATION: called by the theme's (Phase 4) "Tools -> SR Video
//                      Consulting" wp-admin page, gated by the shared-secret 'portal.secret'
//                      middleware like every other admin-tool-facing endpoint — the WP admin
//                      session itself is what authorizes the staff member on the theme side.

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VideoConsultingDuration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VideoConsultingDurationController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            VideoConsultingDuration::orderBy('sort_order')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'minutes'          => ['required', 'integer', 'min:1', 'max:600'],
            'label'            => ['required', 'string', 'max:255'],
            'base_rate'        => ['required', 'numeric', 'min:0', 'max:9999.99'],
            'min_reader_rate'  => ['required', 'numeric', 'min:0', 'max:9999.99'],
            'max_reader_rate'  => ['required', 'numeric', 'min:0', 'max:9999.99', 'gte:min_reader_rate'],
            'enabled'          => ['nullable', 'boolean'],
            'sort_order'       => ['nullable', 'integer', 'min:0'],
        ]);

        $duration = VideoConsultingDuration::create($data);

        return response()->json($duration, 201);
    }

    public function update(Request $request, VideoConsultingDuration $duration): JsonResponse
    {
        $data = $request->validate([
            'minutes'          => ['sometimes', 'integer', 'min:1', 'max:600'],
            'label'            => ['sometimes', 'string', 'max:255'],
            'base_rate'        => ['sometimes', 'numeric', 'min:0', 'max:9999.99'],
            'min_reader_rate'  => ['sometimes', 'numeric', 'min:0', 'max:9999.99'],
            'max_reader_rate'  => ['sometimes', 'numeric', 'min:0', 'max:9999.99'],
            'enabled'          => ['sometimes', 'boolean'],
            'sort_order'       => ['sometimes', 'integer', 'min:0'],
        ]);

        $minRate = $data['min_reader_rate'] ?? $duration->min_reader_rate;
        $maxRate = $data['max_reader_rate'] ?? $duration->max_reader_rate;
        if ((float) $maxRate < (float) $minRate) {
            return response()->json(['message' => 'max_reader_rate must be >= min_reader_rate'], 422);
        }

        $duration->update($data);

        return response()->json($duration);
    }

    public function destroy(VideoConsultingDuration $duration): JsonResponse
    {
        if (! $duration->canBeDeleted()) {
            return response()->json(['message' => 'Duration has rate or booking history — disable it instead of deleting.'], 422);
        }

        $duration->delete();

        return response()->json(['status' => 'deleted']);
    }
}
