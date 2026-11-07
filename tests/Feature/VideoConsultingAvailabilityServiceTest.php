<?php

namespace Tests\Feature;

use App\Models\ReaderAvailabilityRule;
use App\Models\ReaderBlackoutDate;
use App\Models\User;
use App\Models\VideoConsultingDuration;
use App\Models\VideoConsultingHold;
use App\Services\VideoConsultingAvailabilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VideoConsultingAvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeReaderWithRule(string $start = '09:00', string $end = '11:00'): array
    {
        $reader = User::factory()->create(['role' => 'reader']);
        $day = Carbon::now()->addDays(14)->startOfDay();

        ReaderAvailabilityRule::create([
            'reader_id'   => $reader->id,
            'day_of_week' => $day->dayOfWeek,
            'start_time'  => $start,
            'end_time'    => $end,
            'enabled'     => true,
        ]);

        return [$reader, $day];
    }

    public function test_generates_back_to_back_slots_within_the_availability_window(): void
    {
        [$reader, $day] = $this->makeReaderWithRule();
        $duration = VideoConsultingDuration::create([
            'minutes' => 30, 'label' => '30 Minutes', 'base_rate' => 20, 'min_reader_rate' => 10, 'max_reader_rate' => 100,
        ]);

        $slots = (new VideoConsultingAvailabilityService())->generateSlots(
            $reader->id, $duration->id, $day->copy(), $day->copy()->endOfDay()
        );

        $starts = array_map(fn ($s) => $s['start']->format('H:i'), $slots);
        $this->assertSame(['09:00', '09:30', '10:00', '10:30'], $starts);
    }

    public function test_blackout_carves_out_the_overlapping_portion_of_the_window(): void
    {
        [$reader, $day] = $this->makeReaderWithRule();
        $duration = VideoConsultingDuration::create([
            'minutes' => 30, 'label' => '30 Minutes', 'base_rate' => 20, 'min_reader_rate' => 10, 'max_reader_rate' => 100,
        ]);

        ReaderBlackoutDate::create([
            'reader_id' => $reader->id,
            'start_at'  => $day->copy()->setTime(10, 0),
            'end_at'    => $day->copy()->setTime(10, 30),
        ]);

        $slots = (new VideoConsultingAvailabilityService())->generateSlots(
            $reader->id, $duration->id, $day->copy(), $day->copy()->endOfDay()
        );

        $starts = array_map(fn ($s) => $s['start']->format('H:i'), $slots);
        $this->assertSame(['09:00', '09:30', '10:30'], $starts);
    }

    public function test_an_active_hold_removes_that_exact_slot(): void
    {
        [$reader, $day] = $this->makeReaderWithRule();
        $duration = VideoConsultingDuration::create([
            'minutes' => 30, 'label' => '30 Minutes', 'base_rate' => 20, 'min_reader_rate' => 10, 'max_reader_rate' => 100,
        ]);

        VideoConsultingHold::create([
            'reader_id'   => $reader->id,
            'duration_id' => $duration->id,
            'slot_start'  => $day->copy()->setTime(9, 30),
            'slot_end'    => $day->copy()->setTime(10, 0),
            'hold_token'  => 'test-token',
            'status'      => VideoConsultingHold::STATUS_HELD,
            'expires_at'  => now()->addMinutes(20),
        ]);

        $slots = (new VideoConsultingAvailabilityService())->generateSlots(
            $reader->id, $duration->id, $day->copy(), $day->copy()->endOfDay()
        );

        $starts = array_map(fn ($s) => $s['start']->format('H:i'), $slots);
        $this->assertSame(['09:00', '10:00', '10:30'], $starts);
    }

    public function test_an_expired_hold_does_not_block_the_slot(): void
    {
        [$reader, $day] = $this->makeReaderWithRule();
        $duration = VideoConsultingDuration::create([
            'minutes' => 30, 'label' => '30 Minutes', 'base_rate' => 20, 'min_reader_rate' => 10, 'max_reader_rate' => 100,
        ]);

        VideoConsultingHold::create([
            'reader_id'   => $reader->id,
            'duration_id' => $duration->id,
            'slot_start'  => $day->copy()->setTime(9, 30),
            'slot_end'    => $day->copy()->setTime(10, 0),
            'hold_token'  => 'test-token-expired',
            'status'      => VideoConsultingHold::STATUS_HELD,
            'expires_at'  => now()->subMinute(),
        ]);

        $slots = (new VideoConsultingAvailabilityService())->generateSlots(
            $reader->id, $duration->id, $day->copy(), $day->copy()->endOfDay()
        );

        $starts = array_map(fn ($s) => $s['start']->format('H:i'), $slots);
        $this->assertContains('09:30', $starts);
    }

    public function test_is_slot_available_matches_generate_slots(): void
    {
        [$reader, $day] = $this->makeReaderWithRule();
        $duration = VideoConsultingDuration::create([
            'minutes' => 30, 'label' => '30 Minutes', 'base_rate' => 20, 'min_reader_rate' => 10, 'max_reader_rate' => 100,
        ]);

        $service = new VideoConsultingAvailabilityService();

        $this->assertTrue($service->isSlotAvailable($reader->id, $duration->id, $day->copy()->setTime(9, 0)));
        $this->assertFalse($service->isSlotAvailable($reader->id, $duration->id, $day->copy()->setTime(14, 0)));
    }
}
