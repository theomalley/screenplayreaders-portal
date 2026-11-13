<?php

// v1.0 — 2026-11-13 | Receives AssemblyAI's transcription-complete webhook. AssemblyAI has no
//                      HMAC signature scheme — it just echoes back the custom header/value we
//                      registered at submission time (webhook_auth_header_name/value), so that's
//                      what's checked here, mirroring HelpScoutWebhookController/
//                      WherebyWebhookController's own-mechanism pattern (not portal.secret).
//                      The payload itself only ever carries transcript_id + status, never the
//                      transcript text — ProcessVideoConsultingTranscript fetches that separately.

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessVideoConsultingTranscript;
use App\Models\Assignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AssemblyAiWebhookController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        if (! $this->hasValidAuthHeader($request)) {
            Log::warning('AssemblyAI webhook: auth header mismatch');
            return response()->json(['error' => 'Invalid auth header.'], 401);
        }

        $payload = $request->json()->all();
        $transcriptId = $payload['transcript_id'] ?? null;
        $status = $payload['status'] ?? null;

        if (! $transcriptId || ! in_array($status, ['completed', 'error'], true)) {
            return response()->json(['status' => 'ignored']);
        }

        $assignment = Assignment::where('video_consulting_transcription_id', $transcriptId)->first();

        if (! $assignment) {
            Log::info('AssemblyAI webhook: no assignment matches transcript_id', ['transcript_id' => $transcriptId]);
            return response()->json(['status' => 'ignored']);
        }

        ProcessVideoConsultingTranscript::dispatch($assignment->id, $transcriptId, $status);

        return response()->json(['status' => 'accepted']);
    }

    private function hasValidAuthHeader(Request $request): bool
    {
        $expected = (string) config('services.assemblyai.webhook_secret');
        if ($expected === '') {
            return false;
        }

        $received = (string) $request->header('X-AssemblyAI-Webhook-Secret', '');

        return hash_equals($expected, $received);
    }
}
