<?php

// v1.0 — 2026-05-23 | Zapier-facing endpoint: store order_number → HelpScout conversation ID.
//                     Called by the sr-orders Zapier zap immediately after the HS ticket is created.

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HelpScoutConversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HelpScoutConversationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        Log::info('HelpScout conversation endpoint hit', [
            'method'        => $request->method(),
            'url'           => $request->fullUrl(),
            'has_bearer'    => ! empty($request->bearerToken()),
            'content_type'  => $request->header('Content-Type'),
            'all_headers'   => $request->headers->all(),
            'body'          => $request->all(),
        ]);

        if (! $this->authorised($request)) {
            return response()->json(['error' => 'Unauthorised.'], 401);
        }

        $data = $request->validate([
            'order_number'       => 'required|string|max:64',
            'conversation_id'    => 'required|string|max:64',
        ]);

        HelpScoutConversation::updateOrCreate(
            ['order_number'            => $data['order_number']],
            ['helpscout_conversation_id' => $data['conversation_id']]
        );

        return response()->json(['status' => 'ok'], 200);
    }

    private function authorised(Request $request): bool
    {
        $secret = config('services.portal.webhook_secret');

        return ! empty($secret) && $request->bearerToken() === $secret;
    }
}
