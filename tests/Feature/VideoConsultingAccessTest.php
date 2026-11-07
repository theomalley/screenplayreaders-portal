<?php

namespace Tests\Feature;

use App\Models\ReaderAvailabilityRule;
use App\Models\ReaderVideoConsultingRate;
use App\Models\Setting;
use App\Models\User;
use App\Models\VideoConsultingDuration;
use App\Support\VideoConsultingAccess;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin-controlled kill switches for video consulting: a sitewide toggle
 * (Setting::video_consulting_enabled) and a per-reader toggle
 * (ReaderProfile::video_consulting_enabled). Both must be on for the feature to be
 * usable by a given reader — see App\Support\VideoConsultingAccess::enabledFor().
 */
class VideoConsultingAccessTest extends TestCase
{
    use RefreshDatabase;

    private function authHeaders(): array
    {
        config(['services.portal.webhook_secret' => 'test-secret']);

        return ['Authorization' => 'Bearer test-secret'];
    }

    public function test_enabled_for_requires_both_global_and_per_reader_flags(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $reader->readerProfile()->create([
            'initials' => 'TR', 'first_name' => 'Test', 'last_name' => 'Reader',
        ]);

        $this->assertTrue(VideoConsultingAccess::enabledFor($reader->fresh('readerProfile')));

        Setting::setValue('video_consulting_enabled', '0');
        $this->assertFalse(VideoConsultingAccess::enabledFor($reader->fresh('readerProfile')));

        Setting::setValue('video_consulting_enabled', '1');
        $reader->readerProfile->update(['video_consulting_enabled' => false]);
        $this->assertFalse(VideoConsultingAccess::enabledFor($reader->fresh('readerProfile')));
    }

    public function test_a_non_reader_is_never_enabled(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->assertFalse(VideoConsultingAccess::enabledFor($admin));
    }

    public function test_consulting_nav_item_is_hidden_when_disabled_globally(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $reader->readerProfile()->create(['initials' => 'TR', 'first_name' => 'Test', 'last_name' => 'Reader']);

        $this->actingAs($reader)->get('/assignments')->assertSee('Consulting');

        Setting::setValue('video_consulting_enabled', '0');

        $this->actingAs($reader)->get('/assignments')->assertDontSee('Consulting');
    }

    public function test_consulting_nav_item_is_hidden_when_disabled_for_that_reader_only(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $reader->readerProfile()->create([
            'initials' => 'TR', 'first_name' => 'Test', 'last_name' => 'Reader',
            'video_consulting_enabled' => false,
        ]);

        $this->actingAs($reader)->get('/assignments')->assertDontSee('Consulting');
    }

    public function test_self_serve_pages_404_when_disabled_globally(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $reader->readerProfile()->create(['initials' => 'TR', 'first_name' => 'Test', 'last_name' => 'Reader']);
        Setting::setValue('video_consulting_enabled', '0');

        $this->actingAs($reader)->get('/video-consulting-rate')->assertNotFound();
        $this->actingAs($reader)->get('/video-consulting-availability')->assertNotFound();
    }

    public function test_self_serve_pages_404_when_disabled_for_that_reader(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $reader->readerProfile()->create([
            'initials' => 'TR', 'first_name' => 'Test', 'last_name' => 'Reader',
            'video_consulting_enabled' => false,
        ]);

        $this->actingAs($reader)->get('/video-consulting-rate')->assertNotFound();
        $this->actingAs($reader)->get('/video-consulting-availability')->assertNotFound();
    }

    public function test_admin_can_toggle_the_global_setting(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->patch('/settings/video-consulting', ['video_consulting_enabled' => '0'])
            ->assertRedirect(route('settings.assignments'));

        $this->assertFalse(VideoConsultingAccess::enabledGlobally());
    }

    public function test_admin_can_disable_consulting_for_a_single_reader(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $reader = User::factory()->create(['role' => 'reader', 'email' => 'reader@example.com']);
        $reader->readerProfile()->create(['initials' => 'TR', 'first_name' => 'Test', 'last_name' => 'Reader']);

        $this->actingAs($admin)->patch("/readers/{$reader->id}", [
            'initials' => 'TR', 'first_name' => 'Test', 'last_name' => 'Reader',
            'email' => 'reader@example.com', 'availability' => 'available',
            // video_consulting_enabled omitted => unchecked checkbox => request()->boolean() is false
        ])->assertRedirect();

        $this->assertFalse($reader->fresh('readerProfile')->readerProfile->video_consulting_enabled);
    }

    public function test_booking_api_hides_a_disabled_readers_rates_and_slots(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $day = Carbon::now()->addDays(14)->startOfDay();
        ReaderAvailabilityRule::create([
            'reader_id' => $reader->id, 'day_of_week' => $day->dayOfWeek,
            'start_time' => '09:00', 'end_time' => '11:00', 'enabled' => true,
        ]);
        $duration = VideoConsultingDuration::create([
            'minutes' => 30, 'label' => '30 Minutes', 'base_rate' => 20, 'min_reader_rate' => 10, 'max_reader_rate' => 100,
        ]);
        ReaderVideoConsultingRate::create(['reader_id' => $reader->id, 'duration_id' => $duration->id, 'rate' => 50]);

        Setting::setValue('video_consulting_enabled', '0');

        $this->withHeaders($this->authHeaders())
            ->getJson("/api/video-consulting/readers/{$reader->id}/rates")
            ->assertOk()
            ->assertJson([]);

        $this->withHeaders($this->authHeaders())
            ->getJson('/api/video-consulting/available-slots?' . http_build_query([
                'reader_id' => $reader->id, 'duration_id' => $duration->id,
                'from' => $day->toIso8601String(), 'to' => $day->copy()->endOfDay()->toIso8601String(),
            ]))
            ->assertOk()
            ->assertJson([]);

        $this->withHeaders($this->authHeaders())
            ->postJson('/api/video-consulting/holds', [
                'reader_id' => $reader->id, 'duration_id' => $duration->id,
                'slot_start' => $day->copy()->setTime(9, 0)->toIso8601String(),
            ])
            ->assertStatus(422);
    }
}
