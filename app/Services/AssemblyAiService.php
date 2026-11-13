<?php

// v1.0 — 2026-11-13 | Transcription for video-consulting call recordings — thin wrapper over
//                      AssemblyAI's REST API (https://api.assemblyai.com/v2), mirroring
//                      WherebyService's shape. Auth is a raw `authorization` header holding the
//                      key itself (no "Bearer" prefix) — confirmed against AssemblyAI's own docs.
//                      Submission registers a webhook (AssemblyAI's webhook payload only ever
//                      carries transcript_id + status, never the transcript text) rather than
//                      polling, since a transcription job can run several minutes and this
//                      queue's jobs are meant to return quickly.

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AssemblyAiService
{
    private const BASE_URL = 'https://api.assemblyai.com/v2';

    public function submitTranscription(string $audioUrl, string $webhookUrl): string
    {
        $response = Http::withHeaders(['authorization' => (string) config('services.assemblyai.api_key')])
            ->timeout(30)
            ->post(self::BASE_URL . '/transcript', [
                'audio_url'                => $audioUrl,
                'speaker_labels'            => true,
                'webhook_url'               => $webhookUrl,
                'webhook_auth_header_name'  => 'X-AssemblyAI-Webhook-Secret',
                'webhook_auth_header_value' => (string) config('services.assemblyai.webhook_secret'),
            ])
            ->throw();

        return $response->json('id');
    }

    /**
     * @return array{status: string, text: ?string, error: ?string, utterances: array}
     */
    public function fetchTranscript(string $transcriptId): array
    {
        $response = Http::withHeaders(['authorization' => (string) config('services.assemblyai.api_key')])
            ->timeout(30)
            ->get(self::BASE_URL . "/transcript/{$transcriptId}")
            ->throw();

        $data = $response->json();

        return [
            'status'     => $data['status'],
            'text'       => $data['text'] ?? null,
            'error'      => $data['error'] ?? null,
            'utterances' => $data['utterances'] ?? [],
        ];
    }

    /**
     * Renders diarized utterances as "Speaker A: ...\nSpeaker B: ..." — readable for a human QC
     * reviewer and gives the contact-info check who-said-what, which the bare `text` field lacks.
     */
    public static function formatUtterances(array $utterances): string
    {
        $lines = [];

        foreach ($utterances as $utterance) {
            $speaker = $utterance['speaker'] ?? '?';
            $text = trim((string) ($utterance['text'] ?? ''));

            if ($text !== '') {
                $lines[] = "Speaker {$speaker}: {$text}";
            }
        }

        return implode("\n", $lines);
    }
}
