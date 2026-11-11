<?php

namespace Tests\Feature;

use App\Jobs\DownloadWherebyRecording;
use App\Models\Assignment;
use App\Models\User;
use App\Models\VideoConsultingDuration;
use App\Models\VideoConsultingHold;
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

    private function makeAssignmentWithSlot(\Illuminate\Support\Carbon $slotStart, array $overrides = []): Assignment
    {
        $reader = User::factory()->create(['role' => 'reader']);

        $duration = VideoConsultingDuration::create([
            'minutes' => 30, 'label' => '30 Minutes', 'base_rate' => 20, 'min_reader_rate' => 10, 'max_reader_rate' => 100,
        ]);

        $hold = VideoConsultingHold::create([
            'reader_id' => $reader->id, 'duration_id' => $duration->id,
            'slot_start' => $slotStart, 'slot_end' => $slotStart->copy()->addMinutes(30),
            'hold_token' => 'tok-' . uniqid(), 'status' => VideoConsultingHold::STATUS_CONVERTED, 'expires_at' => now(),
        ]);

        return $this->makeAssignment(array_merge([
            'video_consulting_hold_id' => $hold->id,
            'assigned_reader_id'       => $reader->id,
        ], $overrides));
    }

    private function fakeWherebyDownload(): void
    {
        config(['services.whereby.api_key' => 'test-api-key']);
        Storage::fake('do_spaces');
        Http::fake([
            'api.whereby.dev/v1/recordings/*' => Http::response(['accessLink' => 'https://example.com/signed.mp4', 'expires' => 9999999999999], 200),
            'example.com/*' => Http::response('fake-video-bytes', 200),
        ]);
    }

    public function test_downloads_stores_to_spaces_and_routes_to_qc(): void
    {
        $this->fakeWherebyDownload();

        $assignment = $this->makeAssignment();

        (new DownloadWherebyRecording($assignment->id, 'rec-1', 'call.mp4'))
            ->handle(app(WherebyService::class), app(SpacesStorageService::class));

        $fresh = $assignment->fresh();
        $this->assertNotNull($fresh->video_consulting_recording_path);
        $this->assertSame('rec-1', $fresh->video_consulting_last_recording_id);
        $this->assertSame(Assignment::STATUS_QC, $fresh->status);
        $this->assertNotNull($fresh->submitted_at);
        Storage::disk('do_spaces')->assertExists($fresh->video_consulting_recording_path);
    }

    public function test_is_idempotent_once_this_exact_recording_id_is_already_processed(): void
    {
        $this->fakeWherebyDownload();
        Http::fake(); // nothing should be called

        $assignment = $this->makeAssignment([
            'video_consulting_recording_path'    => 'video-consulting/already/there.mp4',
            'video_consulting_last_recording_id' => 'rec-1',
        ]);

        (new DownloadWherebyRecording($assignment->id, 'rec-1', 'call.mp4'))
            ->handle(app(WherebyService::class), app(SpacesStorageService::class));

        Http::assertNothingSent();
        $this->assertSame(Assignment::STATUS_ASSIGNED, $assignment->fresh()->status);
    }

    public function test_does_not_overwrite_a_status_that_already_moved_past_assigned(): void
    {
        $this->fakeWherebyDownload();

        $assignment = $this->makeAssignment(['status' => Assignment::STATUS_NEEDS_ATTENTION]);

        (new DownloadWherebyRecording($assignment->id, 'rec-1', 'call.mp4'))
            ->handle(app(WherebyService::class), app(SpacesStorageService::class));

        $this->assertSame(Assignment::STATUS_NEEDS_ATTENTION, $assignment->fresh()->status);
        $this->assertNotNull($assignment->fresh()->video_consulting_recording_path);
    }

    public function test_an_early_recording_days_before_the_scheduled_call_does_not_complete_the_booking(): void
    {
        $this->fakeWherebyDownload();

        $assignment = $this->makeAssignmentWithSlot(now()->addDays(6));

        (new DownloadWherebyRecording($assignment->id, 'rec-early-test', 'call.mp4'))
            ->handle(app(WherebyService::class), app(SpacesStorageService::class));

        $fresh = $assignment->fresh();
        $this->assertSame(Assignment::STATUS_ASSIGNED, $fresh->status);
        $this->assertNull($fresh->submitted_at);
        $this->assertNotNull($fresh->video_consulting_recording_path);
        $this->assertSame('rec-early-test', $fresh->video_consulting_last_recording_id);
    }

    public function test_a_recording_at_the_scheduled_call_time_does_complete_the_booking(): void
    {
        $this->fakeWherebyDownload();

        $assignment = $this->makeAssignmentWithSlot(now()->subMinutes(5));

        (new DownloadWherebyRecording($assignment->id, 'rec-real-call', 'call.mp4'))
            ->handle(app(WherebyService::class), app(SpacesStorageService::class));

        $fresh = $assignment->fresh();
        $this->assertSame(Assignment::STATUS_QC, $fresh->status);
        $this->assertNotNull($fresh->submitted_at);
    }

    public function test_a_later_real_recording_overwrites_an_earlier_test_recording_and_completes_the_booking(): void
    {
        $this->fakeWherebyDownload();

        $assignment = $this->makeAssignmentWithSlot(now()->addHour());

        // Early/test join: recording finishes well before the scheduled call.
        (new DownloadWherebyRecording($assignment->id, 'rec-early-test', 'call.mp4'))
            ->handle(app(WherebyService::class), app(SpacesStorageService::class));

        $midway = $assignment->fresh();
        $this->assertSame(Assignment::STATUS_ASSIGNED, $midway->status);
        $this->assertSame('rec-early-test', $midway->video_consulting_last_recording_id);

        // The real call happens later; travel to right around slot_start and process the real recording.
        $this->travelTo($assignment->videoConsultingHold->slot_start->copy()->addMinutes(2));

        (new DownloadWherebyRecording($assignment->id, 'rec-real-call', 'call2.mp4'))
            ->handle(app(WherebyService::class), app(SpacesStorageService::class));

        $final = $assignment->fresh();
        $this->assertSame('rec-real-call', $final->video_consulting_last_recording_id);
        $this->assertNotNull($final->video_consulting_recording_path);
        $this->assertSame(Assignment::STATUS_QC, $final->status);
        $this->assertNotNull($final->submitted_at);
        Http::assertSentCount(4); // 2 downloads (access-link + body fetch) x 2 recordings

        $this->travelBack();
    }
}
