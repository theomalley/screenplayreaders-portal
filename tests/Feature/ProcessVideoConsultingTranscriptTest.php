<?php

namespace Tests\Feature;

use App\Jobs\ProcessVideoConsultingTranscript;
use App\Models\Assignment;
use App\Services\AssemblyAiService;
use App\Services\ContactInfoDetectionService;
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

    public function test_completed_transcript_is_saved_and_flagged_when_contact_info_is_found(): void
    {
        $this->fakeFetch([
            'status'     => 'completed',
            'utterances' => [
                ['speaker' => 'A', 'text' => 'Call me at 555-0100.'],
                ['speaker' => 'B', 'text' => 'Got it, will do.'],
            ],
        ]);
        $this->mock(ContactInfoDetectionService::class, function ($mock) {
            $mock->shouldReceive('check')
                ->once()
                ->with("Speaker A: Call me at 555-0100.\nSpeaker B: Got it, will do.")
                ->andReturn(['flagged' => true, 'evidence' => 'Reader gave out a phone number: "Call me at 555-0100."']);
        });

        $assignment = $this->makeAssignment();

        (new ProcessVideoConsultingTranscript($assignment->id, 'transcript-123', 'completed'))
            ->handle(app(AssemblyAiService::class), app(ContactInfoDetectionService::class));

        $fresh = $assignment->fresh();
        $this->assertSame("Speaker A: Call me at 555-0100.\nSpeaker B: Got it, will do.", $fresh->video_consulting_transcript);
        $this->assertTrue($fresh->video_consulting_contact_info_flag);
        $this->assertStringContainsString('555-0100', $fresh->video_consulting_contact_info_evidence);
    }

    public function test_completed_transcript_with_no_utterances_falls_back_to_plain_text(): void
    {
        $this->fakeFetch(['status' => 'completed', 'text' => 'Plain transcript text.', 'utterances' => []]);
        $this->mock(ContactInfoDetectionService::class, function ($mock) {
            $mock->shouldReceive('check')->once()->andReturn(['flagged' => false, 'evidence' => '']);
        });

        $assignment = $this->makeAssignment();

        (new ProcessVideoConsultingTranscript($assignment->id, 'transcript-123', 'completed'))
            ->handle(app(AssemblyAiService::class), app(ContactInfoDetectionService::class));

        $fresh = $assignment->fresh();
        $this->assertSame('Plain transcript text.', $fresh->video_consulting_transcript);
        $this->assertFalse($fresh->video_consulting_contact_info_flag);
        $this->assertNull($fresh->video_consulting_contact_info_evidence);
    }

    public function test_transcript_is_still_saved_if_the_contact_info_check_itself_fails(): void
    {
        $this->fakeFetch(['status' => 'completed', 'utterances' => [['speaker' => 'A', 'text' => 'Hello.']]]);
        $this->mock(ContactInfoDetectionService::class, function ($mock) {
            $mock->shouldReceive('check')->once()->andThrow(new \RuntimeException('Anthropic API down'));
        });

        $assignment = $this->makeAssignment();

        (new ProcessVideoConsultingTranscript($assignment->id, 'transcript-123', 'completed'))
            ->handle(app(AssemblyAiService::class), app(ContactInfoDetectionService::class));

        $fresh = $assignment->fresh();
        $this->assertSame('Speaker A: Hello.', $fresh->video_consulting_transcript);
        $this->assertNull($fresh->video_consulting_contact_info_flag);
    }

    public function test_error_status_saves_nothing(): void
    {
        $this->fakeFetch(['status' => 'error', 'error' => 'invalid audio file']);
        $this->mock(ContactInfoDetectionService::class, function ($mock) {
            $mock->shouldNotReceive('check');
        });

        $assignment = $this->makeAssignment();

        (new ProcessVideoConsultingTranscript($assignment->id, 'transcript-123', 'error'))
            ->handle(app(AssemblyAiService::class), app(ContactInfoDetectionService::class));

        $this->assertNull($assignment->fresh()->video_consulting_transcript);
    }

    public function test_stale_webhook_for_a_transcription_id_no_longer_tracked_is_a_no_op(): void
    {
        Http::fake(); // nothing should be called
        $this->mock(ContactInfoDetectionService::class, function ($mock) {
            $mock->shouldNotReceive('check');
        });

        $assignment = $this->makeAssignment(['video_consulting_transcription_id' => 'transcript-newer']);

        (new ProcessVideoConsultingTranscript($assignment->id, 'transcript-123', 'completed'))
            ->handle(app(AssemblyAiService::class), app(ContactInfoDetectionService::class));

        Http::assertNothingSent();
        $this->assertNull($assignment->fresh()->video_consulting_transcript);
    }
}
