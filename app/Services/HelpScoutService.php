<?php

// v1.6 — 2026-06-02 | createDirectReaderDraft — new outgoing draft addressed to a single reader
// v1.5 — 2026-05-31 | createReaderBroadcastDraft — new outgoing draft with BCC list for reader broadcasts
// v1.4 — 2026-05-31 | Fetch conversation once for ID + status; attempt draft directly on closed conversations
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

    /**
     * Create a new outgoing conversation as a draft addressed to a single reader/editor.
     * Returns the HelpScout web URL for the created conversation.
     */
    public function createDirectReaderDraft(string $toEmail, string $toName): string
    {
        $token     = $this->getToken();
        $mailboxId = $this->getFirstMailboxId($token);

        $convResponse = Http::withToken($token)
            ->asJson()
            ->post(self::API_BASE . '/conversations', [
                'subject'   => 'Message to ' . $toName,
                'customer'  => ['email' => $toEmail],
                'mailboxId' => $mailboxId,
                'type'      => 'email',
                'status'    => 'active',
                'threads'   => [[
                    'type'     => 'reply',
                    'customer' => ['email' => $toEmail],
                    'draft'    => true,
                    'text'     => '(Write your message here)',
                ]],
            ]);

        if (! $convResponse->successful()) {
            throw new \RuntimeException('HelpScout conversation create failed (' . $convResponse->status() . '): ' . $convResponse->body());
        }

        $conversationId = (string) $convResponse->header('Resource-Id');
        if (! $conversationId) {
            throw new \RuntimeException('HelpScout did not return a conversation ID.');
        }

        return 'https://secure.helpscout.net/conversation/' . $conversationId . '/';
    }

    /**
     * Create a new outgoing conversation as a draft with all $bccEmails in BCC.
     * Returns the HelpScout web URL for the created conversation.
     */
    public function createReaderBroadcastDraft(array $bccEmails): string
    {
        $token     = $this->getToken();
        $mailboxId = $this->getFirstMailboxId($token);

        $convResponse = Http::withToken($token)
            ->asJson()
            ->post(self::API_BASE . '/conversations', [
                'subject'   => 'Message to all readers',
                'customer'  => ['email' => 'support@screenplayreaders.com'],
                'mailboxId' => $mailboxId,
                'type'      => 'email',
                'status'    => 'active',
                'threads'   => [[
                    'type'     => 'reply',
                    'customer' => ['email' => 'support@screenplayreaders.com'],
                    'draft'    => true,
                    'bcc'      => $bccEmails,
                    'text'     => '(Write your message here)',
                ]],
            ]);

        if (! $convResponse->successful()) {
            throw new \RuntimeException('HelpScout conversation create failed (' . $convResponse->status() . '): ' . $convResponse->body());
        }

        $conversationId = (string) $convResponse->header('Resource-Id');
        if (! $conversationId) {
            throw new \RuntimeException('HelpScout did not return a conversation ID.');
        }

        return 'https://secure.helpscout.net/conversation/' . $conversationId . '/';
    }

    private function getFirstMailboxId(string $token): int
    {
        $response = Cache::remember('helpscout_first_mailbox_id', 60 * 60, function () use ($token) {
            $r = Http::withToken($token)->get(self::API_BASE . '/mailboxes');
            if (! $r->ok()) {
                throw new \RuntimeException('HelpScout mailboxes fetch failed: ' . $r->body());
            }
            $id = $r->json('_embedded.mailboxes.0.id');
            if (! $id) {
                throw new \RuntimeException('No HelpScout mailboxes found.');
            }
            return (int) $id;
        });

        return (int) $response;
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

}
