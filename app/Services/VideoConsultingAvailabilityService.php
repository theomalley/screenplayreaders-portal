<?php

// v1.0 — 2026-11-07 | Video consulting Phase 1 — turns a reader's recurring
//                      ReaderAvailabilityRule windows, minus ReaderBlackoutDate overlaps and
//                      active VideoConsultingHold slots, into actual bookable start times.
//                      Slots are generated back-to-back from each window's start (no partial
//                      slot spillover past the window end) — simplest default given no fixed
//                      booking-grid granularity was specified. App-timezone only; no
//                      multi-timezone support yet.

namespace App\Services;

use App\Models\ReaderAvailabilityRule;
use App\Models\ReaderBlackoutDate;
use App\Models\VideoConsultingDuration;
use App\Models\VideoConsultingHold;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

class VideoConsultingAvailabilityService
{
    /**
     * Re-validates a single candidate slot server-side — never trust that a client called
     * generateSlots() first. Used by VideoConsultingBookingController::createHold().
     */
    public function isSlotAvailable(int $readerId, int $durationId, Carbon $slotStart): bool
    {
        $slots = $this->generateSlots($readerId, $durationId, $slotStart->copy(), $slotStart->copy()->addMinute());

        foreach ($slots as $slot) {
            if ($slot['start']->equalTo($slotStart)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array{start: Carbon, end: Carbon}> sorted by start
     */
    public function generateSlots(int $readerId, int $durationId, Carbon $from, Carbon $to): array
    {
        $duration = VideoConsultingDuration::active()->find($durationId);
        if (! $duration) {
            return [];
        }

        $minutes = (int) $duration->minutes;

        $rulesByDay = ReaderAvailabilityRule::where('reader_id', $readerId)
            ->enabled()
            ->get()
            ->groupBy('day_of_week');

        $blackouts = ReaderBlackoutDate::where('reader_id', $readerId)
            ->where('start_at', '<', $to)
            ->where('end_at', '>', $from)
            ->get();

        // Active holds block their exact slot_start — not yet a real booking (that's Phase 6,
        // once order-completed webhook -> Assignment conversion exists), but a hold in progress
        // must not be offered to a second customer.
        $heldStarts = VideoConsultingHold::where('reader_id', $readerId)
            ->where('status', VideoConsultingHold::STATUS_HELD)
            ->where('expires_at', '>', now())
            ->whereBetween('slot_start', [$from, $to])
            ->pluck('slot_start')
            ->map(fn (Carbon $start) => $start->timestamp)
            ->all();

        $slots = [];
        $day = $from->copy()->startOfDay();
        $lastDay = $to->copy()->startOfDay();

        while ($day->lte($lastDay)) {
            $rules = $rulesByDay->get($day->dayOfWeek, collect());

            foreach ($rules as $rule) {
                $windowStart = $day->copy()->setTimeFromTimeString($rule->start_time);
                $windowEnd = $day->copy()->setTimeFromTimeString($rule->end_time);

                foreach ($this->subtractBlackouts($windowStart, $windowEnd, $blackouts) as [$subStart, $subEnd]) {
                    $slotStart = $subStart->copy();

                    while ($slotStart->copy()->addMinutes($minutes)->lte($subEnd)) {
                        $slotEnd = $slotStart->copy()->addMinutes($minutes);

                        if (
                            $slotStart->gte($from)
                            && $slotStart->lte($to)
                            && $slotStart->isFuture()
                            && ! in_array($slotStart->timestamp, $heldStarts, true)
                        ) {
                            $slots[] = ['start' => $slotStart->copy(), 'end' => $slotEnd->copy()];
                        }

                        $slotStart->addMinutes($minutes);
                    }
                }
            }

            $day->addDay();
        }

        usort($slots, fn ($a, $b) => $a['start']->timestamp <=> $b['start']->timestamp);

        return $slots;
    }

    /**
     * Splits [$windowStart, $windowEnd] into the sub-windows that remain once every
     * overlapping blackout has been carved out.
     *
     * @return array<int, array{0: Carbon, 1: Carbon}>
     */
    private function subtractBlackouts(Carbon $windowStart, Carbon $windowEnd, $blackouts): array
    {
        $windows = [[CarbonImmutable::make($windowStart), CarbonImmutable::make($windowEnd)]];

        foreach ($blackouts as $blackout) {
            $blackoutStart = CarbonImmutable::make($blackout->start_at);
            $blackoutEnd = CarbonImmutable::make($blackout->end_at);

            $next = [];
            foreach ($windows as [$start, $end]) {
                if ($blackoutEnd->lte($start) || $blackoutStart->gte($end)) {
                    $next[] = [$start, $end];
                    continue;
                }

                if ($blackoutStart->gt($start)) {
                    $next[] = [$start, $blackoutStart];
                }
                if ($blackoutEnd->lt($end)) {
                    $next[] = [$blackoutEnd, $end];
                }
            }
            $windows = $next;
        }

        return array_map(
            fn (array $pair) => [Carbon::make($pair[0]), Carbon::make($pair[1])],
            $windows
        );
    }
}
