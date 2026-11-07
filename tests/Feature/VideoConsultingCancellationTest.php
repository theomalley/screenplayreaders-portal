<?php

namespace Tests\Feature;

use App\Mail\VideoConsultingAdminAlertMail;
use App\Mail\VideoConsultingCancelledMail;
use App\Models\Assignment;
use App\Models\User;
use App\Models\VideoConsultingDuration;
use App\Models\VideoConsultingHold;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class VideoConsultingCancellationTest extends TestCase
{
    use RefreshDatabase;

    private function makeAcceptedBooking(?string $customerEmail = null): array
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
            'assignment_type' => Assignment::TYPE_VIDEO_CONSULTING, 'script_title' => 'x', 'writer_name' => 'x',
            'pay_rate' => 50, 'status' => Assignment::STATUS_ASSIGNED, 'is_test' => true,
            'assigned_reader_id' => $reader->id, 'video_consulting_hold_id' => $hold->id,
            'video_consulting_customer_email' => $customerEmail,
        ]);

        return [$reader, $assignment];
    }

    public function test_reader_can_cancel_their_own_video_consulting_booking(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        [$reader, $assignment] = $this->makeAcceptedBooking('customer@example.com');

        $this->actingAs($reader)
            ->post("/video-consulting-bookings/{$assignment->id}/cancel", ['reason' => 'Scheduling conflict'])
            ->assertRedirect();

        $assignment->refresh();
        $this->assertSame(Assignment::STATUS_CANCELLED, $assignment->status);
        $this->assertSame('Scheduling conflict', $assignment->cancellation_reason);

        Mail::assertQueued(VideoConsultingCancelledMail::class, fn ($m) => $m->hasTo('customer@example.com'));
        Mail::assertQueued(VideoConsultingAdminAlertMail::class, fn ($m) => $m->hasTo($admin->email));
    }

    public function test_cancelling_without_a_customer_email_only_notifies_admins(): void
    {
        Mail::fake();
        [$reader, $assignment] = $this->makeAcceptedBooking(null);

        $this->actingAs($reader)->post("/video-consulting-bookings/{$assignment->id}/cancel")->assertRedirect();

        Mail::assertNotQueued(VideoConsultingCancelledMail::class);
    }

    public function test_another_reader_cannot_cancel_a_booking_that_is_not_theirs(): void
    {
        [, $assignment] = $this->makeAcceptedBooking();
        $otherReader = User::factory()->create(['role' => 'reader']);

        $this->actingAs($otherReader)
            ->post("/video-consulting-bookings/{$assignment->id}/cancel")
            ->assertForbidden();
    }

    public function test_cannot_cancel_a_booking_that_is_not_in_assigned_status(): void
    {
        [$reader, $assignment] = $this->makeAcceptedBooking();
        $assignment->update(['status' => Assignment::STATUS_COMPLETED]);

        $this->actingAs($reader)
            ->post("/video-consulting-bookings/{$assignment->id}/cancel")
            ->assertForbidden();
    }

    public function test_admin_can_cancel_any_video_consulting_booking(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        [, $assignment] = $this->makeAcceptedBooking();

        $this->actingAs($admin)
            ->post("/video-consulting-bookings/{$assignment->id}/cancel")
            ->assertRedirect();

        $this->assertSame(Assignment::STATUS_CANCELLED, $assignment->fresh()->status);
    }
}
