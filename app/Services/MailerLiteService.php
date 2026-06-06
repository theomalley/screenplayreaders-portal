<?php

// v1.0 — 2026-06-06 | MailerLite API v2 client — groups, campaign creation, scheduling, test send

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class MailerLiteService
{
    private string $apiKey;
    private string $baseUrl = 'https://connect.mailerlite.com/api';

    public function __construct()
    {
        $this->apiKey = (string) config('services.mailerlite.api_key', '');
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Fetch all subscriber groups (segments) — used to populate the campaign target dropdown.
     * Returns array of ['id' => '...', 'name' => '...', 'active_count' => int] objects.
     */
    public function getGroups(): array
    {
        $response = Http::withToken($this->apiKey)
            ->get("{$this->baseUrl}/groups", ['limit' => 100, 'sort' => 'name']);

        if ($response->failed()) {
            throw new RuntimeException('MailerLite API error (' . $response->status() . '): ' . ($response->json('message') ?? 'Unknown'));
        }

        return $response->json('data') ?? [];
    }

    /**
     * Create a campaign draft in MailerLite.
     * Returns the created campaign array (includes 'id').
     *
     * $groupIds: array of MailerLite group ID strings
     * $subject:  email subject line
     * $fromName: sender display name
     * $fromEmail: sender address
     * $html:     full email HTML (no outer html/head/body — MailerLite wraps it)
     * $name:     internal campaign name
     */
    public function createCampaign(
        string $name,
        string $subject,
        string $fromName,
        string $fromEmail,
        string $html,
        array  $groupIds
    ): array {
        $response = Http::withToken($this->apiKey)
            ->post("{$this->baseUrl}/campaigns", [
                'name'   => $name,
                'type'   => 'regular',
                'emails' => [[
                    'subject'   => $subject,
                    'from_name' => $fromName,
                    'from'      => $fromEmail,
                    'content'   => $html,
                ]],
                'groups' => $groupIds,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('MailerLite create campaign error (' . $response->status() . '): ' . ($response->json('message') ?? 'Unknown'));
        }

        return $response->json('data') ?? [];
    }

    /**
     * Schedule a campaign for a future send time.
     * $scheduledAt: UTC datetime string "2026-01-15 09:00:00"
     */
    public function scheduleCampaign(string $campaignId, string $scheduledAt): void
    {
        $response = Http::withToken($this->apiKey)
            ->post("{$this->baseUrl}/campaigns/{$campaignId}/schedule", [
                'delivery' => 'scheduled',
                'schedule' => [
                    'date'     => $scheduledAt,
                    'timezone' => 'UTC',
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('MailerLite schedule error (' . $response->status() . '): ' . ($response->json('message') ?? 'Unknown'));
        }
    }

    /**
     * Send a campaign immediately (must be in draft or scheduled status).
     */
    public function sendNow(string $campaignId): void
    {
        $response = Http::withToken($this->apiKey)
            ->post("{$this->baseUrl}/campaigns/{$campaignId}/actions/send");

        if ($response->failed()) {
            throw new RuntimeException('MailerLite send error (' . $response->status() . '): ' . ($response->json('message') ?? 'Unknown'));
        }
    }

    /**
     * Send a test email for a campaign to a specific address.
     * The campaign must already exist in MailerLite (have a campaign ID).
     */
    public function sendTest(string $campaignId, string $email): void
    {
        $response = Http::withToken($this->apiKey)
            ->post("{$this->baseUrl}/campaigns/{$campaignId}/test", [
                'emails' => [$email],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('MailerLite test send error (' . $response->status() . '): ' . ($response->json('message') ?? 'Unknown'));
        }
    }

    /**
     * Delete a campaign in MailerLite (used when re-sending after edits).
     */
    public function deleteCampaign(string $campaignId): void
    {
        Http::withToken($this->apiKey)
            ->delete("{$this->baseUrl}/campaigns/{$campaignId}");
    }
}
