<?php

// v1.0 — 2026-11-07 | Video consulting Phase 2 — receives Whereby's recording.finished
//                      webhook. Signature scheme confirmed against Whereby's own docs:
//                      header "Whereby-Signature: t=<unix>,v1=<hex>", HMAC-SHA256 of
//                      "{timestamp}.{rawBody}" keyed with the webhook's signing secret.
//                      Mirrors HelpScoutWebhookController's own-signature-check pattern
//                      (not the shared portal.secret middleware — Whereby is an independent
//                      third party, not the WP theme).

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\DownloadWherebyRecording;
use App\Models\Assignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WherebyWebhookController extends Controller
{
    private const SIGNATURE_MAX_AGE_SECONDS = 300;

    public function store(Request $request): JsonResponse
    {
        if (! $this->hasValidSignature($request)) {
            Log::warning('Whereby webhook: signature verification failed');
            return response()->json(['error' => 'Invalid signature.'], 401);
        }

        $payload = $request->json()->all();

        if (($payload['type'] ?? null) !== 'recording.finished') {
            return response()->json(['status' => 'ignored']);
        }

        $data = $payload['data'] ?? [];
        $roomName = $data['roomName'] ?? null;
        $recordingId = $data['recordingId'] ?? null;
        $filename = $data['filename'] ?? null;

        if (! $roomName || ! $recordingId) {
            Log::warning('Whereby webhook: recording.finished missing roomName/recordingId', $data);
            return response()->json(['status' => 'ignored']);
        }

        $assignment = Assignment::where('whereby_room_name', $roomName)->first();

        if (! $assignment) {
            Log::info('Whereby webhook: no assignment matches roomName', ['room_name' => $roomName]);
            return response()->json(['status' => 'ignored']);
        }

        if ($assignment->video_consulting_recording_path) {
            return response()->json(['status' => 'already_processed']);
        }

        DownloadWherebyRecording::dispatch($assignment->id, $recordingId, $filename ?? 'recording.mp4');

        return response()->json(['status' => 'accepted']);
    }

    private function hasValidSignature(Request $request): bool
    {
        $secret = config('services.whereby.webhook_secret');
        if (empty($secret)) {
            return false;
        }

        $header = $request->header('Whereby-Signature', '');
        if (! preg_match('/t=(\d+),v1=([0-9a-f]+)/', $header, $matches)) {
            return false;
        }

        [, $timestamp, $signature] = $matches;

        if (abs(time() - (int) $timestamp) > self::SIGNATURE_MAX_AGE_SECONDS) {
            return false;
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$request->getContent()}", (string) $secret);

        return hash_equals($expected, $signature);
    }
}
