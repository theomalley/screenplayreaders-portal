<?php

// v1.0 — 2026-06-12 | Simulates a signed HelpScout webhook delivery against the local
//                     /api/helpscout-webhook endpoint, for testing signature verification,
//                     logging, and the helpscout_sent_at stamp without a live HelpScout event.

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SimulateHelpScoutWebhook extends Command
{
    protected $signature   = 'helpscout:simulate-webhook {conversation_id} {--event=convo.agent.reply.created} {--bad-signature}';
    protected $description = 'POST a simulated, signed HelpScout webhook payload to /api/helpscout-webhook';

    public function handle(): int
    {
        $conversationId = $this->argument('conversation_id');
        $event          = $this->option('event');

        $payload = [
            'id'     => (int) $conversationId,
            'number' => (int) $conversationId,
            'status' => 'active',
        ];

        $body   = json_encode($payload);
        $secret = $this->option('bad-signature')
            ? 'wrong-secret'
            : (string) config('services.helpscout.webhook_secret');

        $signature = base64_encode(hash_hmac('sha1', $body, $secret, true));
        $url       = rtrim(config('app.url'), '/') . '/api/helpscout-webhook';

        $this->info("POST {$url}");
        $this->line("Event: {$event}");
        $this->line("Payload: {$body}");

        $response = Http::withHeaders([
            'Content-Type'         => 'application/json',
            'X-HelpScout-Event'     => $event,
            'X-HelpScout-Signature' => $signature,
        ])->withBody($body, 'application/json')->post($url);

        $this->line("Status: {$response->status()}");
        $this->line("Body: {$response->body()}");

        return $response->successful() ? self::SUCCESS : self::FAILURE;
    }
}
