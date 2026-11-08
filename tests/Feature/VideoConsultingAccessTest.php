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
 * Admin-controlled kill switches for video consulting: a sitewide default
 * (Setting::video_consulting_enabled) and a per-reader tri-state override
 * (ReaderProfile::video_consulting_enabled: null = follow sitewide, true/false = always
 * on/off for that reader regardless of the sitewide setting) — see
 * App\Support\VideoConsultingAccess::enabledFor().
 */
class VideoConsultingAccessTest extends TestCase
{
    use RefreshDatabase;

    private function authHeaders(): array
    {
        config(['services.portal.webhook_secret' => 'test-secret']);

        return ['Authorization' => 'Bearer test-secret'];
    }

    public function test_a_reader_with_no_override_follows_the_sitewide_setting(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $reader->readerProfile()->create([
            'initials' => 'TR', 'first_name' => 'Test', 'last_name' => 'Reader',
        ]);

        $this->assertTrue(VideoConsultingAccess::enabledFor($reader->fresh('readerProfile')));

        Setting::setValue('video_consulting_enabled', '0');
        $this->assertFalse(VideoConsultingAccess::enabledFor($reader->fresh('readerProfile')));
    }

    public function test_an_explicit_disabled_override_wins_even_when_the_sitewide_setting_is_on(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $reader->readerProfile()->create([
            'initials' => 'TR', 'first_name' => 'Test', 'last_name' => 'Reader',
            'video_consulting_enabled' => false,
        ]);

        $this->assertFalse(VideoConsultingAccess::enabledFor($reader->fresh('readerProfile')));
    }

    public function test_an_explicit_enabled_override_wins_even_when_the_sitewide_setting_is_off(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $reader->readerProfile()->create([
            'initials' => 'TR', 'first_name' => 'Test', 'last_name' => 'Reader',
            'video_consulting_enabled' => true,
        ]);
        Setting::setValue('video_consulting_enabled', '0');

        $this->assertTrue(VideoConsultingAccess::enabledFor($reader->fresh('readerProfile')));
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

        $this->actingAs($reader)->get('/video-consulting')->assertNotFound();
    }

    public function test_self_serve_pages_404_when_disabled_for_that_reader(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $reader->readerProfile()->create([
            'initials' => 'TR', 'first_name' => 'Test', 'last_name' => 'Reader',
            'video_consulting_enabled' => false,
        ]);

        $this->actingAs($reader)->get('/video-consulting')->assertNotFound();
    }

    public function test_consulting_nav_item_stays_visible_when_the_reader_has_an_enabled_override(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $reader->readerProfile()->create([
            'initials' => 'TR', 'first_name' => 'Test', 'last_name' => 'Reader',
            'video_consulting_enabled' => true,
        ]);
        Setting::setValue('video_consulting_enabled', '0');

        $this->actingAs($reader)->get('/assignments')->assertSee('Consulting');
        $this->actingAs($reader)->get('/video-consulting')->assertOk();
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
            'video_consulting_enabled' => 'disabled',
        ])->assertRedirect();

        $this->assertFalse($reader->fresh('readerProfile')->readerProfile->video_consulting_enabled);
    }

    public function test_leaving_the_select_on_default_clears_any_previous_override(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $reader = User::factory()->create(['role' => 'reader', 'email' => 'reader2@example.com']);
        $reader->readerProfile()->create([
            'initials' => 'TR', 'first_name' => 'Test', 'last_name' => 'Reader',
            'video_consulting_enabled' => false,
        ]);

        $this->actingAs($admin)->patch("/readers/{$reader->id}", [
            'initials' => 'TR', 'first_name' => 'Test', 'last_name' => 'Reader',
            'email' => 'reader2@example.com', 'availability' => 'available',
            'video_consulting_enabled' => 'default',
        ])->assertRedirect();

        $this->assertNull($reader->fresh('readerProfile')->readerProfile->video_consulting_enabled);
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

    public function test_booking_api_still_offers_a_reader_with_an_enabled_override_when_global_is_off(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $reader->readerProfile()->create([
            'initials' => 'TR', 'first_name' => 'Test', 'last_name' => 'Reader',
            'video_consulting_enabled' => true,
        ]);
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
            ->assertJsonCount(1);

        $this->withHeaders($this->authHeaders())
            ->postJson('/api/video-consulting/holds', [
                'reader_id' => $reader->id, 'duration_id' => $duration->id,
                'slot_start' => $day->copy()->setTime(9, 0)->toIso8601String(),
            ])
            ->assertStatus(201);
    }
}
