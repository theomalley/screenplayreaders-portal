<?php

// v1.2 — 2026-05-31 | Resolve conversation number → large API ID via HelpScout API before storing.
//                     Zapier sends the short conversation number; the URL and API calls need the large ID.
// v1.1 — 2026-05-24 | Trim whitespace from incoming values; accept integer IDs (coerce to string).
// v1.0 — 2026-05-23 | Zapier-facing endpoint: store order_number → HelpScout conversation ID.
//                     Called by the sr-orders Zapier zap immediately after the HS ticket is created.

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HelpScoutConversation;
use App\Services\HelpScoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class HelpScoutConversationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        if (! $this->authorised($request)) {
            return response()->json(['error' => 'Unauthorised.'], 401);
        }

        // Coerce to string and trim — guards against Zapier sending integers or
        // field names with trailing whitespace when Unflatten is enabled.
        $input = [
            'order_number'    => trim((string) $request->input('order_number', '')),
            'conversation_id' => trim((string) $request->input('conversation_id', '')),
        ];

        $validator = Validator::make($input, [
            'order_number'    => 'required|string|max:64',
            'conversation_id' => 'required|string|max:64',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Validation failed.', 'details' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        // Zapier may send the short conversation number (e.g. 9735) rather than
        // the large internal API ID (e.g. 3332513773). The web URL and API calls
        // both require the large ID, so resolve it via API if the value looks like
        // a conversation number (heuristic: < 10,000,000).
        $conversationId = $data['conversation_id'];
        if (is_numeric($conversationId) && (int) $conversationId < 10_000_000) {
            try {
                $resolved = app(HelpScoutService::class)->findConversationIdByTicketNumber($conversationId);
                if ($resolved) {
                    Log::info('HelpScout conversation: resolved number to ID', [
                        'order_number' => $data['order_number'],
                        'number'       => $conversationId,
                        'id'           => $resolved,
                    ]);
                    $conversationId = $resolved;
                }
            } catch (\Throwable $e) {
                Log::warning('HelpScout conversation: ID resolution failed, storing as-is', [
                    'order_number' => $data['order_number'],
                    'value'        => $conversationId,
                    'error'        => $e->getMessage(),
                ]);
            }
        }

        HelpScoutConversation::updateOrCreate(
            ['order_number'              => $data['order_number']],
            ['helpscout_conversation_id' => $conversationId]
        );

        return response()->json(['status' => 'ok'], 200);
    }

    private function authorised(Request $request): bool
    {
        $secret = config('services.portal.webhook_secret');

        return ! empty($secret) && hash_equals($secret, $request->bearerToken() ?? '');
    }
}
