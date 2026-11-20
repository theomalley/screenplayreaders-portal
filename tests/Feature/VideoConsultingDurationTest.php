<?php

namespace Tests\Feature;

use App\Models\ReaderVideoConsultingRate;
use App\Models\User;
use App\Models\VideoConsultingDuration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin CRUD for video consulting durations (app/Http/Controllers/Api/VideoConsultingDurationController.php),
 * gated by the shared-secret portal.secret middleware like every other theme-facing endpoint.
 */
class VideoConsultingDurationTest extends TestCase
{
    use RefreshDatabase;

    private function authHeaders(): array
    {
        config(['services.portal.webhook_secret' => 'test-secret']);

        return ['Authorization' => 'Bearer test-secret'];
    }

    public function test_admin_can_create_a_duration(): void
    {
        $response = $this->withHeaders($this->authHeaders())->postJson('/api/video-consulting/admin/durations', [
            'minutes'         => 30,
            'label'           => '30 Minutes',
            'base_rate'       => 20,
            'min_reader_rate' => 10,
            'max_reader_rate' => 100,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('video_consulting_durations', ['minutes' => 30, 'label' => '30 Minutes']);
    }

    public function test_create_rejects_max_rate_below_min_rate(): void
    {
        $response = $this->withHeaders($this->authHeaders())->postJson('/api/video-consulting/admin/durations', [
            'minutes'         => 30,
            'label'           => '30 Minutes',
            'base_rate'       => 20,
            'min_reader_rate' => 100,
            'max_reader_rate' => 10,
        ]);

        $response->assertStatus(422);
    }

    public function test_request_without_the_shared_secret_is_rejected(): void
    {
        config(['services.portal.webhook_secret' => 'test-secret']);

        $response = $this->postJson('/api/video-consulting/admin/durations', [
            'minutes' => 30, 'label' => 'x', 'base_rate' => 1, 'min_reader_rate' => 1, 'max_reader_rate' => 2,
        ]);

        $response->assertStatus(401);
    }

    public function test_delete_is_blocked_once_a_reader_rate_references_the_duration(): void
    {
        $duration = VideoConsultingDuration::create([
            'minutes' => 30, 'label' => '30 Minutes', 'base_rate' => 20, 'min_reader_rate' => 10, 'max_reader_rate' => 100,
        ]);
        $reader = User::factory()->create(['role' => 'reader']);
        ReaderVideoConsultingRate::create(['reader_id' => $reader->id, 'duration_id' => $duration->id, 'rate' => 50]);

        $response = $this->withHeaders($this->authHeaders())->deleteJson("/api/video-consulting/admin/durations/{$duration->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('video_consulting_durations', ['id' => $duration->id]);
    }

    public function test_delete_succeeds_when_duration_has_no_history(): void
    {
        $duration = VideoConsultingDuration::create([
            'minutes' => 30, 'label' => '30 Minutes', 'base_rate' => 20, 'min_reader_rate' => 10, 'max_reader_rate' => 100,
        ]);

        $response = $this->withHeaders($this->authHeaders())->deleteJson("/api/video-consulting/admin/durations/{$duration->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('video_consulting_durations', ['id' => $duration->id]);
    }

    public function test_admin_can_set_base_rate_percent_on_create(): void
    {
        $response = $this->withHeaders($this->authHeaders())->postJson('/api/video-consulting/admin/durations', [
            'minutes'           => 60,
            'label'             => '60 Minutes',
            'base_rate'         => 100,
            'base_rate_percent' => 15,
            'min_reader_rate'   => 149,
            'max_reader_rate'   => 5000,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('video_consulting_durations', ['minutes' => 60, 'base_rate_percent' => 15]);
    }

    public function test_base_rate_percent_over_100_is_rejected(): void
    {
        $response = $this->withHeaders($this->authHeaders())->postJson('/api/video-consulting/admin/durations', [
            'minutes'           => 60,
            'label'             => '60 Minutes',
            'base_rate'         => 100,
            'base_rate_percent' => 101,
            'min_reader_rate'   => 149,
            'max_reader_rate'   => 5000,
        ]);

        $response->assertStatus(422);
    }

    public function test_base_rate_percent_defaults_to_zero_when_omitted(): void
    {
        $duration = VideoConsultingDuration::create([
            'minutes' => 30, 'label' => '30 Minutes', 'base_rate' => 20, 'min_reader_rate' => 10, 'max_reader_rate' => 100,
        ]);

        $this->assertSame('0.00', $duration->refresh()->base_rate_percent);
    }

    public function test_effective_base_rate_rounds_the_percent_cut_to_whole_dollars(): void
    {
        $duration = VideoConsultingDuration::create([
            'minutes' => 60, 'label' => '60 Minutes', 'base_rate' => 0, 'base_rate_percent' => 33,
            'min_reader_rate' => 10, 'max_reader_rate' => 5000,
        ]);

        // 33% of 149 = 49.17, rounds to 49.
        $this->assertSame(49.0, $duration->effectiveBaseRate(149));
    }

    public function test_effective_base_rate_floors_at_the_flat_base_rate(): void
    {
        $duration = VideoConsultingDuration::create([
            'minutes' => 30, 'label' => '30 Minutes', 'base_rate' => 100, 'base_rate_percent' => 15,
            'min_reader_rate' => 10, 'max_reader_rate' => 5000,
        ]);

        $this->assertSame(100.0, $duration->effectiveBaseRate(149)); // 15% of 149 = 22.35, floor wins
        $this->assertSame(750.0, $duration->effectiveBaseRate(5000)); // 15% of 5000 = 750, percent wins
    }
}
