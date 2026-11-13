<?php

namespace Tests\Feature;

use App\Jobs\ProcessVideoConsultingTranscript;
use App\Models\Assignment;
use App\Services\AssemblyAiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProcessVideoConsultingTranscriptTest extends TestCase
{
    use RefreshDatabase;

    private function makeAssignment(array $overrides = []): Assignment
    {
        return Assignment::create(array_merge([
            'order_number'      => 'TEST-VC-' . random_int(100000, 999999),
            'vendor'            => 'sr',
            'assignment_type'   => Assignment::TYPE_VIDEO_CONSULTING,
            'script_title'      => '30 Minutes Video Consulting Call',
            'writer_name'       => 'N/A (test booking)',
            'pay_rate'          => 50,
            'status'            => Assignment::STATUS_QC,
            'is_test'           => true,
            'video_consulting_transcription_id' => 'transcript-123',
        ], $overrides));
    }

    private function fakeFetch(array $body): void
    {
        config(['services.assemblyai.api_key' => 'test-api-key']);
        Http::fake([
            'api.assemblyai.com/v2/transcript/transcript-123' => Http::response($body, 200),
        ]);
    }

    public function test_completed_transcript_is_saved_with_speaker_labels(): void
    {
        $this->fakeFetch([
            'status'     => 'completed',
            'utterances' => [
                ['speaker' => 'A', 'text' => 'Thanks for joining.'],
                ['speaker' => 'B', 'text' => 'Happy to be here.'],
            ],
        ]);

        $assignment = $this->makeAssignment();

        (new ProcessVideoConsultingTranscript($assignment->id, 'transcript-123', 'completed'))
            ->handle(app(AssemblyAiService::class));

        $this->assertSame(
            "Speaker A: Thanks for joining.\nSpeaker B: Happy to be here.",
            $assignment->fresh()->video_consulting_transcript,
        );
    }

    public function test_completed_transcript_with_no_utterances_falls_back_to_plain_text(): void
    {
        $this->fakeFetch(['status' => 'completed', 'text' => 'Plain transcript text.', 'utterances' => []]);

        $assignment = $this->makeAssignment();

        (new ProcessVideoConsultingTranscript($assignment->id, 'transcript-123', 'completed'))
            ->handle(app(AssemblyAiService::class));

        $this->assertSame('Plain transcript text.', $assignment->fresh()->video_consulting_transcript);
    }

    public function test_error_status_saves_nothing(): void
    {
        $this->fakeFetch(['status' => 'error', 'error' => 'invalid audio file']);

        $assignment = $this->makeAssignment();

        (new ProcessVideoConsultingTranscript($assignment->id, 'transcript-123', 'error'))
            ->handle(app(AssemblyAiService::class));

        $this->assertNull($assignment->fresh()->video_consulting_transcript);
    }

    public function test_stale_webhook_for_a_transcription_id_no_longer_tracked_is_a_no_op(): void
    {
        Http::fake(); // nothing should be called

        $assignment = $this->makeAssignment(['video_consulting_transcription_id' => 'transcript-newer']);

        (new ProcessVideoConsultingTranscript($assignment->id, 'transcript-123', 'completed'))
            ->handle(app(AssemblyAiService::class));

        Http::assertNothingSent();
        $this->assertNull($assignment->fresh()->video_consulting_transcript);
    }
}
