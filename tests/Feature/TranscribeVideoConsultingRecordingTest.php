<?php

namespace Tests\Feature;

use App\Jobs\TranscribeVideoConsultingRecording;
use App\Models\Assignment;
use App\Services\AssemblyAiService;
use App\Services\SpacesStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TranscribeVideoConsultingRecordingTest extends TestCase
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
            'video_consulting_last_recording_id' => 'rec-1',
        ], $overrides));
    }

    public function test_submits_the_stored_recording_and_saves_the_transcription_id(): void
    {
        config([
            'services.assemblyai.api_key'        => 'test-api-key',
            'services.assemblyai.webhook_secret' => 'test-webhook-secret',
        ]);
        Storage::fake('do_spaces');
        Storage::disk('do_spaces')->put('video-consulting/TEST-VC-1/1-rec-1.mp4', 'fake-video-bytes');
        Http::fake([
            'api.assemblyai.com/v2/transcript' => Http::response(['id' => 'transcript-abc'], 200),
        ]);

        $assignment = $this->makeAssignment();

        (new TranscribeVideoConsultingRecording($assignment->id, 'rec-1', 'video-consulting/TEST-VC-1/1-rec-1.mp4'))
            ->handle(app(AssemblyAiService::class), app(SpacesStorageService::class));

        $this->assertSame('transcript-abc', $assignment->fresh()->video_consulting_transcription_id);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.assemblyai.com/v2/transcript'
                && str_contains($request['webhook_url'], '/api/assemblyai-webhook');
        });
    }

    public function test_does_nothing_if_a_newer_recording_has_already_superseded_this_one(): void
    {
        Http::fake(); // nothing should be called

        $assignment = $this->makeAssignment(['video_consulting_last_recording_id' => 'rec-newer']);

        (new TranscribeVideoConsultingRecording($assignment->id, 'rec-1', 'video-consulting/TEST-VC-1/1-rec-1.mp4'))
            ->handle(app(AssemblyAiService::class), app(SpacesStorageService::class));

        Http::assertNothingSent();
        $this->assertNull($assignment->fresh()->video_consulting_transcription_id);
    }
}
