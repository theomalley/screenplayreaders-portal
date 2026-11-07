<?php

namespace Tests\Feature;

use App\Console\Commands\EscalateExpiredVideoConsultingRescheduleOffers;
use App\Mail\VideoConsultingAdminAlertMail;
use App\Mail\VideoConsultingRescheduleConfirmedMail;
use App\Mail\VideoConsultingRescheduleOfferMail;
use App\Models\Assignment;
use App\Models\User;
use App\Models\VideoConsultingDuration;
use App\Models\VideoConsultingHold;
use App\Models\VideoConsultingRescheduleOffer;
use App\Services\VideoConsultingCallService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery\MockInterface;
use Tests\TestCase;

class VideoConsultingRescheduleTest extends TestCase
{
    use RefreshDatabase;

    private function makeAcceptedBooking(?string $customerEmail = 'customer@example.com'): array
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $reader->readerProfile()->create(['initials' => 'TR', 'first_name' => 'Test', 'last_name' => 'Reader']);

        $duration = VideoConsultingDuration::create([
            'minutes' => 30, 'label' => '30 Minutes', 'base_rate' => 20, 'min_reader_rate' => 10, 'max_reader_rate' => 100,
        ]);

        $slotStart = now()->addDay();

        $hold = VideoConsultingHold::create([
            'reader_id' => $reader->id, 'duration_id' => $duration->id,
            'slot_start' => $slotStart, 'slot_end' => $slotStart->copy()->addMinutes(30),
            'hold_token' => 'tok-' . uniqid(), 'status' => VideoConsultingHold::STATUS_CONVERTED, 'expires_at' => now(),
        ]);

        $assignment = Assignment::create([
            'order_number' => 'TEST-VC-' . random_int(100000, 999999), 'vendor' => 'sr',
            'assignment_type' => Assignment::TYPE_VIDEO_CONSULTING, 'script_title' => 'x', 'writer_name' => 'x',
            'pay_rate' => 50, 'status' => Assignment::STATUS_ASSIGNED, 'is_test' => true,
            'assigned_reader_id' => $reader->id, 'video_consulting_hold_id' => $hold->id,
            'video_consulting_customer_email' => $customerEmail,
        ]);

        return [$reader, $assignment, $hold];
    }

    public function test_reader_can_propose_five_slots(): void
    {
        Mail::fake();
        [$reader, $assignment] = $this->makeAcceptedBooking();

        $slots = collect(range(1, 5))->map(fn ($i) => now()->addDays($i)->format('Y-m-d\TH:i'))->all();

        $this->actingAs($reader)
            ->post("/video-consulting-bookings/{$assignment->id}/reschedule", ['slots' => $slots])
            ->assertRedirect();

        $offer = VideoConsultingRescheduleOffer::where('assignment_id', $assignment->id)->first();
        $this->assertNotNull($offer);
        $this->assertCount(5, $offer->candidate_slots);
        $this->assertSame(VideoConsultingRescheduleOffer::STATUS_PENDING, $offer->status);

        // Original booking is untouched until the customer responds.
        $this->assertSame(Assignment::STATUS_ASSIGNED, $assignment->fresh()->status);

        Mail::assertQueued(VideoConsultingRescheduleOfferMail::class, fn ($m) => $m->hasTo('customer@example.com'));
    }

    public function test_offer_requires_exactly_five_distinct_future_slots(): void
    {
        [$reader, $assignment] = $this->makeAcceptedBooking();

        $this->actingAs($reader)
            ->post("/video-consulting-bookings/{$assignment->id}/reschedule", [
                'slots' => [now()->addDay()->format('Y-m-d\TH:i'), now()->addDays(2)->format('Y-m-d\TH:i')],
            ])
            ->assertSessionHasErrors('slots');
    }

    public function test_customer_can_pick_a_slot_and_the_hold_and_whereby_room_update(): void
    {
        Mail::fake();
        [, $assignment, $hold] = $this->makeAcceptedBooking();
        $assignment->update(['whereby_room_url' => 'https://old.whereby.com/room', 'whereby_room_name' => '/old-room']);

        $newSlot = now()->addDays(3)->format('Y-m-d\TH:i');
        $offer = VideoConsultingRescheduleOffer::create([
            'assignment_id' => $assignment->id,
            'token' => bin2hex(random_bytes(32)),
            'candidate_slots' => [$newSlot, now()->addDays(4)->format('Y-m-d\TH:i'), now()->addDays(5)->format('Y-m-d\TH:i'), now()->addDays(6)->format('Y-m-d\TH:i'), now()->addDays(7)->format('Y-m-d\TH:i')],
            'status' => VideoConsultingRescheduleOffer::STATUS_PENDING,
            'expires_at' => now()->addHours(48),
        ]);

        $this->mock(VideoConsultingCallService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createRoomFor')->once();
        });

        $this->post("/video-consulting-reschedule/{$offer->token}", ['chosen_slot' => $newSlot])
            ->assertRedirect(route('video-consulting-reschedule-offer.confirmed', $offer->token));

        $offer->refresh();
        $this->assertSame(VideoConsultingRescheduleOffer::STATUS_ACCEPTED, $offer->status);

        $hold->refresh();
        $this->assertSame(30.0, $hold->slot_start->diffInMinutes($hold->slot_end));
        $this->assertTrue($hold->slot_start->equalTo(\Carbon\Carbon::parse($newSlot)));

        $assignment->refresh();
        $this->assertNull($assignment->whereby_room_url);

        Mail::assertQueued(VideoConsultingRescheduleConfirmedMail::class);
    }

    public function test_cannot_pick_a_slot_that_was_not_offered(): void
    {
        [, $assignment] = $this->makeAcceptedBooking();

        $offer = VideoConsultingRescheduleOffer::create([
            'assignment_id' => $assignment->id,
            'token' => bin2hex(random_bytes(32)),
            'candidate_slots' => [now()->addDays(3)->format('Y-m-d\TH:i')],
            'status' => VideoConsultingRescheduleOffer::STATUS_PENDING,
            'expires_at' => now()->addHours(48),
        ]);

        $this->post("/video-consulting-reschedule/{$offer->token}", ['chosen_slot' => now()->addDays(9)->format('Y-m-d\TH:i')])
            ->assertSessionHasErrors('chosen_slot');
    }

    public function test_expired_offer_link_returns_410(): void
    {
        [, $assignment] = $this->makeAcceptedBooking();

        $offer = VideoConsultingRescheduleOffer::create([
            'assignment_id' => $assignment->id,
            'token' => bin2hex(random_bytes(32)),
            'candidate_slots' => [now()->addDays(3)->format('Y-m-d\TH:i')],
            'status' => VideoConsultingRescheduleOffer::STATUS_PENDING,
            'expires_at' => now()->subHour(),
        ]);

        $this->get("/video-consulting-reschedule/{$offer->token}")->assertStatus(410);
    }

    public function test_escalation_command_flags_assignment_when_offer_deadline_passes(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        [, $assignment] = $this->makeAcceptedBooking();

        $offer = VideoConsultingRescheduleOffer::create([
            'assignment_id' => $assignment->id,
            'token' => bin2hex(random_bytes(32)),
            'candidate_slots' => [now()->addDays(3)->format('Y-m-d\TH:i')],
            'status' => VideoConsultingRescheduleOffer::STATUS_PENDING,
            'expires_at' => now()->subMinute(),
        ]);

        $this->artisan(EscalateExpiredVideoConsultingRescheduleOffers::class)->assertExitCode(0);

        $offer->refresh();
        $this->assertSame(VideoConsultingRescheduleOffer::STATUS_EXPIRED, $offer->status);

        $assignment->refresh();
        $this->assertSame(Assignment::STATUS_NEEDS_ATTENTION, $assignment->status);
        $this->assertSame('reschedule_deadline_expired', $assignment->video_consulting_needs_attention_reason);

        Mail::assertQueued(VideoConsultingAdminAlertMail::class, fn ($m) => $m->hasTo($admin->email));
    }
}
