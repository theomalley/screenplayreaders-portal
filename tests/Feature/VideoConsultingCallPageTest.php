<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\User;
use App\Models\VideoConsultingDuration;
use App\Models\VideoConsultingHold;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VideoConsultingCallPageTest extends TestCase
{
    use RefreshDatabase;

    private function makeAcceptedBooking(?string $customerEmail = 'customer@example.com'): array
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $reader->readerProfile()->create(['initials' => 'TR', 'first_name' => 'Test', 'last_name' => 'Reader']);

        $duration = VideoConsultingDuration::create([
            'minutes' => 30, 'label' => '30 Minutes', 'base_rate' => 20, 'min_reader_rate' => 10, 'max_reader_rate' => 100,
        ]);

        $hold = VideoConsultingHold::create([
            'reader_id' => $reader->id, 'duration_id' => $duration->id,
            'slot_start' => now()->addDay(), 'slot_end' => now()->addDay()->addMinutes(30),
            'hold_token' => 'tok-' . uniqid(), 'status' => VideoConsultingHold::STATUS_CONVERTED, 'expires_at' => now(),
        ]);

        $assignment = Assignment::create([
            'order_number' => 'TEST-VC-' . random_int(100000, 999999), 'vendor' => 'sr',
            'assignment_type' => Assignment::TYPE_VIDEO_CONSULTING, 'script_title' => '60 Minute Script Consultation',
            'writer_name' => 'Jane Writer',
            'pay_rate' => 50, 'status' => Assignment::STATUS_ASSIGNED, 'is_test' => true,
            'assigned_reader_id' => $reader->id, 'video_consulting_hold_id' => $hold->id,
            'video_consulting_customer_email' => $customerEmail,
            'whereby_room_url' => 'https://example.whereby.com/room-xyz',
            'whereby_room_name' => 'room-xyz',
            'video_consulting_call_token' => bin2hex(random_bytes(32)),
        ]);

        return [$reader, $assignment];
    }

    public function test_public_call_page_renders_for_valid_token(): void
    {
        [, $assignment] = $this->makeAcceptedBooking();

        $this->get("/video-consulting-call/{$assignment->video_consulting_call_token}")
            ->assertOk()
            ->assertSee('60 Minute Script Consultation')
            ->assertSee('https://example.whereby.com/room-xyz', false);
    }

    public function test_public_call_page_404s_for_invalid_token(): void
    {
        $this->get('/video-consulting-call/not-a-real-token')->assertNotFound();
    }

    public function test_public_call_page_shows_not_ready_message_when_room_is_missing(): void
    {
        [, $assignment] = $this->makeAcceptedBooking();
        $assignment->update(['whereby_room_url' => null, 'whereby_room_name' => null]);

        $this->get("/video-consulting-call/{$assignment->video_consulting_call_token}")
            ->assertOk()
            ->assertSee("isn&#039;t set up yet", false);
    }

    public function test_assigned_reader_can_view_their_own_call_page(): void
    {
        [$reader, $assignment] = $this->makeAcceptedBooking();

        $this->actingAs($reader)
            ->get("/video-consulting-bookings/{$assignment->id}/call")
            ->assertOk()
            ->assertSee('Jane Writer');
    }

    public function test_other_reader_cannot_view_someone_elses_call_page(): void
    {
        [, $assignment] = $this->makeAcceptedBooking();
        $otherReader = User::factory()->create(['role' => 'reader']);

        $this->actingAs($otherReader)
            ->get("/video-consulting-bookings/{$assignment->id}/call")
            ->assertForbidden();
    }

    public function test_admin_can_view_any_call_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [, $assignment] = $this->makeAcceptedBooking();

        $this->actingAs($admin)
            ->get("/video-consulting-bookings/{$assignment->id}/call")
            ->assertOk();
    }

    public function test_preview_page_renders_sample_content_without_a_real_assignment(): void
    {
        $before = \App\Models\Assignment::count();

        $this->get('/video-consulting-call/preview')
            ->assertOk()
            ->assertSee('The Great American Screenplay (Sample)')
            ->assertSee('Jane Reader', false);

        $this->assertSame($before, \App\Models\Assignment::count());
    }

    public function test_preview_page_reflects_saved_call_page_settings(): void
    {
        \App\Models\Setting::setValue('call_page_heading', 'Custom Preview Heading');
        \App\Models\Setting::setValue('call_page_logo_url', 'https://example.com/logo.png');

        $this->get('/video-consulting-call/preview')
            ->assertOk()
            ->assertSee('Custom Preview Heading')
            ->assertSee('https://example.com/logo.png', false);
    }
}
