<?php

// v1.3 — 2026-05-23 | Upload attachments separately to thread after draft creation
// v1.2 — 2026-05-23 | Fetch customer ID from conversation before drafting (required by /reply endpoint)
// v1.1 — 2026-05-23 | Fix token URL (v2/oauth2/token) and reply endpoint (POST /reply not /threads)
// v1.0 — 2026-05-23 | OAuth2 token + draft reply creation on existing conversations

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HelpScoutService
{
    private const TOKEN_URL = 'https://api.helpscout.net/v2/oauth2/token';
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

        $customerId = $this->getCustomerId($conversationId, $token);

        $body = [
            'customer' => ['id' => $customerId],
            'draft'    => true,
            'text'     => $html,
        ];

        $response = Http::withToken($token)
            ->post(self::API_BASE . "/conversations/{$conversationId}/reply", $body);

        if (! $response->successful()) {
            Log::error('HelpScout draft creation failed', [
                'conversation_id' => $conversationId,
                'status'          => $response->status(),
                'body'            => $response->body(),
            ]);
            throw new \RuntimeException('HelpScout draft creation failed (' . $response->status() . '): ' . $response->body());
        }

        $threadId = $response->header('Resource-Id');

        foreach ($attachments as $attachment) {
            $ar = Http::withToken($token)
                ->post(self::API_BASE . "/conversations/{$conversationId}/threads/{$threadId}/attachments", $attachment);

            if (! $ar->successful()) {
                Log::error('HelpScout attachment upload failed', [
                    'conversation_id' => $conversationId,
                    'thread_id'       => $threadId,
                    'file'            => $attachment['fileName'] ?? '',
                    'status'          => $ar->status(),
                    'body'            => $ar->body(),
                ]);
            }
        }
    }

    private function getCustomerId(string $conversationId, string $token): int
    {
        $response = Http::withToken($token)
            ->get(self::API_BASE . "/conversations/{$conversationId}");

        if (! $response->ok()) {
            throw new \RuntimeException('HelpScout conversation lookup failed (' . $response->status() . '): ' . $response->body());
        }

        $json = $response->json();
        $href = $json['_links']['primaryCustomer']['href'] ?? '';
        $id   = (int) basename($href);

        if (! $id) {
            throw new \RuntimeException("Could not extract customer ID from conversation {$conversationId}.");
        }

        return $id;
    }
}
