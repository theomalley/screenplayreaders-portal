<?php

// v1.0 — 2026-11-07 | Video consulting Phase 2 — thin wrapper over Whereby's REST API
//                      (https://api.whereby.dev/v1). Shapes confirmed against Whereby's own
//                      OpenAPI spec: createMeeting's recording.destination.provider must be
//                      "whereby" (not "s3") for getRecordingAccessLink() to work — that
//                      endpoint only serves Whereby-hosted recordings.

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class WherebyService
{
    private const BASE_URL = 'https://api.whereby.dev/v1';

    /**
     * @return array{meetingId: string, roomName: string, roomUrl: string}
     */
    public function createRoom(Carbon $endDate): array
    {
        $response = Http::withToken((string) config('services.whereby.api_key'))
            ->timeout(30)
            ->post(self::BASE_URL . '/meetings', [
                'endDate' => $endDate->toIso8601String(),
                'recording' => [
                    'type' => 'cloud',
                    'destination' => ['provider' => 'whereby'],
                    'startTrigger' => 'automatic',
                ],
            ])
            ->throw();

        $data = $response->json();

        return [
            'meetingId' => $data['meetingId'],
            'roomName'  => $data['roomName'],
            'roomUrl'   => $data['roomUrl'],
        ];
    }

    /**
     * @return array{accessLink: string, expires: int} expires is epoch milliseconds
     */
    public function getRecordingAccessLink(string $recordingId, int $validForSeconds = 3600): array
    {
        $response = Http::withToken((string) config('services.whereby.api_key'))
            ->timeout(30)
            ->get(self::BASE_URL . "/recordings/{$recordingId}/access-link", [
                'validForSeconds' => $validForSeconds,
            ])
            ->throw();

        $data = $response->json();

        return [
            'accessLink' => $data['accessLink'],
            'expires'    => $data['expires'],
        ];
    }
}
