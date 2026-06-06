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
            throw new RuntimeException('MailerLite API error (' . $response->status() . '): ' . $response->body());
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
            throw new RuntimeException('MailerLite create campaign error (' . $response->status() . '): ' . ($response->body()));
        }

        $data = $response->json('data');

        if (empty($data['id'])) {
            throw new RuntimeException('MailerLite create campaign returned no ID. Response: ' . $response->body());
        }

        return $data;
    }

    /**
     * Fetch a single campaign — used to verify status and missing_data after creation.
     */
    public function getCampaign(string $campaignId): array
    {
        $response = Http::withToken($this->apiKey)
            ->get("{$this->baseUrl}/campaigns/{$campaignId}");

        if ($response->failed()) {
            throw new RuntimeException('MailerLite get campaign error (' . $response->status() . '): ' . $response->body());
        }

        return $response->json('data') ?? [];
    }

    /**
     * Schedule a campaign for a future send time.
     * $scheduledAt: UTC datetime string "2026-01-15 09:00:00"
     */
    public function scheduleCampaign(string $campaignId, string $scheduledAt): void
    {
        $dt = new \DateTime($scheduledAt, new \DateTimeZone('UTC'));

        $response = Http::withToken($this->apiKey)
            ->post("{$this->baseUrl}/campaigns/{$campaignId}/schedule", [
                'delivery' => 'scheduled',
                'schedule' => [
                    'date'     => $dt->format('Y-m-d'),
                    'hours'    => $dt->format('H'),
                    'minutes'  => $dt->format('i'),
                    'timezone' => $this->utcTimezoneId(),
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('MailerLite schedule error (' . $response->status() . '): ' . $response->body());
        }
    }

    /** Fetch MailerLite's integer ID for UTC from their /timezones endpoint. */
    private function utcTimezoneId(): int
    {
        static $id = null;
        if ($id !== null) {
            return $id;
        }

        $response = Http::withToken($this->apiKey)->get("{$this->baseUrl}/timezones");
        if ($response->failed()) {
            throw new RuntimeException('MailerLite timezones error (' . $response->status() . '): ' . $response->body());
        }

        foreach ($response->json('data') ?? [] as $tz) {
            if (($tz['gmt'] ?? '') === '+00:00' || ($tz['title'] ?? '') === 'UTC') {
                $id = (int) $tz['id'];
                return $id;
            }
        }

        throw new RuntimeException('Could not find UTC timezone in MailerLite timezone list. Response: ' . $response->body());
    }

    /**
     * Send a campaign immediately (must be in draft or scheduled status).
     */
    public function sendNow(string $campaignId): void
    {
        $response = Http::withToken($this->apiKey)
            ->post("{$this->baseUrl}/campaigns/{$campaignId}/actions/send");

        if ($response->failed()) {
            throw new RuntimeException('MailerLite send error (' . $response->status() . '): ' . $response->body());
        }
    }

    /**
     * Send a test email for a campaign to a specific address.
     * MailerLite's new API ties the test action to the email object, not the campaign.
     * $emailId comes from campaign['emails'][0]['id'] returned by createCampaign.
     */
    public function sendTest(string $campaignId, string $emailId, string $email): void
    {
        $response = Http::withToken($this->apiKey)
            ->post("{$this->baseUrl}/campaigns/{$campaignId}/emails/{$emailId}/test", [
                'emails' => [$email],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('MailerLite test send error (' . $response->status() . '): ' . $response->body());
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
