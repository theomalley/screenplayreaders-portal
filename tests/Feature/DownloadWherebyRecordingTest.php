<?php

namespace Tests\Feature;

use App\Jobs\DownloadWherebyRecording;
use App\Jobs\TranscribeVideoConsultingRecording;
use App\Models\Assignment;
use App\Models\User;
use App\Models\VideoConsultingDuration;
use App\Models\VideoConsultingHold;
use App\Services\SpacesStorageService;
use App\Services\WherebyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
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
        // TranscribeVideoConsultingRecording is dispatched at the end of handle() — faking the
        // queue keeps these tests scoped to DownloadWherebyRecording's own behavior instead of
        // also exercising (and needing credentials for) the transcription pipeline it kicks off.
        Queue::fake();
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

        Queue::assertPushed(TranscribeVideoConsultingRecording::class, function ($job) use ($fresh) {
            return $job->assignmentId === $fresh->id
                && $job->recordingId === 'rec-1'
                && $job->recordingPath === $fresh->video_consulting_recording_path;
        });
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

    public function test_a_recording_well_before_the_scheduled_call_still_completes_the_booking(): void
    {
        // Recording can only exist at all if two real participants were in the room together
        // (WherebyService uses recording.startTrigger = automatic-2nd-participant) — so even a
        // recording that lands days before the scheduled slot reflects a real conversation and
        // must still reach QC for review (contact-info/transcript monitoring happens there).
        $this->fakeWherebyDownload();

        $assignment = $this->makeAssignmentWithSlot(now()->addDays(6));

        (new DownloadWherebyRecording($assignment->id, 'rec-early-real-call', 'call.mp4'))
            ->handle(app(WherebyService::class), app(SpacesStorageService::class));

        $fresh = $assignment->fresh();
        $this->assertSame(Assignment::STATUS_QC, $fresh->status);
        $this->assertNotNull($fresh->submitted_at);
        $this->assertNotNull($fresh->video_consulting_recording_path);
        $this->assertSame('rec-early-real-call', $fresh->video_consulting_last_recording_id);
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

    public function test_a_later_recording_overwrites_an_earlier_ones_stored_file_without_disturbing_an_already_completed_status(): void
    {
        $this->fakeWherebyDownload();

        $assignment = $this->makeAssignmentWithSlot(now()->addHour());

        // First session: already completes the booking (any recording now means two real
        // participants were present, per the automatic-2nd-participant trigger).
        (new DownloadWherebyRecording($assignment->id, 'rec-first', 'call.mp4'))
            ->handle(app(WherebyService::class), app(SpacesStorageService::class));

        $midway = $assignment->fresh();
        $this->assertSame(Assignment::STATUS_QC, $midway->status);
        $firstSubmittedAt = $midway->submitted_at;
        $this->assertSame('rec-first', $midway->video_consulting_last_recording_id);
        $firstPath = $midway->video_consulting_recording_path;

        // A second, later recording for the same assignment (e.g. they reconnected) must NOT
        // destroy the first recording's file — each recording gets its own storage path now.
        $this->travelTo($assignment->videoConsultingHold->slot_start->copy()->addMinutes(2));

        (new DownloadWherebyRecording($assignment->id, 'rec-second', 'call2.mp4'))
            ->handle(app(WherebyService::class), app(SpacesStorageService::class));

        $final = $assignment->fresh();
        $this->assertSame('rec-second', $final->video_consulting_last_recording_id);
        $this->assertNotNull($final->video_consulting_recording_path);
        $this->assertNotSame($firstPath, $final->video_consulting_recording_path);
        $this->assertSame(Assignment::STATUS_QC, $final->status);
        $this->assertEquals($firstSubmittedAt, $final->submitted_at);
        Storage::disk('do_spaces')->assertExists($firstPath);
        Storage::disk('do_spaces')->assertExists($final->video_consulting_recording_path);
        Http::assertSentCount(4); // 2 downloads (access-link + body fetch) x 2 recordings

        $this->travelBack();
    }
}
