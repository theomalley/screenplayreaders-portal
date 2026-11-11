<?php

namespace Tests\Feature;

use App\Mail\VideoConsultingCallReminderMail;
use App\Mail\VideoConsultingReaderCallReminderMail;
use App\Models\Assignment;
use App\Models\User;
use App\Models\VideoConsultingDuration;
use App\Models\VideoConsultingHold;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class VideoConsultingCallReminderTest extends TestCase
{
    use RefreshDatabase;

    private function makeAcceptedBooking(Carbon $slotStart, ?string $customerEmail = 'customer@example.com'): array
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $reader->readerProfile()->create(['initials' => 'TR', 'first_name' => 'Test', 'last_name' => 'Reader']);

        $duration = VideoConsultingDuration::create([
            'minutes' => 30, 'label' => '30 Minutes', 'base_rate' => 20, 'min_reader_rate' => 10, 'max_reader_rate' => 100,
        ]);

        $hold = VideoConsultingHold::create([
            'reader_id' => $reader->id, 'duration_id' => $duration->id,
            'slot_start' => $slotStart, 'slot_end' => $slotStart->copy()->addMinutes(30),
            'hold_token' => 'tok-' . uniqid(), 'status' => VideoConsultingHold::STATUS_CONVERTED, 'expires_at' => now(),
        ]);

        $assignment = Assignment::create([
            'order_number' => 'TEST-VC-' . random_int(100000, 999999), 'vendor' => 'sr',
            'assignment_type' => Assignment::TYPE_VIDEO_CONSULTING, 'script_title' => '30 Minute Script Consultation',
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

    public function test_sends_24h_reminder_to_customer_and_reader_within_window(): void
    {
        Mail::fake();
        [$reader, $assignment] = $this->makeAcceptedBooking(now()->addHours(24));

        $this->artisan('video-consulting:send-call-reminders')->assertSuccessful();

        Mail::assertQueued(VideoConsultingCallReminderMail::class, fn ($m) => $m->hasTo('customer@example.com') && $m->assignment->is($assignment));
        Mail::assertQueued(VideoConsultingReaderCallReminderMail::class, fn ($m) => $m->hasTo($reader->email));

        $this->assertNotNull($assignment->fresh()->video_consulting_reminder_24h_sent_at);
    }

    public function test_does_not_send_twice_for_the_same_window(): void
    {
        Mail::fake();
        [, $assignment] = $this->makeAcceptedBooking(now()->addHours(24));

        $this->artisan('video-consulting:send-call-reminders');
        $this->artisan('video-consulting:send-call-reminders');

        Mail::assertQueued(VideoConsultingCallReminderMail::class, 1);
        Mail::assertQueued(VideoConsultingReaderCallReminderMail::class, 1);
    }

    public function test_does_not_send_outside_the_reminder_window(): void
    {
        Mail::fake();
        $this->makeAcceptedBooking(now()->addHours(10));

        $this->artisan('video-consulting:send-call-reminders');

        Mail::assertNotQueued(VideoConsultingCallReminderMail::class);
        Mail::assertNotQueued(VideoConsultingReaderCallReminderMail::class);
    }

    public function test_does_not_send_for_a_cancelled_assignment(): void
    {
        Mail::fake();
        [, $assignment] = $this->makeAcceptedBooking(now()->addHours(24));
        $assignment->update(['status' => Assignment::STATUS_CANCELLED]);

        $this->artisan('video-consulting:send-call-reminders');

        Mail::assertNotQueued(VideoConsultingCallReminderMail::class);
    }

    public function test_does_not_send_without_a_customer_email_but_still_notifies_reader(): void
    {
        Mail::fake();
        [$reader, $assignment] = $this->makeAcceptedBooking(now()->addHours(24), null);

        $this->artisan('video-consulting:send-call-reminders');

        Mail::assertNotQueued(VideoConsultingCallReminderMail::class);
        Mail::assertQueued(VideoConsultingReaderCallReminderMail::class, fn ($m) => $m->hasTo($reader->email));
    }

    public function test_customer_reminder_falls_back_to_blade_view_when_no_mailersend_template_configured(): void
    {
        config(['services.mailersend.video_consulting_reminder_customer_24h_template_id' => '']);
        [, $assignment] = $this->makeAcceptedBooking(now()->addHours(24));

        $mail = new VideoConsultingCallReminderMail($assignment);
        $rendered = $mail->render();

        $this->assertStringContainsString('Reminder: your call is in 24 hours', $rendered);
        $this->assertStringContainsString('View Call Details', $rendered);
    }
}
