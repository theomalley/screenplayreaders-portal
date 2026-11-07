<?php

namespace Tests\Feature;

use App\Mail\VideoConsultingAdminAlertMail;
use App\Models\Assignment;
use App\Models\User;
use App\Models\VideoConsultingDuration;
use App\Models\VideoConsultingHold;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class VideoConsultingNoShowTest extends TestCase
{
    use RefreshDatabase;

    private function makeAcceptedBooking(bool $slotInPast = true): array
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $reader->readerProfile()->create(['initials' => 'TR', 'first_name' => 'Test', 'last_name' => 'Reader']);

        $duration = VideoConsultingDuration::create([
            'minutes' => 30, 'label' => '30 Minutes', 'base_rate' => 20, 'min_reader_rate' => 10, 'max_reader_rate' => 100,
        ]);

        $slotStart = $slotInPast ? now()->subHour() : now()->addDay();

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
        ]);

        return [$reader, $assignment];
    }

    public function test_reader_can_report_a_no_show_once_the_slot_has_passed(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        [$reader, $assignment] = $this->makeAcceptedBooking(slotInPast: true);

        $this->actingAs($reader)
            ->post("/video-consulting-bookings/{$assignment->id}/no-show")
            ->assertRedirect();

        $assignment->refresh();
        $this->assertSame(Assignment::STATUS_NEEDS_ATTENTION, $assignment->status);
        $this->assertSame('no_show_reported_by_reader', $assignment->video_consulting_needs_attention_reason);

        Mail::assertQueued(VideoConsultingAdminAlertMail::class, fn ($m) => $m->hasTo($admin->email));
    }

    public function test_admin_confirming_a_no_show_cancels_and_zeroes_pay(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$reader, $assignment] = $this->makeAcceptedBooking();
        $assignment->update([
            'status' => Assignment::STATUS_NEEDS_ATTENTION,
            'video_consulting_needs_attention_reason' => 'no_show_reported_by_reader',
        ]);

        $this->actingAs($admin)
            ->post("/qc/{$assignment->id}/video-consulting/no-show/confirm")
            ->assertRedirect();

        $assignment->refresh();
        $this->assertSame(Assignment::STATUS_CANCELLED, $assignment->status);
        $this->assertSame(0.0, (float) $assignment->pay_rate);
        $this->assertNull($assignment->video_consulting_needs_attention_reason);
    }

    public function test_admin_dismissing_a_no_show_returns_it_to_assigned(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$reader, $assignment] = $this->makeAcceptedBooking();
        $assignment->update([
            'status' => Assignment::STATUS_NEEDS_ATTENTION,
            'video_consulting_needs_attention_reason' => 'no_show_reported_by_reader',
        ]);

        $this->actingAs($admin)
            ->post("/qc/{$assignment->id}/video-consulting/no-show/dismiss")
            ->assertRedirect();

        $assignment->refresh();
        $this->assertSame(Assignment::STATUS_ASSIGNED, $assignment->status);
        $this->assertNull($assignment->video_consulting_needs_attention_reason);
    }

    public function test_reader_cannot_resolve_their_own_no_show_report(): void
    {
        [$reader, $assignment] = $this->makeAcceptedBooking();
        $assignment->update(['status' => Assignment::STATUS_NEEDS_ATTENTION]);

        $this->actingAs($reader)
            ->post("/qc/{$assignment->id}/video-consulting/no-show/confirm")
            ->assertForbidden();
    }
}
