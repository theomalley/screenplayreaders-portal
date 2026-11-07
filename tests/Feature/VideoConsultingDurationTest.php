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
}
