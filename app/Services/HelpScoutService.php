<?php

// v1.0 — 2026-05-23 | OAuth2 token + draft reply creation on existing conversations

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HelpScoutService
{
    private const TOKEN_URL = 'https://api.helpscout.net/v2/auth/token';
    private const API_BASE  = 'https://api.helpscout.net/v2';

    private function getToken(): string
    {
        return Cache::remember('helpscout_access_token', 55 * 60, function () {
            $response = Http::asForm()->post(self::TOKEN_URL, [
                'grant_type'    => 'client_credentials',
                'client_id'     => config('services.helpscout.client_id'),
                'client_secret' => config('services.helpscout.client_secret'),
            ]);

            if (! $response->ok()) {
                throw new \RuntimeException('HelpScout auth failed: ' . $response->body());
            }

            return $response->json('access_token');
        });
    }

    /**
     * Create a draft reply thread on an existing HelpScout conversation.
     *
     * @param  string  $conversationId   The HelpScout conversation ID (numeric string).
     * @param  string  $html             Body of the draft reply.
     * @param  array   $attachments      Each entry: ['fileName' => '...', 'mimeType' => '...', 'data' => '<base64>']
     */
    public function createDraftReply(string $conversationId, string $html, array $attachments = []): void
    {
        $token = $this->getToken();

        $body = [
            'type'  => 'reply',
            'draft' => true,
            'text'  => $html,
        ];

        if (! empty($attachments)) {
            $body['attachments'] = $attachments;
        }

        $response = Http::withToken($token)
            ->post(self::API_BASE . "/conversations/{$conversationId}/threads", $body);

        if (! $response->successful()) {
            Log::error('HelpScout draft creation failed', [
                'conversation_id' => $conversationId,
                'status'          => $response->status(),
                'body'            => $response->body(),
            ]);
            throw new \RuntimeException('HelpScout draft creation failed (' . $response->status() . '): ' . $response->body());
        }
    }
}
