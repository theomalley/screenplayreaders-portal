<?php

// v1.0 — 2026-11-22 | US federal holidays for the video consulting "Available for video
//                      calls" blackout accordion — computed from the standard OPM rules
//                      (nth-weekday-of-month, or a fixed date shifted to the nearest weekday
//                      when it falls on a weekend) rather than stored as literal dates, so no
//                      yearly maintenance is needed. See ReaderProfile.
//                      video_consulting_holiday_blackouts and
//                      VideoConsultingAvailabilityService::generateSlots().

namespace App\Support;

use Carbon\Carbon;

class FederalHolidays
{
    public const HOLIDAYS = [
        'new_years'    => "New Year's Day",
        'mlk'          => 'Martin Luther King Jr. Day',
        'presidents'   => "Washington's Birthday (Presidents Day)",
        'memorial'     => 'Memorial Day',
        'juneteenth'   => 'Juneteenth',
        'independence' => 'Independence Day',
        'labor'        => 'Labor Day',
        'columbus'     => 'Columbus Day',
        'veterans'     => 'Veterans Day',
        'thanksgiving' => 'Thanksgiving Day',
        'christmas'    => 'Christmas Day',
    ];

    /** @return array<string, Carbon> holiday key => observed date in $year */
    public static function datesForYear(int $year): array
    {
        return [
            'new_years'    => self::observed(Carbon::create($year, 1, 1)),
            'mlk'          => self::nthWeekdayOfMonth($year, 1, Carbon::MONDAY, 3),
            'presidents'   => self::nthWeekdayOfMonth($year, 2, Carbon::MONDAY, 3),
            'memorial'     => self::lastWeekdayOfMonth($year, 5, Carbon::MONDAY),
            'juneteenth'   => self::observed(Carbon::create($year, 6, 19)),
            'independence' => self::observed(Carbon::create($year, 7, 4)),
            'labor'        => self::nthWeekdayOfMonth($year, 9, Carbon::MONDAY, 1),
            'columbus'     => self::nthWeekdayOfMonth($year, 10, Carbon::MONDAY, 2),
            'veterans'     => self::observed(Carbon::create($year, 11, 11)),
            'thanksgiving' => self::nthWeekdayOfMonth($year, 11, Carbon::THURSDAY, 4),
            'christmas'    => self::observed(Carbon::create($year, 12, 25)),
        ];
    }

    /**
     * The holiday key $date falls on, or null. Checks the adjacent years too since a
     * weekend-observed shift can cross Dec 31/Jan 1 (e.g. New Year's Day observed Dec 31).
     */
    public static function keyForDate(Carbon $date): ?string
    {
        $day = $date->copy()->startOfDay();

        foreach ([$day->year - 1, $day->year, $day->year + 1] as $year) {
            foreach (self::datesForYear($year) as $key => $observed) {
                if ($observed->isSameDay($day)) {
                    return $key;
                }
            }
        }

        return null;
    }

    /** Federal observed-date rule: Saturday shifts to the preceding Friday, Sunday to the following Monday. */
    private static function observed(Carbon $date): Carbon
    {
        return match ($date->dayOfWeek) {
            Carbon::SATURDAY => $date->copy()->subDay(),
            Carbon::SUNDAY   => $date->copy()->addDay(),
            default          => $date->copy(),
        };
    }

    private static function nthWeekdayOfMonth(int $year, int $month, int $weekday, int $n): Carbon
    {
        $first = Carbon::create($year, $month, 1);
        $offset = ($weekday - $first->dayOfWeek + 7) % 7;

        return $first->addDays($offset + ($n - 1) * 7);
    }

    private static function lastWeekdayOfMonth(int $year, int $month, int $weekday): Carbon
    {
        $end = Carbon::create($year, $month, 1)->endOfMonth();
        $offset = ($end->dayOfWeek - $weekday + 7) % 7;

        return $end->subDays($offset);
    }
}
