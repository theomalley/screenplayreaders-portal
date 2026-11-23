<?php

namespace Tests\Feature;

use App\Jobs\UploadScriptToDrive;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VideoConsultingBookingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Queue::fake();
    }

    private function authHeaders(): array
    {
        config(['services.portal.webhook_secret' => 'test-secret']);

        // Needed for plain post() (file-upload tests can't use postJson()) to get a JSON
        // validation-error response instead of a redirect-back.
        return ['Authorization' => 'Bearer test-secret', 'Accept' => 'application/json'];
    }

    /** Required booking fields a real customer-confirmed booking always carries now. */
    private function bookingExtras(array $overrides = []): array
    {
        return array_merge([
            'script_title' => 'My Great Screenplay',
            'script'       => UploadedFile::fake()->create('script.pdf', 100, 'application/pdf'),
        ], $overrides);
    }

    private function makeBookableReader(bool $notifiable = false): array
    {
        $reader = User::factory()->create(['role' => 'reader', 'name' => 'Jamie Reader']);
        $reader->readerProfile()->create([
            'initials' => 'JR', 'first_name' => 'Jamie', 'last_name' => 'Reader',
            'email_notifications' => $notifiable, 'email_notify_requests' => $notifiable,
            'email_notify_video_consulting' => $notifiable,
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
        $response->assertJsonFragment(['id' => $reader->id, 'name' => 'Jamie Reader', 'initials' => 'JR']);
        $payload = $response->json();
        $this->assertStringContainsString('9:00 AM', $payload[0]['availability_summary']);
        $this->assertSame($duration->id, $payload[0]['durations'][0]['duration_id']);
        $this->assertEquals(70.0, $payload[0]['durations'][0]['price']); // base_rate 20 + reader_rate 50
        $this->assertSame(0, $payload[0]['min_lead_hours']); // default from makeBookableReader()
    }

    public function test_readers_endpoint_adds_base_rate_percent_on_top_of_the_flat_fee_at_a_high_rate(): void
    {
        $reader = User::factory()->create(['role' => 'reader', 'name' => 'Jamie Reader']);
        $reader->readerProfile()->create(['initials' => 'JR', 'first_name' => 'Jamie', 'last_name' => 'Reader']);
        $duration = VideoConsultingDuration::create([
            'minutes' => 60, 'label' => '60 Minutes', 'base_rate' => 100, 'base_rate_percent' => 15,
            'min_reader_rate' => 149, 'max_reader_rate' => 5000,
        ]);
        ReaderVideoConsultingRate::create(['reader_id' => $reader->id, 'duration_id' => $duration->id, 'rate' => 5000]);

        $response = $this->withHeaders($this->authHeaders())->getJson('/api/video-consulting/readers');

        $response->assertOk();
        // base_rate + base_rate_percent both apply: $100 flat + 15% of 5000 ($750) = $850 fee, + 5000 reader rate.
        $this->assertEquals(5850.0, $response->json('0.durations.0.price'));
    }

    public function test_readers_endpoint_adds_base_rate_percent_on_top_of_the_flat_fee_at_a_low_rate(): void
    {
        $reader = User::factory()->create(['role' => 'reader', 'name' => 'Jamie Reader']);
        $reader->readerProfile()->create(['initials' => 'JR', 'first_name' => 'Jamie', 'last_name' => 'Reader']);
        $duration = VideoConsultingDuration::create([
            'minutes' => 60, 'label' => '60 Minutes', 'base_rate' => 100, 'base_rate_percent' => 15,
            'min_reader_rate' => 149, 'max_reader_rate' => 5000,
        ]);
        ReaderVideoConsultingRate::create(['reader_id' => $reader->id, 'duration_id' => $duration->id, 'rate' => 149]);

        $response = $this->withHeaders($this->authHeaders())->getJson('/api/video-consulting/readers');

        $response->assertOk();
        // $100 flat + 15% of 149 (22.35, rounds to 22) = $122 fee, + 149 reader rate.
        $this->assertEquals(271.0, $response->json('0.durations.0.price'));
    }

    public function test_readers_endpoint_includes_the_readers_min_lead_hours(): void
    {
        [$reader] = $this->makeBookableReader();
        $reader->readerProfile->update(['video_consulting_min_lead_hours' => 48]);

        $response = $this->withHeaders($this->authHeaders())->getJson('/api/video-consulting/readers');

        $response->assertOk();
        $this->assertSame(48, $response->json('0.min_lead_hours'));
    }

    public function test_readers_endpoint_includes_photo_url_when_the_reader_has_a_photo(): void
    {
        [$reader] = $this->makeBookableReader();
        $reader->readerProfile->update(['photo' => 'reader-photos/jamie.jpg']);

        $response = $this->withHeaders($this->authHeaders())->getJson('/api/video-consulting/readers');

        $response->assertOk();
        $this->assertStringContainsString('reader-photos/jamie.jpg', $response->json('0.photo_url'));
    }

    public function test_readers_endpoint_photo_url_is_null_when_the_reader_has_no_photo(): void
    {
        $this->makeBookableReader();

        $response = $this->withHeaders($this->authHeaders())->getJson('/api/video-consulting/readers');

        $response->assertOk();
        $this->assertNull($response->json('0.photo_url'));
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

    public function test_readers_endpoint_includes_an_editor_who_has_opted_in(): void
    {
        $editor = User::factory()->create(['role' => 'editor', 'name' => 'Jordan Editor']);
        $editor->readerProfile()->create([
            'initials' => 'JE', 'first_name' => 'Jordan', 'last_name' => 'Editor',
            'video_consulting_enabled' => true,
        ]);
        $duration = VideoConsultingDuration::create([
            'minutes' => 30, 'label' => '30 Minutes', 'base_rate' => 20, 'min_reader_rate' => 10, 'max_reader_rate' => 100,
        ]);
        ReaderVideoConsultingRate::create(['reader_id' => $editor->id, 'duration_id' => $duration->id, 'rate' => 50]);

        $response = $this->withHeaders($this->authHeaders())->getJson('/api/video-consulting/readers');

        $response->assertOk();
        $response->assertJsonFragment(['id' => $editor->id, 'name' => 'Jordan Editor']);
    }

    public function test_readers_endpoint_excludes_an_editor_who_has_not_opted_in(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $editor->readerProfile()->create(['initials' => 'JE', 'first_name' => 'Jordan', 'last_name' => 'Editor']);
        $duration = VideoConsultingDuration::create([
            'minutes' => 30, 'label' => '30 Minutes', 'base_rate' => 20, 'min_reader_rate' => 10, 'max_reader_rate' => 100,
        ]);
        ReaderVideoConsultingRate::create(['reader_id' => $editor->id, 'duration_id' => $duration->id, 'rate' => 50]);

        $response = $this->withHeaders($this->authHeaders())->getJson('/api/video-consulting/readers');

        $response->assertOk();
        $this->assertCount(0, $response->json());
    }

    public function test_readers_endpoint_marks_a_reader_as_reader_request_eligible(): void
    {
        [$reader] = $this->makeBookableReader();

        $response = $this->withHeaders($this->authHeaders())->getJson('/api/video-consulting/readers');

        $response->assertOk();
        $response->assertJsonFragment(['id' => $reader->id, 'reader_request_eligible' => true]);
    }

    public function test_readers_endpoint_marks_an_opted_out_admin_as_not_reader_request_eligible(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin Reader']);
        $admin->readerProfile()->create([
            'initials' => 'AR', 'first_name' => 'Admin', 'last_name' => 'Reader',
            'video_consulting_enabled' => true,
            // Eligible by default now — explicit false here is what "opted out" means.
            'video_consulting_reader_request_eligible' => false,
        ]);
        $duration = VideoConsultingDuration::create([
            'minutes' => 30, 'label' => '30 Minutes', 'base_rate' => 20, 'min_reader_rate' => 10, 'max_reader_rate' => 100,
        ]);
        ReaderVideoConsultingRate::create(['reader_id' => $admin->id, 'duration_id' => $duration->id, 'rate' => 50]);

        $response = $this->withHeaders($this->authHeaders())->getJson('/api/video-consulting/readers');

        $response->assertOk();
        $response->assertJsonFragment(['id' => $admin->id, 'reader_request_eligible' => false]);
    }

    public function test_readers_endpoint_marks_an_opted_in_admin_as_reader_request_eligible(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin Reader']);
        $admin->readerProfile()->create([
            'initials' => 'AR', 'first_name' => 'Admin', 'last_name' => 'Reader',
            'video_consulting_enabled' => true,
            'video_consulting_reader_request_eligible' => true,
        ]);
        $duration = VideoConsultingDuration::create([
            'minutes' => 30, 'label' => '30 Minutes', 'base_rate' => 20, 'min_reader_rate' => 10, 'max_reader_rate' => 100,
        ]);
        ReaderVideoConsultingRate::create(['reader_id' => $admin->id, 'duration_id' => $duration->id, 'rate' => 50]);

        $response = $this->withHeaders($this->authHeaders())->getJson('/api/video-consulting/readers');

        $response->assertOk();
        $response->assertJsonFragment(['id' => $admin->id, 'reader_request_eligible' => true]);
    }

    public function test_readers_endpoint_marks_an_opted_out_editor_as_not_reader_request_eligible(): void
    {
        // Editors don't automatically write coverage either (only a plain reader does).
        $editor = User::factory()->create(['role' => 'editor', 'name' => 'Editor Reader']);
        $editor->readerProfile()->create([
            'initials' => 'ER', 'first_name' => 'Editor', 'last_name' => 'Reader',
            'video_consulting_enabled' => true,
            // Eligible by default now — explicit false here is what "opted out" means.
            'video_consulting_reader_request_eligible' => false,
        ]);
        $duration = VideoConsultingDuration::create([
            'minutes' => 30, 'label' => '30 Minutes', 'base_rate' => 20, 'min_reader_rate' => 10, 'max_reader_rate' => 100,
        ]);
        ReaderVideoConsultingRate::create(['reader_id' => $editor->id, 'duration_id' => $duration->id, 'rate' => 50]);

        $response = $this->withHeaders($this->authHeaders())->getJson('/api/video-consulting/readers');

        $response->assertOk();
        $response->assertJsonFragment(['id' => $editor->id, 'reader_request_eligible' => false]);
    }

    public function test_readers_endpoint_marks_an_opted_in_editor_as_reader_request_eligible(): void
    {
        $editor = User::factory()->create(['role' => 'editor', 'name' => 'Editor Reader']);
        $editor->readerProfile()->create([
            'initials' => 'ER', 'first_name' => 'Editor', 'last_name' => 'Reader',
            'video_consulting_enabled' => true,
            'video_consulting_reader_request_eligible' => true,
        ]);
        $duration = VideoConsultingDuration::create([
            'minutes' => 30, 'label' => '30 Minutes', 'base_rate' => 20, 'min_reader_rate' => 10, 'max_reader_rate' => 100,
        ]);
        ReaderVideoConsultingRate::create(['reader_id' => $editor->id, 'duration_id' => $duration->id, 'rate' => 50]);

        $response = $this->withHeaders($this->authHeaders())->getJson('/api/video-consulting/readers');

        $response->assertOk();
        $response->assertJsonFragment(['id' => $editor->id, 'reader_request_eligible' => true]);
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

        $response = $this->withHeaders($this->authHeaders())->post('/api/video-consulting/bookings', array_merge([
            'hold_token' => $hold->hold_token,
            'order_number' => '#5001',
            'customer_name' => 'Pat Customer',
            'customer_email' => 'pat@example.com',
        ], $this->bookingExtras(['script_title' => 'My Great Screenplay'])));

        $response->assertStatus(201);
        $response->assertJsonStructure(['status', 'assignment_id']);

        $assignment = Assignment::find($response->json('assignment_id'));
        $this->assertSame(Assignment::TYPE_VIDEO_CONSULTING, $assignment->assignment_type);
        $this->assertSame(Assignment::STATUS_UNASSIGNED, $assignment->status);
        $this->assertSame($reader->id, $assignment->requested_reader_id);
        $this->assertSame('pat@example.com', $assignment->video_consulting_customer_email);
        $this->assertEquals(50, $assignment->pay_rate);
        $this->assertSame('#5001', $assignment->order_number);
        $this->assertSame('My Great Screenplay', $assignment->script_title);

        $hold->refresh();
        $this->assertSame(VideoConsultingHold::STATUS_CONVERTED, $hold->status);
        $this->assertSame($assignment->id, $hold->converted_assignment_id);

        Mail::assertQueued(VideoConsultingBookingConfirmedMail::class, fn ($m) => $m->hasTo('pat@example.com'));
        Mail::assertQueued(NewAssignmentMail::class);
        Queue::assertPushed(UploadScriptToDrive::class, fn ($job) => $job->assignmentId === $assignment->id);
    }

    public function test_create_booking_rejects_a_request_missing_the_script_file(): void
    {
        [$reader, $duration] = $this->makeBookableReader();
        $hold = $this->makeHold($reader, $duration, now()->addDay());

        $response = $this->withHeaders($this->authHeaders())->post('/api/video-consulting/bookings', [
            'hold_token' => $hold->hold_token,
            'order_number' => '#5001',
            'customer_email' => 'pat@example.com',
            'script_title' => 'My Great Screenplay',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('script');
    }

    public function test_create_booking_rejects_a_request_missing_the_script_title(): void
    {
        [$reader, $duration] = $this->makeBookableReader();
        $hold = $this->makeHold($reader, $duration, now()->addDay());

        $response = $this->withHeaders($this->authHeaders())->post('/api/video-consulting/bookings', [
            'hold_token' => $hold->hold_token,
            'order_number' => '#5001',
            'customer_email' => 'pat@example.com',
            'script' => UploadedFile::fake()->create('script.pdf', 100, 'application/pdf'),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('script_title');
    }

    public function test_create_booking_syncs_a_default_tier_so_the_reader_can_accept(): void
    {
        Mail::fake();
        $tier = Tier::where('is_onboarding', false)->orderBy('position')->first();
        [$reader, $duration] = $this->makeBookableReader();
        $hold = $this->makeHold($reader, $duration, now()->addDay());

        $response = $this->withHeaders($this->authHeaders())->post('/api/video-consulting/bookings', array_merge([
            'hold_token' => $hold->hold_token,
            'order_number' => '#5002',
            'customer_email' => 'pat@example.com',
        ], $this->bookingExtras()));

        $assignment = Assignment::find($response->json('assignment_id'));
        $this->assertTrue($assignment->tiers->contains($tier->id));
    }

    public function test_create_booking_is_idempotent_for_an_already_converted_hold(): void
    {
        Mail::fake();
        [$reader, $duration] = $this->makeBookableReader();
        $hold = $this->makeHold($reader, $duration, now()->addDay());

        $first = $this->withHeaders($this->authHeaders())->post('/api/video-consulting/bookings', array_merge([
            'hold_token' => $hold->hold_token,
            'order_number' => '#5003',
            'customer_email' => 'pat@example.com',
        ], $this->bookingExtras()));
        $first->assertStatus(201);

        $second = $this->withHeaders($this->authHeaders())->post('/api/video-consulting/bookings', array_merge([
            'hold_token' => $hold->hold_token,
            'order_number' => '#5003',
            'customer_email' => 'pat@example.com',
        ], $this->bookingExtras()));

        $second->assertStatus(200);
        $second->assertJson(['status' => 'already_exists', 'assignment_id' => $first->json('assignment_id')]);
        $this->assertSame(1, Assignment::where('video_consulting_hold_id', $hold->id)->count());
    }

    public function test_create_booking_rejects_an_expired_hold(): void
    {
        [$reader, $duration] = $this->makeBookableReader();
        $hold = $this->makeHold($reader, $duration, now()->addDay());
        $hold->update(['expires_at' => now()->subMinute()]);

        $response = $this->withHeaders($this->authHeaders())->post('/api/video-consulting/bookings', array_merge([
            'hold_token' => $hold->hold_token,
            'order_number' => '#5004',
            'customer_email' => 'pat@example.com',
        ], $this->bookingExtras()));

        $response->assertStatus(409);
    }

    public function test_create_booking_rejects_an_unknown_hold_token(): void
    {
        $response = $this->withHeaders($this->authHeaders())->post('/api/video-consulting/bookings', array_merge([
            'hold_token' => 'does-not-exist',
            'order_number' => '#5005',
            'customer_email' => 'pat@example.com',
        ], $this->bookingExtras()));

        $response->assertStatus(404);
    }

    public function test_create_booking_rejects_a_hold_whose_reader_rate_was_removed(): void
    {
        [$reader, $duration, $rate] = $this->makeBookableReader();
        $hold = $this->makeHold($reader, $duration, now()->addDay());
        $rate->delete();

        $response = $this->withHeaders($this->authHeaders())->post('/api/video-consulting/bookings', array_merge([
            'hold_token' => $hold->hold_token,
            'order_number' => '#5006',
            'customer_email' => 'pat@example.com',
        ], $this->bookingExtras()));

        $response->assertStatus(422);
    }
}
