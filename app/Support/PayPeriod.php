<?php

// Pay periods run Saturday 8:00 AM → following Saturday 7:00 AM (America/Los_Angeles).
// Payments go out Saturday morning ~8AM, so the 7AM cutoff ensures same-morning
// completions roll into the *next* cycle rather than the one about to close.

namespace App\Support;

use Carbon\Carbon;

class PayPeriod
{
    const TZ = 'America/Los_Angeles';

    /**
     * Return the start (Saturday 8AM LA) of the pay period that $dt falls in.
     */
    public static function start(Carbon $dt): Carbon
    {
        $la = $dt->copy()->setTimezone(self::TZ);

        // Days since last Saturday: Sun=1, Mon=2, Tue=3, Wed=4, Thu=5, Fri=6, Sat=0
        $daysSinceSat = ($la->dayOfWeek + 1) % 7;

        $saturday = $la->copy()->subDays($daysSinceSat)->setTime(8, 0, 0);

        // If $la is before 8AM on that Saturday, the previous Saturday is the period start
        if ($la < $saturday) {
            $saturday->subWeek();
        }

        return $saturday;
    }

    /**
     * Return the end (following Saturday 7AM LA) of the pay period that starts at $periodStart.
     */
    public static function end(Carbon $periodStart): Carbon
    {
        return $periodStart->copy()->addWeek()->subHour();
    }

    /**
     * Return [start, end] for the period $dt falls in.
     */
    public static function bounds(Carbon $dt): array
    {
        $start = self::start($dt);
        return [$start, self::end($start)];
    }

    /**
     * Return [start, end] for the current pay period.
     */
    public static function current(): array
    {
        return self::bounds(Carbon::now(self::TZ));
    }

    /**
     * Human-readable label for a period starting at $start.
     * e.g. "May 24 – May 30"
     */
    public static function label(Carbon $start): string
    {
        $end = self::end($start);
        if ($start->month === $end->month) {
            return $start->format('M j') . '–' . $end->format('j');
        }
        return $start->format('M j') . ' – ' . $end->format('M j');
    }
}
