<?php

namespace Tests\Feature;

use App\Mail\NewAssignmentMail;
use App\Mail\VideoConsultingBookingConfirmedMail;
use App\Models\Assignment;
use App\Models\ReaderAvailabilityRule;
use App\Models\ReaderVideoConsultingRate;
use App\Models\Tier;
use App\Models\User;
use App\Models\VideoConsultingDuration;
use App\Models\VideoConsultingHold;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class VideoConsultingBookingApiTest extends TestCase
{
    use RefreshDatabase;

    private function authHeaders(): array
    {
        config(['services.portal.webhook_secret' => 'test-secret']);

        return ['Authorization' => 'Bearer test-secret'];
    }

    private function makeBookableReader(bool $notifiable = false): array
    {
        $reader = User::factory()->create(['role' => 'reader', 'name' => 'Jamie Reader']);
        $reader->readerProfile()->create([
            'initials' => 'JR', 'first_name' => 'Jamie', 'last_name' => 'Reader',
            'email_notifications' => $notifiable, 'email_notify_requests' => $notifiable,
        ]);
        $duration = VideoConsultingDuration::create([
            'minutes' => 30, 'label' => '30 Minutes', 'base_rate' => 20, 'min_reader_rate' => 10, 'max_reader_rate' => 100,
        ]);
        $rate = ReaderVideoConsultingRate::create(['reader_id' => $reader->id, 'duration_id' => $duration->id, 'rate' => 50]);

        $day = Carbon::now()->addDays(14)->startOfDay();
        ReaderAvailabilityRule::create([
            'reader_id' => $reader->id, 'day_of_week' => $day->dayOfWeek,
            'start_time' => '09:00', 'end_time' => '11:00', 'enabled' => true,
        ]);

        return [$reader, $duration, $rate, $day];
    }

    private function makeHold(User $reader, VideoConsultingDuration $duration, Carbon $slotStart, string $status = VideoConsultingHold::STATUS_HELD): VideoConsultingHold
    {
        return VideoConsultingHold::create([
            'reader_id' => $reader->id, 'duration_id' => $duration->id,
            'slot_start' => $slotStart, 'slot_end' => $slotStart->copy()->addMinutes($duration->minutes),
            'hold_token' => 'tok-' . uniqid(), 'status' => $status,
            'expires_at' => $status === VideoConsultingHold::STATUS_HELD ? now()->addMinutes(20) : now(),
        ]);
    }

    public function test_readers_endpoint_lists_a_reader_with_availability_summary_and_durations(): void
    {
        [$reader, $duration] = $this->makeBookableReader();

        $response = $this->withHeaders($this->authHeaders())->getJson('/api/video-consulting/readers');

        $response->assertOk();
        $response->assertJsonFragment(['id' => $reader->id, 'name' => 'Jamie Reader']);
        $payload = $response->json();
        $this->assertStringContainsString('9:00 AM', $payload[0]['availability_summary']);
        $this->assertSame($duration->id, $payload[0]['durations'][0]['duration_id']);
        $this->assertEquals(70.0, $payload[0]['durations'][0]['price']); // base_rate 20 + reader_rate 50
    }

    public function test_readers_endpoint_excludes_a_reader_with_no_rates(): void
    {
        User::factory()->create(['role' => 'reader']);

        $response = $this->withHeaders($this->authHeaders())->getJson('/api/video-consulting/readers');

        $response->assertOk();
        $this->assertCount(0, $response->json());
    }

    public function test_readers_endpoint_excludes_a_reader_with_access_disabled(): void
    {
        [$reader] = $this->makeBookableReader();
        $reader->readerProfile->update(['video_consulting_enabled' => false]);

        $response = $this->withHeaders($this->authHeaders())->getJson('/api/video-consulting/readers');

        $response->assertOk();
        $this->assertCount(0, $response->json());
    }

    public function test_create_booking_converts_a_held_slot_into_an_assignment_and_notifies(): void
    {
        Mail::fake();
        // The migration-seeded default tiers ("Tier 0" onboarding, "Tier 1", "Tier 2") already
        // exist from a fresh migration — reuse the one the controller's own default-tier lookup
        // will pick, rather than creating a duplicate "Tier 1" that wins the tie-break instead.
        $tier = Tier::where('is_onboarding', false)->orderBy('position')->first();
        [$reader, $duration] = $this->makeBookableReader(notifiable: true);
        $reader->readerProfile->tiers()->sync([$tier->id]);
        $hold = $this->makeHold($reader, $duration, now()->addDay());

        $response = $this->withHeaders($this->authHeaders())->postJson('/api/video-consulting/bookings', [
            'hold_token' => $hold->hold_token,
            'order_number' => '#5001',
            'customer_name' => 'Pat Customer',
            'customer_email' => 'pat@example.com',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['status', 'assignment_id']);

        $assignment = Assignment::find($response->json('assignment_id'));
        $this->assertSame(Assignment::TYPE_VIDEO_CONSULTING, $assignment->assignment_type);
        $this->assertSame(Assignment::STATUS_UNASSIGNED, $assignment->status);
        $this->assertSame($reader->id, $assignment->requested_reader_id);
        $this->assertSame('pat@example.com', $assignment->video_consulting_customer_email);
        $this->assertEquals(50, $assignment->pay_rate);
        $this->assertSame('#5001', $assignment->order_number);

        $hold->refresh();
        $this->assertSame(VideoConsultingHold::STATUS_CONVERTED, $hold->status);
        $this->assertSame($assignment->id, $hold->converted_assignment_id);

        Mail::assertQueued(VideoConsultingBookingConfirmedMail::class, fn ($m) => $m->hasTo('pat@example.com'));
        Mail::assertQueued(NewAssignmentMail::class);
    }

    public function test_create_booking_syncs_a_default_tier_so_the_reader_can_accept(): void
    {
        Mail::fake();
        $tier = Tier::where('is_onboarding', false)->orderBy('position')->first();
        [$reader, $duration] = $this->makeBookableReader();
        $hold = $this->makeHold($reader, $duration, now()->addDay());

        $response = $this->withHeaders($this->authHeaders())->postJson('/api/video-consulting/bookings', [
            'hold_token' => $hold->hold_token,
            'order_number' => '#5002',
            'customer_email' => 'pat@example.com',
        ]);

        $assignment = Assignment::find($response->json('assignment_id'));
        $this->assertTrue($assignment->tiers->contains($tier->id));
    }

    public function test_create_booking_is_idempotent_for_an_already_converted_hold(): void
    {
        Mail::fake();
        [$reader, $duration] = $this->makeBookableReader();
        $hold = $this->makeHold($reader, $duration, now()->addDay());

        $first = $this->withHeaders($this->authHeaders())->postJson('/api/video-consulting/bookings', [
            'hold_token' => $hold->hold_token,
            'order_number' => '#5003',
            'customer_email' => 'pat@example.com',
        ]);
        $first->assertStatus(201);

        $second = $this->withHeaders($this->authHeaders())->postJson('/api/video-consulting/bookings', [
            'hold_token' => $hold->hold_token,
            'order_number' => '#5003',
            'customer_email' => 'pat@example.com',
        ]);

        $second->assertStatus(200);
        $second->assertJson(['status' => 'already_exists', 'assignment_id' => $first->json('assignment_id')]);
        $this->assertSame(1, Assignment::where('video_consulting_hold_id', $hold->id)->count());
    }

    public function test_create_booking_rejects_an_expired_hold(): void
    {
        [$reader, $duration] = $this->makeBookableReader();
        $hold = $this->makeHold($reader, $duration, now()->addDay());
        $hold->update(['expires_at' => now()->subMinute()]);

        $response = $this->withHeaders($this->authHeaders())->postJson('/api/video-consulting/bookings', [
            'hold_token' => $hold->hold_token,
            'order_number' => '#5004',
            'customer_email' => 'pat@example.com',
        ]);

        $response->assertStatus(409);
    }

    public function test_create_booking_rejects_an_unknown_hold_token(): void
    {
        $response = $this->withHeaders($this->authHeaders())->postJson('/api/video-consulting/bookings', [
            'hold_token' => 'does-not-exist',
            'order_number' => '#5005',
            'customer_email' => 'pat@example.com',
        ]);

        $response->assertStatus(404);
    }

    public function test_create_booking_rejects_a_hold_whose_reader_rate_was_removed(): void
    {
        [$reader, $duration, $rate] = $this->makeBookableReader();
        $hold = $this->makeHold($reader, $duration, now()->addDay());
        $rate->delete();

        $response = $this->withHeaders($this->authHeaders())->postJson('/api/video-consulting/bookings', [
            'hold_token' => $hold->hold_token,
            'order_number' => '#5006',
            'customer_email' => 'pat@example.com',
        ]);

        $response->assertStatus(422);
    }
}
