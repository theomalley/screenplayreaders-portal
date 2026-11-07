<?php

namespace Tests\Feature;

use App\Jobs\ResetTestAssignment;
use App\Models\Assignment;
use App\Models\ReaderVideoConsultingRate;
use App\Models\User;
use App\Models\VideoConsultingDuration;
use App\Models\VideoConsultingHold;
use App\Services\VideoConsultingCallService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class TestDataControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_is_not_forbidden_from_the_test_data_page(): void
    {
        // Not assertOk(): the full view needs a built Vite manifest, which is a frontend-
        // build concern unrelated to authorization. What matters here is that the Gate
        // lets an admin past the 403 boundary.
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/admin/test-data');

        $this->assertNotEquals(403, $response->status());
    }

    public function test_admin_can_toggle_auto_reset(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post('/admin/test-data/auto-reset', ['enabled' => '1'])->assertRedirect();
    }

    public function test_editor_is_forbidden_from_every_test_data_action(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);

        $this->actingAs($editor)->get('/admin/test-data')->assertForbidden();
        $this->actingAs($editor)->post('/admin/test-data/seed')->assertForbidden();
        $this->actingAs($editor)->post('/admin/test-data/reset')->assertForbidden();
        $this->actingAs($editor)->delete('/admin/test-data')->assertForbidden();
        $this->actingAs($editor)->post('/admin/test-data/script')->assertForbidden();
        $this->actingAs($editor)->post('/admin/test-data/auto-reset')->assertForbidden();
    }

    public function test_reader_is_forbidden_from_every_test_data_action(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);

        $this->actingAs($reader)->get('/admin/test-data')->assertForbidden();
        $this->actingAs($reader)->post('/admin/test-data/seed')->assertForbidden();
        $this->actingAs($reader)->post('/admin/test-data/reset')->assertForbidden();
        $this->actingAs($reader)->delete('/admin/test-data')->assertForbidden();
        $this->actingAs($reader)->post('/admin/test-data/script')->assertForbidden();
        $this->actingAs($reader)->post('/admin/test-data/auto-reset')->assertForbidden();
        $this->actingAs($reader)->post('/admin/test-data/video-consulting-booking')->assertForbidden();
    }

    private function makeReaderWithRate(): array
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $reader->readerProfile()->create(['initials' => 'TR', 'first_name' => 'Test', 'last_name' => 'Reader']);

        // AssignmentPolicy::accept()'s tierMatch() requires the reader to belong to the same
        // tier the test booking will be attached to (mirrors a real reader's onboarding).
        $tier = \App\Models\Tier::firstOrCreate(['name' => 'Tier 1'], ['position' => 1, 'is_onboarding' => false]);
        $reader->readerProfile->tiers()->sync([$tier->id]);

        $duration = VideoConsultingDuration::create([
            'minutes' => 30, 'label' => '30 Minutes', 'base_rate' => 20, 'min_reader_rate' => 10, 'max_reader_rate' => 100,
        ]);
        ReaderVideoConsultingRate::create(['reader_id' => $reader->id, 'duration_id' => $duration->id, 'rate' => 50]);

        return [$reader, $duration];
    }

    public function test_admin_can_create_a_test_video_consulting_booking(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$reader, $duration] = $this->makeReaderWithRate();

        $this->actingAs($admin)->post('/admin/test-data/video-consulting-booking', [
            'reader_id' => $reader->id, 'duration_id' => $duration->id,
        ])->assertRedirect();

        $assignment = Assignment::where('assignment_type', Assignment::TYPE_VIDEO_CONSULTING)->first();
        $this->assertNotNull($assignment);
        $this->assertTrue($assignment->is_test);
        $this->assertSame(Assignment::STATUS_UNASSIGNED, $assignment->status);
        $this->assertSame($reader->id, $assignment->requested_reader_id);
        $this->assertSame(50.0, (float) $assignment->pay_rate);

        $hold = VideoConsultingHold::find($assignment->video_consulting_hold_id);
        $this->assertNotNull($hold);
        $this->assertSame(VideoConsultingHold::STATUS_CONVERTED, $hold->status);
        $this->assertSame($assignment->id, $hold->converted_assignment_id);
    }

    public function test_creating_a_booking_for_a_duration_the_reader_doesnt_offer_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $reader = User::factory()->create(['role' => 'reader']);
        $duration = VideoConsultingDuration::create([
            'minutes' => 30, 'label' => '30 Minutes', 'base_rate' => 20, 'min_reader_rate' => 10, 'max_reader_rate' => 100,
        ]);

        $this->actingAs($admin)->post('/admin/test-data/video-consulting-booking', [
            'reader_id' => $reader->id, 'duration_id' => $duration->id,
        ])->assertStatus(422);
    }

    public function test_accepting_the_test_booking_creates_the_whereby_room(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$reader, $duration] = $this->makeReaderWithRate();

        $this->actingAs($admin)->post('/admin/test-data/video-consulting-booking', [
            'reader_id' => $reader->id, 'duration_id' => $duration->id,
        ]);
        $assignment = Assignment::where('assignment_type', Assignment::TYPE_VIDEO_CONSULTING)->first();

        $this->mock(VideoConsultingCallService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createRoomFor')->once();
        });

        $this->actingAs($reader)
            ->post("/assignments/{$assignment->id}/accept")
            ->assertRedirect();

        $this->assertSame(Assignment::STATUS_ASSIGNED, $assignment->fresh()->status);
        $this->assertSame($reader->id, $assignment->fresh()->assigned_reader_id);
        $this->assertSame(50.0, (float) $assignment->fresh()->pay_rate); // unchanged by the saving() rate recompute
    }

    public function test_reset_all_clears_video_consulting_columns_and_drops_the_test_hold(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$reader, $duration] = $this->makeReaderWithRate();
        $this->actingAs($admin)->post('/admin/test-data/video-consulting-booking', [
            'reader_id' => $reader->id, 'duration_id' => $duration->id,
        ]);
        $assignment = Assignment::where('assignment_type', Assignment::TYPE_VIDEO_CONSULTING)->first();
        $assignment->update(['whereby_room_url' => 'https://x.whereby.com/room', 'whereby_room_name' => '/room']);

        $this->actingAs($admin)->post('/admin/test-data/reset')->assertRedirect();

        $this->assertNull($assignment->fresh()->whereby_room_url);
        $this->assertNull($assignment->fresh()->whereby_room_name);
    }

    public function test_destroy_all_also_deletes_the_associated_test_holds(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$reader, $duration] = $this->makeReaderWithRate();
        $this->actingAs($admin)->post('/admin/test-data/video-consulting-booking', [
            'reader_id' => $reader->id, 'duration_id' => $duration->id,
        ]);
        $holdId = Assignment::where('assignment_type', Assignment::TYPE_VIDEO_CONSULTING)->first()->video_consulting_hold_id;

        $this->actingAs($admin)->delete('/admin/test-data')->assertRedirect();

        $this->assertNull(VideoConsultingHold::find($holdId));
    }

    public function test_reset_test_assignment_job_clears_video_consulting_columns_and_hold(): void
    {
        [$reader, $duration] = $this->makeReaderWithRate();
        $hold = VideoConsultingHold::create([
            'reader_id' => $reader->id, 'duration_id' => $duration->id,
            'slot_start' => now(), 'slot_end' => now()->addMinutes(30),
            'hold_token' => 'token-reset-test', 'status' => VideoConsultingHold::STATUS_CONVERTED, 'expires_at' => now(),
        ]);
        $assignment = Assignment::create([
            'order_number' => 'TEST-VC-' . random_int(100000, 999999), 'vendor' => 'sr',
            'assignment_type' => Assignment::TYPE_VIDEO_CONSULTING, 'script_title' => 'x', 'writer_name' => 'x',
            'pay_rate' => 50, 'status' => Assignment::STATUS_COMPLETED, 'is_test' => true,
            'assigned_reader_id' => $reader->id, 'video_consulting_hold_id' => $hold->id,
            'whereby_room_url' => 'https://x.whereby.com/room', 'whereby_room_name' => '/room',
        ]);
        $hold->update(['converted_assignment_id' => $assignment->id]);

        (new ResetTestAssignment($assignment->id))->handle();

        $fresh = $assignment->fresh();
        $this->assertSame(Assignment::STATUS_UNASSIGNED, $fresh->status);
        $this->assertNull($fresh->video_consulting_hold_id);
        $this->assertNull($fresh->whereby_room_url);
        $this->assertNull(VideoConsultingHold::find($hold->id));
    }
}
