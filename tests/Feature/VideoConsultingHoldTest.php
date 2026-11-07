<?php

namespace Tests\Feature;

use App\Models\ReaderAvailabilityRule;
use App\Models\ReaderVideoConsultingRate;
use App\Models\User;
use App\Models\VideoConsultingDuration;
use App\Models\VideoConsultingHold;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VideoConsultingHoldTest extends TestCase
{
    use RefreshDatabase;

    private function authHeaders(): array
    {
        config(['services.portal.webhook_secret' => 'test-secret']);

        return ['Authorization' => 'Bearer test-secret'];
    }

    private function makeBookableReader(): array
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $duration = VideoConsultingDuration::create([
            'minutes' => 30, 'label' => '30 Minutes', 'base_rate' => 20, 'min_reader_rate' => 10, 'max_reader_rate' => 100,
        ]);
        ReaderVideoConsultingRate::create(['reader_id' => $reader->id, 'duration_id' => $duration->id, 'rate' => 50]);

        $day = Carbon::now()->addDays(14)->startOfDay();
        ReaderAvailabilityRule::create([
            'reader_id' => $reader->id, 'day_of_week' => $day->dayOfWeek,
            'start_time' => '09:00', 'end_time' => '11:00', 'enabled' => true,
        ]);

        return [$reader, $duration, $day];
    }

    public function test_the_database_unique_index_rejects_a_duplicate_reader_slot(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $duration = VideoConsultingDuration::create([
            'minutes' => 30, 'label' => '30 Minutes', 'base_rate' => 20, 'min_reader_rate' => 10, 'max_reader_rate' => 100,
        ]);
        $slotStart = now()->addDay();

        VideoConsultingHold::create([
            'reader_id' => $reader->id, 'duration_id' => $duration->id,
            'slot_start' => $slotStart, 'slot_end' => $slotStart->copy()->addMinutes(30),
            'hold_token' => 'token-1', 'status' => VideoConsultingHold::STATUS_HELD, 'expires_at' => now()->addMinutes(20),
        ]);

        $this->expectException(QueryException::class);

        VideoConsultingHold::create([
            'reader_id' => $reader->id, 'duration_id' => $duration->id,
            'slot_start' => $slotStart, 'slot_end' => $slotStart->copy()->addMinutes(30),
            'hold_token' => 'token-2', 'status' => VideoConsultingHold::STATUS_HELD, 'expires_at' => now()->addMinutes(20),
        ]);
    }

    public function test_create_hold_succeeds_for_an_available_slot(): void
    {
        [$reader, $duration, $day] = $this->makeBookableReader();

        $response = $this->withHeaders($this->authHeaders())->postJson('/api/video-consulting/holds', [
            'reader_id' => $reader->id,
            'duration_id' => $duration->id,
            'slot_start' => $day->copy()->setTime(9, 0)->toIso8601String(),
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['hold_token', 'expires_at']);
        $this->assertDatabaseHas('video_consulting_holds', ['reader_id' => $reader->id, 'duration_id' => $duration->id]);
    }

    public function test_create_hold_rejects_a_slot_outside_the_readers_availability(): void
    {
        [$reader, $duration, $day] = $this->makeBookableReader();

        $response = $this->withHeaders($this->authHeaders())->postJson('/api/video-consulting/holds', [
            'reader_id' => $reader->id,
            'duration_id' => $duration->id,
            'slot_start' => $day->copy()->setTime(14, 0)->toIso8601String(),
        ]);

        $response->assertStatus(409);
    }

    public function test_create_hold_rejects_a_duration_the_reader_does_not_offer(): void
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
        // No ReaderVideoConsultingRate row — reader never opted into this duration.

        $response = $this->withHeaders($this->authHeaders())->postJson('/api/video-consulting/holds', [
            'reader_id' => $reader->id,
            'duration_id' => $duration->id,
            'slot_start' => $day->copy()->setTime(9, 0)->toIso8601String(),
        ]);

        $response->assertStatus(422);
    }

    public function test_release_hold_deletes_the_held_row(): void
    {
        [$reader, $duration, $day] = $this->makeBookableReader();
        $slotStart = $day->copy()->setTime(9, 0);

        $hold = VideoConsultingHold::create([
            'reader_id' => $reader->id, 'duration_id' => $duration->id,
            'slot_start' => $slotStart, 'slot_end' => $slotStart->copy()->addMinutes(30),
            'hold_token' => 'release-me', 'status' => VideoConsultingHold::STATUS_HELD, 'expires_at' => now()->addMinutes(20),
        ]);

        $this->withHeaders($this->authHeaders())->deleteJson("/api/video-consulting/holds/{$hold->hold_token}")->assertOk();

        $this->assertDatabaseMissing('video_consulting_holds', ['id' => $hold->id]);
    }

    public function test_console_command_only_sweeps_expired_held_rows(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $duration = VideoConsultingDuration::create([
            'minutes' => 30, 'label' => '30 Minutes', 'base_rate' => 20, 'min_reader_rate' => 10, 'max_reader_rate' => 100,
        ]);

        $expiredHeld = VideoConsultingHold::create([
            'reader_id' => $reader->id, 'duration_id' => $duration->id,
            'slot_start' => now()->addDay(), 'slot_end' => now()->addDay()->addMinutes(30),
            'hold_token' => 'expired-held', 'status' => VideoConsultingHold::STATUS_HELD, 'expires_at' => now()->subMinute(),
        ]);
        $futureHeld = VideoConsultingHold::create([
            'reader_id' => $reader->id, 'duration_id' => $duration->id,
            'slot_start' => now()->addDays(2), 'slot_end' => now()->addDays(2)->addMinutes(30),
            'hold_token' => 'future-held', 'status' => VideoConsultingHold::STATUS_HELD, 'expires_at' => now()->addMinutes(20),
        ]);
        $expiredConverted = VideoConsultingHold::create([
            'reader_id' => $reader->id, 'duration_id' => $duration->id,
            'slot_start' => now()->addDays(3), 'slot_end' => now()->addDays(3)->addMinutes(30),
            'hold_token' => 'expired-converted', 'status' => VideoConsultingHold::STATUS_CONVERTED, 'expires_at' => now()->subMinute(),
        ]);

        $this->artisan('video-consulting:release-expired-holds')->assertSuccessful();

        $this->assertDatabaseMissing('video_consulting_holds', ['id' => $expiredHeld->id]);
        $this->assertDatabaseHas('video_consulting_holds', ['id' => $futureHeld->id]);
        $this->assertDatabaseHas('video_consulting_holds', ['id' => $expiredConverted->id]);
    }
}
