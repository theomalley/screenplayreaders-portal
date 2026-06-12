<?php

// v1.0 — 2026-06-12 | HelpScout webhook receiver for convo.agent.reply.created.
//                     Logs every delivery (signed or not) to helpscout_webhook_logs for
//                     inspection via /admin/helpscout-webhook-logs, then — if the signature
//                     is valid and a conversation id can be extracted — stamps
//                     helpscout_sent_at on the matching helpscout_order_conversations row.
//                     NOTE: conversation-id extraction is best-effort until the real V3
//                     payload shape is confirmed against logged deliveries (see plan).

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HelpScoutConversation;
use App\Models\HelpScoutWebhookLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HelpScoutWebhookController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $secret  = config('services.helpscout.webhook_secret');
        $payload = $request->json()->all();

        $expected = base64_encode(hash_hmac('sha1', $request->getContent(), (string) $secret, true));
        $valid    = ! empty($secret) && hash_equals($expected, $request->header('X-HelpScout-Signature') ?? '');

        $conversationId = $this->extractConversationId($payload);

        HelpScoutWebhookLog::create([
            'event'                     => $request->header('X-HelpScout-Event') ?? ($payload['event'] ?? $payload['type'] ?? null),
            'helpscout_conversation_id' => $conversationId,
            'payload'                   => $payload,
            'signature_valid'           => $valid,
        ]);

        if (! $valid) {
            Log::warning('HelpScout webhook: signature verification failed');
            return response()->json(['error' => 'Invalid signature.'], 401);
        }

        if ($conversationId) {
            $updated = HelpScoutConversation::where('helpscout_conversation_id', $conversationId)
                ->whereNull('helpscout_sent_at')
                ->update(['helpscout_sent_at' => now()]);

            Log::info('HelpScout webhook: processed', [
                'conversation_id' => $conversationId,
                'rows_updated'    => $updated,
            ]);
        } else {
            Log::warning('HelpScout webhook: could not extract conversation id from payload');
        }

        return response()->json(['status' => 'ok']);
    }

    private function extractConversationId(array $payload): ?string
    {
        if (! empty($payload['id']) && is_numeric($payload['id'])) {
            return (string) $payload['id'];
        }

        $href = $payload['_links']['self']['href'] ?? null;
        if ($href && preg_match('/(\d+)$/', $href, $m)) {
            return $m[1];
        }

        return null;
    }
}
