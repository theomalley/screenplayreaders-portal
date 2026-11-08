<?php

namespace Tests\Feature;

use App\Models\ReaderAvailabilityRule;
use App\Models\ReaderBlackoutDate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reader self-service weekly availability + blackout dates (VideoConsultingAvailabilityController).
 */
class VideoConsultingAvailabilityControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_reader_can_set_a_weekly_window(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);

        $this->actingAs($reader)->patch('/video-consulting-availability', [
            'days' => [1 => ['enabled' => '1', 'start_time' => '09:00', 'end_time' => '17:00']],
        ])->assertRedirect(route('video-consulting.edit'));

        $this->assertDatabaseHas('reader_availability_rules', [
            'reader_id' => $reader->id, 'day_of_week' => 1, 'enabled' => true,
        ]);
    }

    public function test_unchecking_a_day_removes_its_rule(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        ReaderAvailabilityRule::create([
            'reader_id' => $reader->id, 'day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '17:00', 'enabled' => true,
        ]);

        $this->actingAs($reader)->patch('/video-consulting-availability', ['days' => []]);

        $this->assertDatabaseMissing('reader_availability_rules', ['reader_id' => $reader->id, 'day_of_week' => 1]);
    }

    public function test_reader_can_set_their_minimum_lead_time(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $reader->readerProfile()->create(['initials' => 'TR', 'first_name' => 'Test', 'last_name' => 'Reader']);

        $this->actingAs($reader)->patch('/video-consulting-availability', [
            'days' => [], 'min_lead_hours' => '24',
        ])->assertRedirect(route('video-consulting.edit'));

        $this->assertSame(24, $reader->fresh('readerProfile')->readerProfile->video_consulting_min_lead_hours);
    }

    public function test_omitting_min_lead_hours_saves_it_as_zero(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $reader->readerProfile()->create([
            'initials' => 'TR', 'first_name' => 'Test', 'last_name' => 'Reader',
            'video_consulting_min_lead_hours' => 24,
        ]);

        $this->actingAs($reader)->patch('/video-consulting-availability', ['days' => []]);

        $this->assertSame(0, $reader->fresh('readerProfile')->readerProfile->video_consulting_min_lead_hours);
    }

    public function test_end_time_before_start_time_is_rejected(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);

        $this->actingAs($reader)->patch('/video-consulting-availability', [
            'days' => [1 => ['enabled' => '1', 'start_time' => '17:00', 'end_time' => '09:00']],
        ])->assertSessionHasErrors('days.1.end_time');
    }

    public function test_reader_can_add_and_remove_a_blackout(): void
    {
        $reader = User::factory()->create(['role' => 'reader']);

        $this->actingAs($reader)->post('/video-consulting-availability/blackouts', [
            'start_at' => now()->addDays(5)->format('Y-m-d H:i:s'),
            'end_at'   => now()->addDays(6)->format('Y-m-d H:i:s'),
            'reason'   => 'Vacation',
        ])->assertRedirect(route('video-consulting.edit'));

        $blackout = ReaderBlackoutDate::where('reader_id', $reader->id)->first();
        $this->assertNotNull($blackout);

        $this->actingAs($reader)
            ->delete("/video-consulting-availability/blackouts/{$blackout->id}")
            ->assertRedirect(route('video-consulting.edit'));

        $this->assertDatabaseMissing('reader_blackout_dates', ['id' => $blackout->id]);
    }

    public function test_a_reader_cannot_delete_another_readers_blackout(): void
    {
        $readerA = User::factory()->create(['role' => 'reader']);
        $readerB = User::factory()->create(['role' => 'reader']);
        $blackout = ReaderBlackoutDate::create([
            'reader_id' => $readerA->id, 'start_at' => now()->addDay(), 'end_at' => now()->addDays(2),
        ]);

        $this->actingAs($readerB)
            ->delete("/video-consulting-availability/blackouts/{$blackout->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('reader_blackout_dates', ['id' => $blackout->id]);
    }
}
