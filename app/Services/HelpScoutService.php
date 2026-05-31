<?php

// v1.4 — 2026-05-31 | Reopen closed conversations before drafting; fetch conversation once for ID + status
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
        $token        = $this->getToken();
        $conversation = $this->fetchConversation($conversationId, $token);
        $customerId   = $this->extractCustomerId($conversation, $conversationId);
        $convStatus   = $conversation['status'] ?? 'unknown';

        Log::info('HelpScout draft: conversation fetched', [
            'conversation_id' => $conversationId,
            'status'          => $convStatus,
            'customer_id'     => $customerId,
        ]);

        // HelpScout rejects draft replies on closed conversations — reopen first.
        if ($convStatus === 'closed') {
            Log::info('HelpScout draft: reopening closed conversation', ['conversation_id' => $conversationId]);
            $this->reopenConversation($conversationId, $token);
            Log::info('HelpScout draft: conversation reopened', ['conversation_id' => $conversationId]);
        }

        $body = [
            'customer' => ['id' => $customerId],
            'draft'    => true,
            'text'     => $html,
        ];

        Log::info('HelpScout draft: posting reply', ['conversation_id' => $conversationId]);

        $response = Http::withToken($token)
            ->post(self::API_BASE . "/conversations/{$conversationId}/reply", $body);

        if (! $response->successful()) {
            Log::error('HelpScout draft creation failed', [
                'conversation_id' => $conversationId,
                'http_status'     => $response->status(),
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

    public function findConversationIdByTicketNumber(string $ticketNumber): ?string
    {
        $token    = $this->getToken();
        $response = Http::withToken($token)
            ->get(self::API_BASE . '/conversations', [
                'number' => $ticketNumber,
                'status' => 'all',
            ]);

        if (! $response->ok()) {
            Log::error('HelpScout conversation search failed', [
                'ticket_number' => $ticketNumber,
                'http_status'   => $response->status(),
                'body'          => $response->body(),
            ]);
            return null;
        }

        $id = $response->json('_embedded.conversations.0.id');

        Log::info('HelpScout conversation search', [
            'ticket_number'   => $ticketNumber,
            'conversation_id' => $id ?? 'not found',
            'total_results'   => $response->json('page.totalElements') ?? 'unknown',
        ]);

        return $id ? (string) $id : null;
    }

    private function fetchConversation(string $conversationId, string $token): array
    {
        $response = Http::withToken($token)
            ->get(self::API_BASE . "/conversations/{$conversationId}");

        if (! $response->ok()) {
            throw new \RuntimeException('HelpScout conversation lookup failed (' . $response->status() . '): ' . $response->body());
        }

        return $response->json();
    }

    private function extractCustomerId(array $conversation, string $conversationId): int
    {
        $href = $conversation['_links']['primaryCustomer']['href'] ?? '';
        $id   = (int) basename($href);

        if (! $id) {
            throw new \RuntimeException("Could not extract customer ID from conversation {$conversationId}.");
        }

        return $id;
    }

    private function reopenConversation(string $conversationId, string $token): void
    {
        $response = Http::withToken($token)
            ->withHeaders(['Content-Type' => 'application/json-patch+json'])
            ->patch(self::API_BASE . "/conversations/{$conversationId}", [
                ['op' => 'replace', 'path' => '/status', 'value' => 'active'],
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('HelpScout reopen failed (' . $response->status() . '): ' . $response->body());
        }
    }
}
