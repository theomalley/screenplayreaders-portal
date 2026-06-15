<?php

// v1.2 — 2026-06-15 | When the draft is actually sent, clear helpscout_draft_sent_at /
//                     helpscout_draft_dismissed_by so the "goback ready" alert disappears
//                     for everyone instead of lingering until manually dismissed.
// v1.1 — 2026-06-12 | Only stamp helpscout_sent_at if the order already has at least one
//                     submitted assignment — convo.agent.reply.created also fires for the
//                     order-creation ticket message and other non-delivery agent replies,
//                     which would otherwise stamp a timestamp before submitted_at.
// v1.0 — 2026-06-12 | HelpScout webhook receiver for convo.agent.reply.created.
//                     Logs every delivery (signed or not) to helpscout_webhook_logs for
//                     inspection via /admin/helpscout-webhook-logs, then — if the signature
//                     is valid and a conversation id can be extracted — stamps
//                     helpscout_sent_at on the matching helpscout_order_conversations row.
//                     Conversation-id extraction confirmed against a real payload: top-level
//                     `id` is the conversation id.

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
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
            $conversation = HelpScoutConversation::where('helpscout_conversation_id', $conversationId)
                ->whereNull('helpscout_sent_at')
                ->first();

            if (! $conversation) {
                Log::info('HelpScout webhook: no eligible conversation row (unknown id or already stamped)', [
                    'conversation_id' => $conversationId,
                ]);
            } elseif (! Assignment::where('order_number', $conversation->order_number)->whereNotNull('submitted_at')->exists()) {
                // This reply was created before any reader submitted coverage for the order —
                // it's the order-creation ticket message (or similar), not the coverage delivery.
                Log::info('HelpScout webhook: skipped — no submitted coverage yet for order', [
                    'conversation_id' => $conversationId,
                    'order_number'    => $conversation->order_number,
                ]);
            } else {
                $conversation->update(['helpscout_sent_at' => now()]);

                // The "goback ready at HelpScout" alert is keyed off helpscout_draft_sent_at —
                // clear it now that the draft has actually been sent, so it disappears for everyone.
                Assignment::where('order_number', $conversation->order_number)
                    ->whereNotNull('helpscout_draft_sent_at')
                    ->update([
                        'helpscout_draft_sent_at'      => null,
                        'helpscout_draft_dismissed_by' => null,
                    ]);

                Log::info('HelpScout webhook: processed', [
                    'conversation_id' => $conversationId,
                    'order_number'    => $conversation->order_number,
                ]);
            }
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
