<?php

// Pay periods run on the configured payout day/time (default: Saturday 8:00 AM America/Los_Angeles).
// The period end is 1 hour before the next period start so same-morning completions
// roll into the next cycle rather than the one about to close.
// Schedule is admin-configurable via Settings (payout_day, payout_time, payout_frequency).

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
            return Setting::getPayoutSchedule();
        } catch (\Throwable $e) {
            return ['frequency' => 'weekly', 'day' => 6, 'time' => '08:00', 'override' => null, 'anchor' => null];
        }
    }

    /**
     * Return the start of the pay period that $dt falls in, using the configured
     * payout day and time. For biweekly schedules, snaps to the anchor-aligned cycle.
     */
    public static function start(Carbon $dt): Carbon
    {
        $cfg = self::config();
        [$h, $m] = array_map('intval', explode(':', $cfg['time']));
        $day = $cfg['day']; // 0=Sun … 6=Sat

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
        return $periodStart->copy()->addWeeks($weeks)->subHour();
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
        [$h, $m] = array_map('intval', explode(':', $cfg['time']));

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
