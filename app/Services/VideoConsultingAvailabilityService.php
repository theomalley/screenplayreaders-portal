<?php

// v1.4 — 2026-11-23 | Add hasConflictingBooking() — a reschedule (reader proposing candidate
//                      slots, or a customer accepting one) used to commit the new slot_start
//                      with ZERO check against the reader's other holds/bookings, risking a
//                      silent double-book if an unrelated checkout grabbed that time in the
//                      meantime. Deliberately narrower than isSlotAvailable()/generateSlots():
//                      it only checks for an actual overlapping hold (held or converted), not
//                      the reader's standing weekly ReaderAvailabilityRule windows — a
//                      reschedule is the reader manually vouching for a specific one-off time
//                      they know they're free at (same as a reader request can predate/ignore
//                      their own posted hours), so it shouldn't be rejected by a schedule check
//                      that has nothing to do with the actual collision risk being guarded
//                      against. (Using the full isSlotAvailable() here would also require
//                      every reschedule fixture to carry its own ReaderAvailabilityRule rows,
//                      which isn't how VideoConsultingRescheduleTest's accepted-booking fixture
//                      is written — see VideoConsultingRescheduleController/
//                      VideoConsultingRescheduleOfferController for where this is called.)
// v1.3 — 2026-11-22 | generateSlots() now also skips days that fall on a US federal holiday
//                      the reader has blacked out (ReaderProfile.
//                      video_consulting_holiday_blackouts, App\Support\FederalHolidays) — a
//                      reader who's never touched the accordion is blacked out on all of them
//                      by default. Enforced here (not just displayed), so it's authoritative
//                      the same way min_lead_hours already is.
// v1.2 — 2026-11-09 | Video consulting — enforce each reader's own minimum lead time
//                      (ReaderProfile.video_consulting_min_lead_hours) in generateSlots(), not
//                      just "must be in the future" — gives the reader time to actually read
//                      the script and compile notes before the call. Server-side/authoritative;
//                      a customer can never see or pick a slot sooner than this regardless of
//                      what the theme happens to display.
// v1.1 — 2026-11-09 | Video consulting Phase 6 — add availabilitySummaryFor(), a weekly
//                      pattern summary (not real slots) for the checkout upgrade's reader picker.
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
use App\Models\User;
use App\Models\VideoConsultingDuration;
use App\Models\VideoConsultingHold;
use App\Support\FederalHolidays;
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
     * Collapses a reader's weekly ReaderAvailabilityRule windows into a short human string
     * (e.g. "Mon–Fri: 9:00 AM–5:00 PM") for the checkout upgrade's reader picker — a pattern
     * summary, not a list of actual bookable slots (that's generateSlots()/available-slots).
     */
    public function availabilitySummaryFor(int $readerId): string
    {
        $rules = ReaderAvailabilityRule::where('reader_id', $readerId)->enabled()->get();
        if ($rules->isEmpty()) {
            return '';
        }

        $dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

        $daysByWindow = [];
        foreach ($rules as $rule) {
            $window = $this->formatTimeRange($rule->start_time, $rule->end_time);
            $daysByWindow[$window][] = (int) $rule->day_of_week;
        }

        $parts = [];
        foreach ($daysByWindow as $window => $days) {
            $parts[] = $this->collapseDaysToRanges($days, $dayNames) . ': ' . $window;
        }

        return implode('; ', $parts);
    }

    private function formatTimeRange(string $start, string $end): string
    {
        // Carbon::parse (not createFromFormat) since the time column can come back as
        // either "H:i" or "H:i:s" depending on driver/factory input.
        $format = fn (string $time) => Carbon::parse($time)->format('g:i A');

        return $format($start) . '–' . $format($end);
    }

    /** Collapses e.g. [1, 2, 3, 5] into "Mon–Wed, Fri". */
    private function collapseDaysToRanges(array $days, array $dayNames): string
    {
        sort($days);

        $ranges = [];
        $start = $prev = $days[0];

        foreach (array_slice($days, 1) as $day) {
            if ($day === $prev + 1) {
                $prev = $day;
                continue;
            }
            $ranges[] = [$start, $prev];
            $start = $prev = $day;
        }
        $ranges[] = [$start, $prev];

        return implode(', ', array_map(
            fn (array $range) => $range[0] === $range[1]
                ? $dayNames[$range[0]]
                : $dayNames[$range[0]] . '–' . $dayNames[$range[1]],
            $ranges
        ));
    }

    /**
     * True if this reader already has another active hold (held or converted/booked) that
     * overlaps [$slotStart, $slotEnd) — the actual double-booking check a reschedule needs,
     * independent of whether the time falls inside the reader's own posted weekly windows.
     * $excludeHoldId is the hold being rescheduled itself (it still occupies its OLD
     * slot_start/slot_end until the caller commits the new one, so it would never actually
     * self-collide, but excluding it by id keeps this safe regardless of call order).
     */
    public function hasConflictingBooking(int $readerId, Carbon $slotStart, Carbon $slotEnd, ?int $excludeHoldId = null): bool
    {
        return VideoConsultingHold::where('reader_id', $readerId)
            ->when($excludeHoldId !== null, fn ($q) => $q->where('id', '!=', $excludeHoldId))
            ->where(function ($q) {
                $q->where('status', VideoConsultingHold::STATUS_CONVERTED)
                    ->orWhere(function ($q2) {
                        $q2->where('status', VideoConsultingHold::STATUS_HELD)
                            ->where('expires_at', '>', now());
                    });
            })
            ->where('slot_start', '<', $slotEnd)
            ->where('slot_end', '>', $slotStart)
            ->exists();
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

        $readerProfile = User::find($readerId)?->readerProfile;

        $leadHours = (int) ($readerProfile?->video_consulting_min_lead_hours ?? 0);
        $earliestAllowed = now()->addHours($leadHours);

        $holidayBlackouts = $readerProfile?->video_consulting_holiday_blackouts ?? array_keys(FederalHolidays::HOLIDAYS);

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
            $holidayKey = FederalHolidays::keyForDate($day);
            if ($holidayKey !== null && in_array($holidayKey, $holidayBlackouts, true)) {
                $day->addDay();
                continue;
            }

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
                            && $slotStart->gte($earliestAllowed)
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
