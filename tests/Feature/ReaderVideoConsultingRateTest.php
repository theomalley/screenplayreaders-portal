<?php

namespace Tests\Feature;

use App\Models\ReaderVideoConsultingRate;
use App\Models\User;
use App\Models\VideoConsultingDuration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reader self-service rate page (VideoConsultingRateController) — the first self-serve
 * rate-setting UI in the app. Always scoped to auth()->user(), no route parameter.
 */
class ReaderVideoConsultingRateTest extends TestCase
{
    use RefreshDatabase;

    private function makeDuration(float $min = 10, float $max = 100): VideoConsultingDuration
    {
        return VideoConsultingDuration::create([
            'minutes' => 30, 'label' => '30 Minutes', 'base_rate' => 20,
            'min_reader_rate' => $min, 'max_reader_rate' => $max,
        ]);
    }

    public function test_reader_can_set_a_rate_within_bounds(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $duration = $this->makeDuration();

        $this->actingAs($reader)
            ->patch('/video-consulting-rate', ['rates' => [$duration->id => '45.00']])
            ->assertRedirect(route('video-consulting.edit'));

        $this->assertDatabaseHas('reader_video_consulting_rates', [
            'reader_id' => $reader->id, 'duration_id' => $duration->id, 'rate' => 45.00,
        ]);
    }

    public function test_rate_outside_the_durations_bounds_is_rejected(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $duration = $this->makeDuration(10, 100);

        $this->actingAs($reader)
            ->patch('/video-consulting-rate', ['rates' => [$duration->id => '500.00']])
            ->assertSessionHasErrors("rates.{$duration->id}");

        $this->assertDatabaseMissing('reader_video_consulting_rates', ['reader_id' => $reader->id]);
    }

    public function test_blank_rate_removes_any_existing_opt_in(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $duration = $this->makeDuration();
        ReaderVideoConsultingRate::create(['reader_id' => $reader->id, 'duration_id' => $duration->id, 'rate' => 50]);

        $this->actingAs($reader)->patch('/video-consulting-rate', ['rates' => [$duration->id => '']]);

        $this->assertDatabaseMissing('reader_video_consulting_rates', [
            'reader_id' => $reader->id, 'duration_id' => $duration->id,
        ]);
    }

    public function test_update_always_writes_to_the_authenticated_readers_own_row(): void
    {
        $readerA = User::factory()->create(['role' => 'reader']);
        $readerB = User::factory()->create(['role' => 'reader']);
        $duration = $this->makeDuration();

        $this->actingAs($readerA)->patch('/video-consulting-rate', ['rates' => [$duration->id => '30.00']]);

        $this->assertDatabaseHas('reader_video_consulting_rates', ['reader_id' => $readerA->id, 'rate' => 30.00]);
        $this->assertDatabaseMissing('reader_video_consulting_rates', ['reader_id' => $readerB->id]);
    }
}
