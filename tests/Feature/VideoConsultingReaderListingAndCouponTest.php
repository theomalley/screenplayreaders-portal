<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\ReaderAvailabilityRule;
use App\Models\ReaderVideoConsultingRate;
use App\Models\Tier;
use App\Models\User;
use App\Models\VideoConsultingDuration;
use App\Models\VideoConsultingHold;
use App\Support\VideoConsultingAccess;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Reader-authored video-consulting listing note + call mode (ProfileController::
 * updateVideoConsultingListing()), the reader's own single reusable discount code
 * (updateVideoConsultingCoupon()), and redemption of that code — see
 * App\Support\VideoConsultingAccess for the floor-at-0/never-touches-base_rate math this
 * all leans on.
 */
class VideoConsultingReaderListingAndCouponTest extends TestCase
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

        return ['Authorization' => 'Bearer test-secret', 'Accept' => 'application/json'];
    }

    private function makeBookableReader(array $profileOverrides = []): array
    {
        $reader = User::factory()->create(['role' => 'reader', 'name' => 'Jamie Reader']);
        $reader->readerProfile()->create(array_merge([
            'initials' => 'JR', 'first_name' => 'Jamie', 'last_name' => 'Reader',
        ], $profileOverrides));
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

    // ---- Self-serve note + call mode -----------------------------------------------------

    public function test_reader_can_set_and_clear_note_and_call_mode(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $reader->readerProfile()->create(['initials' => 'TR', 'first_name' => 'Test', 'last_name' => 'Reader']);

        $this->actingAs($reader)
            ->patch('/profile/video-consulting-listing', [
                'video_consulting_note' => 'Back Monday!',
                'video_consulting_call_mode' => 'audio',
            ])
            ->assertRedirect(route('profile.edit'));

        $profile = $reader->fresh('readerProfile')->readerProfile;
        $this->assertSame('Back Monday!', $profile->video_consulting_note);
        $this->assertSame('audio', $profile->video_consulting_call_mode);

        $this->actingAs($reader)
            ->patch('/profile/video-consulting-listing', [
                'video_consulting_note' => '   ',
                'video_consulting_call_mode' => 'video',
            ])
            ->assertRedirect(route('profile.edit'));

        $profile = $reader->fresh('readerProfile')->readerProfile;
        $this->assertNull($profile->video_consulting_note);
        $this->assertSame('video', $profile->video_consulting_call_mode);
    }

    public function test_admin_can_set_note_and_call_mode_for_themselves(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->patch('/profile/video-consulting-listing', [
                'video_consulting_note' => 'On vacation, back the 1st',
                'video_consulting_call_mode' => 'audio',
            ])
            ->assertRedirect(route('profile.edit'));

        $profile = $admin->fresh('readerProfile')->readerProfile;
        $this->assertSame('On vacation, back the 1st', $profile->video_consulting_note);
        $this->assertSame('audio', $profile->video_consulting_call_mode);
    }

    public function test_call_mode_is_required_and_must_be_video_or_audio(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $reader->readerProfile()->create(['initials' => 'TR', 'first_name' => 'Test', 'last_name' => 'Reader']);

        $this->actingAs($reader)
            ->patch('/profile/video-consulting-listing', ['video_consulting_call_mode' => 'surround-sound'])
            ->assertSessionHasErrors('video_consulting_call_mode');
    }

    // ---- Self-serve coupon code ----------------------------------------------------------

    public function test_reader_can_set_and_clear_their_coupon_code(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $reader->readerProfile()->create(['initials' => 'TR', 'first_name' => 'Test', 'last_name' => 'Reader']);

        $this->actingAs($reader)
            ->patch('/profile/video-consulting-coupon', [
                'coupon_code' => 'save20',
                'coupon_type' => 'fixed',
                'coupon_amount' => '20',
            ])
            ->assertRedirect(route('profile.edit'));

        $profile = $reader->fresh('readerProfile')->readerProfile;
        $this->assertSame('SAVE20', $profile->video_consulting_coupon_code);
        $this->assertSame('fixed', $profile->video_consulting_coupon_type);
        $this->assertEquals(20, $profile->video_consulting_coupon_amount);

        $this->actingAs($reader)
            ->patch('/profile/video-consulting-coupon', ['coupon_code' => ''])
            ->assertRedirect(route('profile.edit'));

        $profile = $reader->fresh('readerProfile')->readerProfile;
        $this->assertNull($profile->video_consulting_coupon_code);
        $this->assertNull($profile->video_consulting_coupon_type);
        $this->assertNull($profile->video_consulting_coupon_amount);
    }

    public function test_coupon_code_rejects_a_percent_amount_over_100(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $reader->readerProfile()->create(['initials' => 'TR', 'first_name' => 'Test', 'last_name' => 'Reader']);

        $this->actingAs($reader)
            ->patch('/profile/video-consulting-coupon', [
                'coupon_code' => 'HUGE',
                'coupon_type' => 'percent',
                'coupon_amount' => '500',
            ])
            ->assertSessionHasErrors('coupon_amount');

        $this->assertNull($reader->fresh('readerProfile')->readerProfile->video_consulting_coupon_code);
    }

    public function test_coupon_code_rejects_a_negative_amount(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $reader->readerProfile()->create(['initials' => 'TR', 'first_name' => 'Test', 'last_name' => 'Reader']);

        $this->actingAs($reader)
            ->patch('/profile/video-consulting-coupon', [
                'coupon_code' => 'NEG',
                'coupon_type' => 'fixed',
                'coupon_amount' => '-5',
            ])
            ->assertSessionHasErrors('coupon_amount');
    }

    public function test_coupon_code_cannot_collide_with_another_readers_code(): void
    {
        $readerA = User::factory()->create(['role' => 'reader']);
        $readerA->readerProfile()->create([
            'initials' => 'RA', 'first_name' => 'Reader', 'last_name' => 'A',
            'video_consulting_coupon_code' => 'TAKEN', 'video_consulting_coupon_type' => 'fixed',
            'video_consulting_coupon_amount' => 10,
        ]);
        $readerB = User::factory()->create(['role' => 'reader']);
        $readerB->readerProfile()->create(['initials' => 'RB', 'first_name' => 'Reader', 'last_name' => 'B']);

        $this->actingAs($readerB)
            ->patch('/profile/video-consulting-coupon', [
                'coupon_code' => 'taken',
                'coupon_type' => 'fixed',
                'coupon_amount' => '5',
            ])
            ->assertSessionHasErrors('coupon_code');

        $this->assertNull($readerB->fresh('readerProfile')->readerProfile->video_consulting_coupon_code);
    }

    public function test_a_reader_can_keep_their_own_existing_code_when_resaving(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $reader->readerProfile()->create([
            'initials' => 'TR', 'first_name' => 'Test', 'last_name' => 'Reader',
            'video_consulting_coupon_code' => 'MINE', 'video_consulting_coupon_type' => 'fixed',
            'video_consulting_coupon_amount' => 10,
        ]);

        $this->actingAs($reader)
            ->patch('/profile/video-consulting-coupon', [
                'coupon_code' => 'mine',
                'coupon_type' => 'percent',
                'coupon_amount' => '15',
            ])
            ->assertRedirect(route('profile.edit'));

        $profile = $reader->fresh('readerProfile')->readerProfile;
        $this->assertSame('MINE', $profile->video_consulting_coupon_code);
        $this->assertSame('percent', $profile->video_consulting_coupon_type);
        $this->assertEquals(15, $profile->video_consulting_coupon_amount);
    }

    // ---- Redemption math — the actual safety guarantee -----------------------------------

    public function test_applying_the_discount_never_drops_below_zero_reader_profit_even_with_absurd_fixed_amounts(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $reader->readerProfile()->create([
            'initials' => 'TR', 'first_name' => 'Test', 'last_name' => 'Reader',
            'video_consulting_coupon_code' => 'HUGE', 'video_consulting_coupon_type' => 'fixed',
            'video_consulting_coupon_amount' => 999999,
        ]);

        $discounted = VideoConsultingAccess::applyReaderDiscount($reader->fresh('readerProfile'), 50.0);

        $this->assertSame(0.0, $discounted);
    }

    public function test_applying_the_discount_never_drops_below_zero_reader_profit_even_with_a_clamped_percent(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $reader->readerProfile()->create([
            'initials' => 'TR', 'first_name' => 'Test', 'last_name' => 'Reader',
            // Validation at save time already blocks >100, but redemption must be safe
            // on its own regardless — a stored value could exist from before that rule,
            // or from direct DB manipulation.
            'video_consulting_coupon_code' => 'OVER', 'video_consulting_coupon_type' => 'percent',
            'video_consulting_coupon_amount' => 500,
        ]);

        $discounted = VideoConsultingAccess::applyReaderDiscount($reader->fresh('readerProfile'), 50.0);

        $this->assertSame(0.0, $discounted);
    }

    public function test_a_normal_fixed_discount_only_reduces_the_readers_own_cut(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $reader->readerProfile()->create([
            'initials' => 'TR', 'first_name' => 'Test', 'last_name' => 'Reader',
            'video_consulting_coupon_code' => 'SAVE20', 'video_consulting_coupon_type' => 'fixed',
            'video_consulting_coupon_amount' => 20,
        ]);

        $discounted = VideoConsultingAccess::applyReaderDiscount($reader->fresh('readerProfile'), 50.0);

        $this->assertSame(30.0, $discounted); // base_rate is never passed in at all — nothing here can touch it
    }

    public function test_a_code_only_matches_its_own_reader(): void
    {
        $readerA = User::factory()->create(['role' => 'reader']);
        $readerA->readerProfile()->create([
            'initials' => 'RA', 'first_name' => 'Reader', 'last_name' => 'A',
            'video_consulting_coupon_code' => 'AAA10', 'video_consulting_coupon_type' => 'fixed',
            'video_consulting_coupon_amount' => 10,
        ]);
        $readerB = User::factory()->create(['role' => 'reader']);
        $readerB->readerProfile()->create(['initials' => 'RB', 'first_name' => 'Reader', 'last_name' => 'B']);

        $this->assertTrue(VideoConsultingAccess::readerCouponMatches($readerA->fresh('readerProfile'), 'aaa10'));
        $this->assertFalse(VideoConsultingAccess::readerCouponMatches($readerB->fresh('readerProfile'), 'AAA10'));
    }

    // ---- readers() API ---------------------------------------------------------------------

    public function test_readers_endpoint_includes_note_and_call_mode_for_an_enabled_reader_with_no_open_slots(): void
    {
        $reader = User::factory()->create(['role' => 'reader', 'name' => 'Jamie Reader']);
        $reader->readerProfile()->create([
            'initials' => 'JR', 'first_name' => 'Jamie', 'last_name' => 'Reader',
            'video_consulting_note' => 'Back Monday!', 'video_consulting_call_mode' => 'audio',
        ]);
        $duration = VideoConsultingDuration::create([
            'minutes' => 30, 'label' => '30 Minutes', 'base_rate' => 20, 'min_reader_rate' => 10, 'max_reader_rate' => 100,
        ]);
        ReaderVideoConsultingRate::create(['reader_id' => $reader->id, 'duration_id' => $duration->id, 'rate' => 50]);
        // Deliberately no ReaderAvailabilityRule — no open slots right now, but still enabled
        // and still has a configured rate, so they must still appear (unchanged behavior).

        $response = $this->withHeaders($this->authHeaders())->getJson('/api/video-consulting/readers');

        $response->assertOk();
        $payload = $response->json();
        $this->assertCount(1, $payload);
        $this->assertSame('Back Monday!', $payload[0]['note']);
        $this->assertSame('audio', $payload[0]['call_mode']);
    }

    public function test_readers_endpoint_excludes_a_fully_disabled_reader_even_with_a_note_set(): void
    {
        [$reader] = $this->makeBookableReader([
            'video_consulting_enabled' => false,
            'video_consulting_note' => 'Back Monday!',
        ]);

        $response = $this->withHeaders($this->authHeaders())->getJson('/api/video-consulting/readers');

        $response->assertOk();
        $this->assertCount(0, $response->json());
    }

    public function test_readers_endpoint_applies_a_matching_coupon_code_to_that_readers_prices_only(): void
    {
        [$readerA, $durationA] = $this->makeBookableReader([
            'video_consulting_coupon_code' => 'SAVE20', 'video_consulting_coupon_type' => 'fixed',
            'video_consulting_coupon_amount' => 20,
        ]);
        $readerB = User::factory()->create(['role' => 'reader', 'name' => 'Other Reader']);
        $readerB->readerProfile()->create(['initials' => 'OR', 'first_name' => 'Other', 'last_name' => 'Reader']);
        $rateB = ReaderVideoConsultingRate::create(['reader_id' => $readerB->id, 'duration_id' => $durationA->id, 'rate' => 50]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/video-consulting/readers?coupon_code=save20');

        $response->assertOk();
        $byId = collect($response->json())->keyBy('id');

        $this->assertTrue($byId[$readerA->id]['coupon_applied']);
        $this->assertSame('$20.00 off', $byId[$readerA->id]['coupon_label']);
        $this->assertEquals(50.0, $byId[$readerA->id]['durations'][0]['price']); // base 20 + (reader_rate 50 - 20)

        $this->assertFalse($byId[$readerB->id]['coupon_applied']);
        $this->assertEquals(70.0, $byId[$readerB->id]['durations'][0]['price']); // untouched: base 20 + reader_rate 50
    }

    public function test_readers_endpoint_ignores_a_coupon_code_that_matches_nobody(): void
    {
        [$reader] = $this->makeBookableReader();

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/video-consulting/readers?coupon_code=NOPE');

        $response->assertOk();
        $payload = $response->json();
        $this->assertFalse($payload[0]['coupon_applied']);
        $this->assertEquals(70.0, $payload[0]['durations'][0]['price']);
    }

    // ---- createBooking() applies + records the discount -----------------------------------

    public function test_create_booking_applies_a_matching_coupon_to_pay_rate_and_records_it(): void
    {
        Mail::fake();
        $tier = Tier::where('is_onboarding', false)->orderBy('position')->first();
        [$reader, $duration] = $this->makeBookableReader([
            'video_consulting_coupon_code' => 'SAVE20', 'video_consulting_coupon_type' => 'fixed',
            'video_consulting_coupon_amount' => 20,
        ]);
        $reader->readerProfile->tiers()->sync([$tier->id]);
        $hold = $this->makeHold($reader, $duration, now()->addDay());

        $response = $this->withHeaders($this->authHeaders())->post('/api/video-consulting/bookings', [
            'hold_token' => $hold->hold_token,
            'order_number' => '#5002',
            'customer_email' => 'pat@example.com',
            'script_title' => 'My Great Screenplay',
            'coupon_code' => 'save20',
            'script' => UploadedFile::fake()->create('script.pdf', 100, 'application/pdf'),
        ]);

        $response->assertStatus(201);
        $assignment = Assignment::find($response->json('assignment_id'));

        $this->assertEquals(30, $assignment->pay_rate); // 50 - 20
        $this->assertSame('SAVE20', $assignment->video_consulting_coupon_code_used);
        $this->assertEquals(20, $assignment->video_consulting_discount_amount);
    }

    public function test_create_booking_ignores_a_coupon_code_that_does_not_match_the_reader(): void
    {
        Mail::fake();
        $tier = Tier::where('is_onboarding', false)->orderBy('position')->first();
        [$reader, $duration] = $this->makeBookableReader([
            'video_consulting_coupon_code' => 'SAVE20', 'video_consulting_coupon_type' => 'fixed',
            'video_consulting_coupon_amount' => 20,
        ]);
        $reader->readerProfile->tiers()->sync([$tier->id]);
        $hold = $this->makeHold($reader, $duration, now()->addDay());

        $response = $this->withHeaders($this->authHeaders())->post('/api/video-consulting/bookings', [
            'hold_token' => $hold->hold_token,
            'order_number' => '#5003',
            'customer_email' => 'pat@example.com',
            'script_title' => 'My Great Screenplay',
            'coupon_code' => 'WRONGCODE',
            'script' => UploadedFile::fake()->create('script.pdf', 100, 'application/pdf'),
        ]);

        $response->assertStatus(201);
        $assignment = Assignment::find($response->json('assignment_id'));

        $this->assertEquals(50, $assignment->pay_rate); // full rate, no discount applied
        $this->assertNull($assignment->video_consulting_coupon_code_used);
        $this->assertNull($assignment->video_consulting_discount_amount);
    }
}
