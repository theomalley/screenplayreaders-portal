<?php

namespace Tests\Feature;

use App\Models\ReaderAvailabilityRule;
use App\Models\ReaderVideoConsultingRate;
use App\Models\User;
use App\Models\VideoConsultingDuration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Combined "Consulting" page (rate + availability merged onto one view, replacing the two
 * separate video-consulting-rate.edit / video-consulting-availability.edit pages).
 */
class VideoConsultingSettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_reader_sees_both_rate_and_availability_on_one_page(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $duration = VideoConsultingDuration::create([
            'minutes' => 30, 'label' => '30 Minutes', 'base_rate' => 20, 'min_reader_rate' => 10, 'max_reader_rate' => 100,
        ]);
        ReaderVideoConsultingRate::create(['reader_id' => $reader->id, 'duration_id' => $duration->id, 'rate' => 45]);
        ReaderAvailabilityRule::create([
            'reader_id' => $reader->id, 'day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '17:00', 'enabled' => true,
        ]);

        $response = $this->actingAs($reader)->get('/video-consulting');

        $response->assertOk();
        $response->assertSee('Your Rate');
        $response->assertSee('Your Availability');
        $response->assertSee('30 Minutes');
    }

    public function test_access_disabled_reader_gets_404(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $reader->readerProfile()->create(['initials' => 'XX', 'first_name' => 'X', 'last_name' => 'X', 'video_consulting_enabled' => false]);

        $this->actingAs($reader)->get('/video-consulting')->assertNotFound();
    }

    public function test_old_rate_and_availability_edit_routes_redirect_to_the_combined_page(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);

        $this->actingAs($reader)->get('/video-consulting-rate')->assertRedirect(route('video-consulting.edit'));
        $this->actingAs($reader)->get('/video-consulting-availability')->assertRedirect(route('video-consulting.edit'));
    }
}
