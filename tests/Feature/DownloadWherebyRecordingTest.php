<?php

namespace Tests\Feature;

use App\Jobs\DownloadWherebyRecording;
use App\Models\Assignment;
use App\Services\SpacesStorageService;
use App\Services\WherebyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DownloadWherebyRecordingTest extends TestCase
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
            'status'            => Assignment::STATUS_ASSIGNED,
            'is_test'           => true,
            'whereby_room_name' => '/room-abc',
        ], $overrides));
    }

    public function test_downloads_stores_to_spaces_and_routes_to_qc(): void
    {
        config(['services.whereby.api_key' => 'test-api-key']);
        Storage::fake('do_spaces');
        Http::fake([
            'api.whereby.dev/v1/recordings/*' => Http::response(['accessLink' => 'https://example.com/signed.mp4', 'expires' => 9999999999999], 200),
            'example.com/*' => Http::response('fake-video-bytes', 200),
        ]);

        $assignment = $this->makeAssignment();

        (new DownloadWherebyRecording($assignment->id, 'rec-1', 'call.mp4'))
            ->handle(app(WherebyService::class), app(SpacesStorageService::class));

        $fresh = $assignment->fresh();
        $this->assertNotNull($fresh->video_consulting_recording_path);
        $this->assertSame(Assignment::STATUS_QC, $fresh->status);
        $this->assertNotNull($fresh->submitted_at);
        Storage::disk('do_spaces')->assertExists($fresh->video_consulting_recording_path);
    }

    public function test_is_idempotent_once_a_recording_path_is_already_set(): void
    {
        config(['services.whereby.api_key' => 'test-api-key']);
        Storage::fake('do_spaces');
        Http::fake(); // nothing should be called

        $assignment = $this->makeAssignment(['video_consulting_recording_path' => 'video-consulting/already/there.mp4']);

        (new DownloadWherebyRecording($assignment->id, 'rec-1', 'call.mp4'))
            ->handle(app(WherebyService::class), app(SpacesStorageService::class));

        Http::assertNothingSent();
        $this->assertSame(Assignment::STATUS_ASSIGNED, $assignment->fresh()->status);
    }

    public function test_does_not_overwrite_a_status_that_already_moved_past_assigned(): void
    {
        config(['services.whereby.api_key' => 'test-api-key']);
        Storage::fake('do_spaces');
        Http::fake([
            'api.whereby.dev/v1/recordings/*' => Http::response(['accessLink' => 'https://example.com/signed.mp4', 'expires' => 9999999999999], 200),
            'example.com/*' => Http::response('fake-video-bytes', 200),
        ]);

        $assignment = $this->makeAssignment(['status' => Assignment::STATUS_NEEDS_ATTENTION]);

        (new DownloadWherebyRecording($assignment->id, 'rec-1', 'call.mp4'))
            ->handle(app(WherebyService::class), app(SpacesStorageService::class));

        $this->assertSame(Assignment::STATUS_NEEDS_ATTENTION, $assignment->fresh()->status);
        $this->assertNotNull($assignment->fresh()->video_consulting_recording_path);
    }
}
