<?php

namespace Tests\Feature;

use App\Models\ReaderVideoConsultingRate;
use App\Models\User;
use App\Models\VideoConsultingDuration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A reader who has opted into offering video consulting calls (set a rate for at least one
 * duration) must have email_notify_video_consulting forced on, regardless of what they submit
 * — see ProfileController::updateNotifications().
 */
class ProfileNotificationsVideoConsultingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_toggle_is_forced_on_for_a_reader_who_offers_video_consulting(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $reader->readerProfile()->create(['initials' => 'TR', 'first_name' => 'Test', 'last_name' => 'Reader']);
        $duration = VideoConsultingDuration::create([
            'minutes' => 30, 'label' => '30 Minutes', 'base_rate' => 20, 'min_reader_rate' => 10, 'max_reader_rate' => 100,
        ]);
        ReaderVideoConsultingRate::create(['reader_id' => $reader->id, 'duration_id' => $duration->id, 'rate' => 50]);

        // Deliberately omit email_notify_video_consulting from the submission — simulates
        // the checkbox being disabled client-side and never posted at all.
        $this->actingAs($reader)->patch('/profile/notifications', [])->assertRedirect();

        $this->assertTrue($reader->fresh('readerProfile')->readerProfile->email_notify_video_consulting);
    }

    public function test_the_toggle_is_a_normal_optional_checkbox_for_a_reader_with_no_rates(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $reader->readerProfile()->create(['initials' => 'TR', 'first_name' => 'Test', 'last_name' => 'Reader']);

        $this->actingAs($reader)->patch('/profile/notifications', [])->assertRedirect();

        $this->assertFalse($reader->fresh('readerProfile')->readerProfile->email_notify_video_consulting);

        $this->actingAs($reader)->patch('/profile/notifications', ['email_notify_video_consulting' => '1'])->assertRedirect();

        $this->assertTrue($reader->fresh('readerProfile')->readerProfile->email_notify_video_consulting);
    }
}
