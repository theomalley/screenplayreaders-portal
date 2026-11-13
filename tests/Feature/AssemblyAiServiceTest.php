<?php

namespace Tests\Feature;

use App\Services\AssemblyAiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AssemblyAiServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.assemblyai.api_key'        => 'test-api-key',
            'services.assemblyai.webhook_secret' => 'test-webhook-secret',
        ]);
    }

    public function test_submit_transcription_sends_expected_fields_and_returns_the_id(): void
    {
        Http::fake([
            'api.assemblyai.com/v2/transcript' => Http::response(['id' => 'transcript-123'], 200),
        ]);

        $id = (new AssemblyAiService())->submitTranscription(
            'https://spaces.example.com/signed-recording-url',
            'https://portal.example.com/api/assemblyai-webhook',
        );

        $this->assertSame('transcript-123', $id);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.assemblyai.com/v2/transcript'
                && $request->hasHeader('authorization', 'test-api-key')
                && ! $request->hasHeader('Authorization', 'Bearer test-api-key')
                && $request['audio_url'] === 'https://spaces.example.com/signed-recording-url'
                && $request['speaker_labels'] === true
                && $request['webhook_url'] === 'https://portal.example.com/api/assemblyai-webhook'
                && $request['webhook_auth_header_name'] === 'X-AssemblyAI-Webhook-Secret'
                && $request['webhook_auth_header_value'] === 'test-webhook-secret';
        });
    }

    public function test_fetch_transcript_parses_response(): void
    {
        Http::fake([
            'api.assemblyai.com/v2/transcript/transcript-123' => Http::response([
                'status'     => 'completed',
                'text'       => 'Hello there.',
                'utterances' => [['speaker' => 'A', 'text' => 'Hello there.']],
            ], 200),
        ]);

        $result = (new AssemblyAiService())->fetchTranscript('transcript-123');

        $this->assertSame('completed', $result['status']);
        $this->assertSame('Hello there.', $result['text']);
        $this->assertSame('A', $result['utterances'][0]['speaker']);
    }

    public function test_format_utterances_renders_speaker_labeled_lines(): void
    {
        $formatted = AssemblyAiService::formatUtterances([
            ['speaker' => 'A', 'text' => 'Hi, thanks for joining.'],
            ['speaker' => 'B', 'text' => "Happy to be here."],
            ['speaker' => 'A', 'text' => '  '], // blank utterance is skipped
        ]);

        $this->assertSame("Speaker A: Hi, thanks for joining.\nSpeaker B: Happy to be here.", $formatted);
    }
}
