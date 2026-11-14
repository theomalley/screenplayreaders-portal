<?php

namespace Tests\Feature;

use App\Models\ReaderAvailabilityRule;
use App\Models\ReaderVideoConsultingRate;
use App\Models\User;
use App\Models\VideoConsultingDuration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The standalone "Consulting" page/nav tab is retired entirely — Rate and Availability both
 * now live on the reader's main Profile page (see ProfileController::edit() /
 * resources/views/partials/video-consulting-availability.blade.php). Every old entry point
 * that used to render the combined page now just redirects to profile.edit, with no access
 * gate of its own — the Profile page itself decides what to show based on access.
 */
class VideoConsultingSettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_consulting_page_redirects_to_profile(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);

        $this->actingAs($reader)->get('/video-consulting')->assertRedirect(route('profile.edit'));
    }

    public function test_old_consulting_page_redirects_to_profile_even_for_access_disabled_reader(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $reader->readerProfile()->create(['initials' => 'XX', 'first_name' => 'X', 'last_name' => 'X', 'video_consulting_enabled' => false]);

        $this->actingAs($reader)->get('/video-consulting')->assertRedirect(route('profile.edit'));
    }

    public function test_profile_page_shows_rate_and_availability_for_an_enabled_reader(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $duration = VideoConsultingDuration::create([
            'minutes' => 30, 'label' => '30 Minutes', 'base_rate' => 20, 'min_reader_rate' => 10, 'max_reader_rate' => 100,
        ]);
        ReaderVideoConsultingRate::create(['reader_id' => $reader->id, 'duration_id' => $duration->id, 'rate' => 45]);
        ReaderAvailabilityRule::create([
            'reader_id' => $reader->id, 'day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '17:00', 'enabled' => true,
        ]);

        $response = $this->actingAs($reader)->get(route('profile.edit'));

        $response->assertOk();
        $response->assertSee('Video Consulting Rate');
        $response->assertSee('30 Minutes');
        $response->assertSee('Video Consulting Availability');
        $response->assertSee('Blackout Dates');
    }

    public function test_access_disabled_reader_does_not_see_video_consulting_on_profile(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $reader->readerProfile()->create(['initials' => 'XX', 'first_name' => 'X', 'last_name' => 'X', 'video_consulting_enabled' => false]);

        $response = $this->actingAs($reader)->get(route('profile.edit'));

        $response->assertOk();
        $response->assertDontSee('Video Consulting Rate');
        $response->assertDontSee('Video Consulting Availability');
    }

    public function test_old_rate_and_availability_edit_routes_redirect_to_profile(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);

        $this->actingAs($reader)->get('/video-consulting-rate')->assertRedirect(route('profile.edit'));
        $this->actingAs($reader)->get('/video-consulting-availability')->assertRedirect(route('profile.edit'));
    }
}
