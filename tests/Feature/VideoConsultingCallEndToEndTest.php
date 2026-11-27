<?php

// v1.0 — 2026-11-27 | Regression coverage for "do test rooms still work" after the call-page
//                      embed/countdown/extend upgrade — unlike TestDataControllerTest's own
//                      acceptance test, this one does NOT mock VideoConsultingCallService away.
//                      It fakes Whereby at the HTTP layer (like WherebyServiceTest) so acceptance
//                      runs the real WherebyService code path and the assignment ends up with a
//                      real whereby_room_url + call token, then exercises both call pages and the
//                      extend/status endpoints against that real data.

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\ReaderVideoConsultingRate;
use App\Models\Tier;
use App\Models\User;
use App\Models\VideoConsultingDuration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class VideoConsultingCallEndToEndTest extends TestCase
{
    use RefreshDatabase;

    private function acceptRealTestBooking(): array
    {
        Mail::fake();
        config(['services.whereby.api_key' => 'test-api-key']);

        $admin = User::factory()->create(['role' => 'admin']);
        $reader = User::factory()->create(['role' => 'reader']);
        $reader->readerProfile()->create(['initials' => 'TR', 'first_name' => 'Test', 'last_name' => 'Reader']);
        $tier = Tier::firstOrCreate(['name' => 'Tier 1'], ['position' => 1, 'is_onboarding' => false]);
        $reader->readerProfile->tiers()->sync([$tier->id]);

        $duration = VideoConsultingDuration::create([
            'minutes' => 30, 'label' => '30 Minutes', 'base_rate' => 20, 'min_reader_rate' => 10, 'max_reader_rate' => 100,
        ]);
        ReaderVideoConsultingRate::create(['reader_id' => $reader->id, 'duration_id' => $duration->id, 'rate' => 50]);

        $this->actingAs($admin)->post('/admin/test-data/video-consulting-booking', [
            'reader_id' => $reader->id, 'duration_id' => $duration->id,
        ])->assertRedirect();

        $assignment = Assignment::where('assignment_type', Assignment::TYPE_VIDEO_CONSULTING)->first();

        Http::fake([
            'api.whereby.dev/v1/meetings' => Http::response([
                'meetingId' => 'mtg-e2e-test',
                'roomName'  => '/e2e-test-room',
                'roomUrl'   => 'https://example.whereby.com/e2e-test-room',
                'startDate' => now()->toIso8601String(),
                'endDate'   => $assignment->videoConsultingHold->slot_end->toIso8601String(),
            ], 201),
        ]);

        $this->actingAs($reader)->post("/assignments/{$assignment->id}/accept")->assertRedirect();

        return [$reader, $assignment->fresh()];
    }

    public function test_accepting_a_test_booking_produces_a_working_reader_call_page(): void
    {
        [$reader, $assignment] = $this->acceptRealTestBooking();

        $this->assertSame('https://example.whereby.com/e2e-test-room', $assignment->whereby_room_url);
        $this->assertNotNull($assignment->video_consulting_call_token);

        $this->actingAs($reader)
            ->get("/video-consulting-bookings/{$assignment->id}/call")
            ->assertOk()
            ->assertSee('https://example.whereby.com/e2e-test-room', false)
            ->assertSee('call-timer', false)
            // The real extend-call route URL only gets wired into the Alpine component's
            // x-data when $extendUrl is non-null — the <template x-if> button markup itself
            // is always present server-side (Alpine hides it client-side), so asserting on the
            // URL string is what actually distinguishes "reader page" from "customer page".
            ->assertSee('extend-call', false);
    }

    public function test_accepting_a_test_booking_produces_a_working_public_customer_page_without_an_extend_button(): void
    {
        [, $assignment] = $this->acceptRealTestBooking();

        $this->get("/video-consulting-call/{$assignment->video_consulting_call_token}")
            ->assertOk()
            ->assertSee('https://example.whereby.com/e2e-test-room', false)
            ->assertSee('call-timer', false)
            ->assertDontSee('extend-call', false);
    }

    public function test_reader_can_extend_the_call_and_the_customer_status_endpoint_reflects_it(): void
    {
        [$reader, $assignment] = $this->acceptRealTestBooking();
        $originalEnd = $assignment->videoConsultingHold->slot_end;

        $response = $this->actingAs($reader)
            ->post("/video-consulting-bookings/{$assignment->id}/extend-call")
            ->assertOk();

        $response->assertJson(['capped' => false]);
        $assignment->refresh();
        $this->assertTrue($assignment->video_consulting_extended_until->equalTo($originalEnd->copy()->addMinutes(5)));

        $status = $this->get("/video-consulting-call/{$assignment->video_consulting_call_token}/status")->assertOk();
        $this->assertSame(
            $assignment->video_consulting_extended_until->toIso8601String(),
            $status->json('effective_end')
        );
    }

    public function test_extending_the_call_is_capped_at_max_extend_minutes(): void
    {
        [$reader, $assignment] = $this->acceptRealTestBooking();
        $originalEnd = $assignment->videoConsultingHold->slot_end;

        // 30 minutes of cap / 5-minute increments = 6 extensions to reach the cap exactly.
        for ($i = 0; $i < 6; $i++) {
            $this->actingAs($reader)->post("/video-consulting-bookings/{$assignment->id}/extend-call")->assertOk();
        }

        $assignment->refresh();
        $this->assertTrue($assignment->video_consulting_extended_until->equalTo($originalEnd->copy()->addMinutes(30)));

        $capped = $this->actingAs($reader)
            ->post("/video-consulting-bookings/{$assignment->id}/extend-call")
            ->assertOk();
        $capped->assertJson(['capped' => true]);

        $assignment->refresh();
        $this->assertTrue($assignment->video_consulting_extended_until->equalTo($originalEnd->copy()->addMinutes(30)));
    }

    public function test_other_reader_cannot_extend_someone_elses_call(): void
    {
        [, $assignment] = $this->acceptRealTestBooking();
        $otherReader = User::factory()->create(['role' => 'reader']);

        $this->actingAs($otherReader)
            ->post("/video-consulting-bookings/{$assignment->id}/extend-call")
            ->assertForbidden();
    }
}
