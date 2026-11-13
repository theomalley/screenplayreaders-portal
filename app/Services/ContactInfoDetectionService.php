<?php

// v1.0 — 2026-11-13 | Flags whether a video-consulting call transcript shows the reader and
//                      customer exchanging personal contact info (phone/email/social handles)
//                      or agreeing to communicate off-platform — the compliance check this
//                      whole recording/transcript pipeline exists for. A human still makes the
//                      call in QC review; this only surfaces a flag + supporting quotes for them
//                      to check, same spirit as the existing manual-transcript stopgap it augments.

namespace App\Services;

use Anthropic\Client;

class ContactInfoDetectionService
{
    private const MODEL = 'claude-opus-5-5';

    private const SYSTEM_PROMPT = <<<'PROMPT'
        You review call transcripts between a script reader and a customer for a screenplay
        coverage service. Flag only an actual exchange of personal contact information (a phone
        number, a personal email address, a social media handle/username) or an explicit
        agreement to communicate outside the platform going forward. Do not flag the company's
        own support email or phone number if mentioned, generic pleasantries, or discussion of
        the script itself.
        PROMPT;

    /**
     * @return array{flagged: bool, evidence: string}
     */
    public function check(string $transcript): array
    {
        $client = new Client(apiKey: (string) config('services.anthropic.api_key'));

        $message = $client->messages->create(
            model: self::MODEL,
            maxTokens: 1024,
            system: self::SYSTEM_PROMPT,
            messages: [[
                'role'    => 'user',
                'content' => "Review this call transcript:\n\n{$transcript}",
            ]],
            outputConfig: ['format' => ContactInfoVerdict::class],
        );

        $verdict = $message->parsedOutput();

        return [
            'flagged'  => $verdict->flagged,
            'evidence' => $verdict->evidence,
        ];
    }
}
