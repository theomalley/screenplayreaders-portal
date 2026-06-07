<?php

// Pay periods run on the configured start day/time (default: Saturday 8:00 AM America/Los_Angeles).
// Start and end are independently configurable via Settings (period_start_day/time, period_end_day/time).
// Default end: Saturday 7:00 AM — one hour before next period opens.

namespace App\Support;

use App\Models\Setting;
use Carbon\Carbon;

class PayPeriod
{
    const TZ = 'America/Los_Angeles';

    /**
     * Read payout schedule config from settings, with hardcoded fallbacks so this
     * works even before the settings table exists (e.g. during fresh migrations).
     */
    private static function config(): array
    {
        try {
            return array_merge(Setting::getPayoutSchedule(), Setting::getPayPeriod());
        } catch (\Throwable $e) {
            return [
                'frequency'  => 'weekly', 'day' => 6, 'time' => '08:00', 'override' => null, 'anchor' => null,
                'start_day'  => 6, 'start_time' => '08:00',
                'end_day'    => 6, 'end_time'   => '07:00',
            ];
        }
    }

    /**
     * Return the start of the pay period that $dt falls in, using the configured
     * payout day and time. For biweekly schedules, snaps to the anchor-aligned cycle.
     */
    public static function start(Carbon $dt): Carbon
    {
        $cfg = self::config();
        [$h, $m] = array_map('intval', explode(':', $cfg['start_time']));
        $day = $cfg['start_day']; // 0=Sun … 6=Sat

        $la = $dt->copy()->setTimezone(self::TZ);

        // Days since the most recent occurrence of the configured payout day
        $daysSince = ($la->dayOfWeek - $day + 7) % 7;
        $candidate = $la->copy()->subDays($daysSince)->setTime($h, $m, 0);

        // If $la is before the candidate time, step back one week
        if ($la->lt($candidate)) {
            $candidate->subWeek();
        }

        // For biweekly: ensure alignment with the stored anchor date
        if ($cfg['frequency'] === 'biweekly' && $cfg['anchor']) {
            $anchor     = Carbon::parse($cfg['anchor'], self::TZ)->setTime($h, $m, 0);
            $weeksDiff  = (int) $anchor->diffInWeeks($candidate);
            if ($weeksDiff % 2 !== 0) {
                $candidate->subWeek();
            }
        }

        return $candidate;
    }

    /**
     * Return the end of the pay period that starts at $periodStart.
     * Weekly: 1 week later minus 1 hour. Biweekly: 2 weeks later minus 1 hour.
     */
    public static function end(Carbon $periodStart): Carbon
    {
        $cfg   = self::config();
        $weeks = $cfg['frequency'] === 'biweekly' ? 2 : 1;
        [$endH, $endM] = array_map('intval', explode(':', $cfg['end_time']));
        $endDay = $cfg['end_day'];

        // The next period's start defines the cycle length; work backward to end_day at end_time.
        $nextStart = $periodStart->copy()->setTimezone(self::TZ)->addWeeks($weeks);
        $daysBack  = ($nextStart->dayOfWeek - $endDay + 7) % 7;
        $candidate = $nextStart->copy()->subDays($daysBack)->setTime($endH, $endM, 0);

        // If end_day == next-start day and end_time >= start_time, the candidate would land
        // on or after nextStart — push it back one week so it stays within the current period.
        if ($candidate->gte($nextStart)) {
            $candidate->subWeek();
        }

        return $candidate;
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
     * Return the next scheduled payout date as a Carbon instance (America/Los_Angeles).
     * If an admin override is set and is still in the future, that date is returned instead.
     */
    public static function nextPayoutDate(): Carbon
    {
        $cfg = self::config();
        [$h, $m] = array_map('intval', explode(':', $cfg['start_time']));

        // Return the override if it's set and hasn't passed yet
        if ($cfg['override']) {
            $override = Carbon::parse($cfg['override'], self::TZ)->setTime($h, $m, 0);
            if ($override->isFuture()) {
                return $override;
            }
        }

        // Next payout = start of the current period + period length
        [$periodStart] = self::current();
        $weeks = $cfg['frequency'] === 'biweekly' ? 2 : 1;
        return $periodStart->copy()->addWeeks($weeks);
    }

    /**
     * Human-readable label for a period starting at $start.
     * e.g. "May 24 – May 30" or "May 24 – Jun 6" for biweekly
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
