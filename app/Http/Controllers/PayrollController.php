<?php

// v1.0 — 2026-05-31 | Admin payroll dashboard — weekly payout history split by 1099 vs non-1099

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Support\PayPeriod;
use Carbon\Carbon;

class PayrollController extends Controller
{
    public static array $PERIODS = [
        'this_week'  => 'This Week',
        'last_week'  => 'Last Week',
        'this_month' => 'This Month',
        'last_month' => 'Last Month',
        'last_30'    => 'Last 30 Days',
        'last_60'    => 'Last 60 Days',
        'last_90'    => 'Last 90 Days',
        'this_year'  => 'This Year',
        'last_year'  => 'Last Year',
        'all_time'   => 'All Time',
    ];

    public function index()
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $period = request()->input('period', 'all_time');
        if (! array_key_exists($period, self::$PERIODS)) {
            $period = 'all_time';
        }

        [$start, $end] = $this->dateRange($period);

        // Paid completed SR assignments within the period (filtered by reader_paid_at)
        $assignments = Assignment::with(['assignedReader.readerProfile'])
            ->where('status', Assignment::STATUS_COMPLETED)
            ->where('vendor', 'sr')
            ->whereNotNull('reader_paid_at')
            ->when($start, fn($q) => $q->where('reader_paid_at', '>=', $start))
            ->when($end,   fn($q) => $q->where('reader_paid_at', '<=', $end))
            ->orderByDesc('reader_paid_at')
            ->get();

        // Group into weekly pay periods keyed by period_start
        $periodMap = [];

        foreach ($assignments as $a) {
            $periodStart = PayPeriod::start($a->reader_paid_at);
            $key         = $periodStart->toDateString();

            if (! isset($periodMap[$key])) {
                $periodMap[$key] = [
                    'period_start' => $periodStart,
                    'period_end'   => PayPeriod::end($periodStart),
                    'pay_1099'     => 0.0,
                    'pay_non_1099' => 0.0,
                    'count_1099'   => 0,
                    'count_non'    => 0,
                ];
            }

            $is1099 = (bool) ($a->assignedReader?->readerProfile?->is_1099 ?? false);
            $rate   = (float) $a->pay_rate;

            if ($is1099) {
                $periodMap[$key]['pay_1099']   += $rate;
                $periodMap[$key]['count_1099'] += 1;
            } else {
                $periodMap[$key]['pay_non_1099'] += $rate;
                $periodMap[$key]['count_non']    += 1;
            }
        }

        // Sort newest period first
        krsort($periodMap);
        $periods = array_values($periodMap);

        $totals = [
            'pay_1099'     => array_sum(array_column($periods, 'pay_1099')),
            'pay_non_1099' => array_sum(array_column($periods, 'pay_non_1099')),
        ];
        $totals['total'] = $totals['pay_1099'] + $totals['pay_non_1099'];

        return view('payroll.index', compact('periods', 'totals', 'period'));
    }

    private function dateRange(string $period): array
    {
        $tz  = config('app.timezone', 'America/Los_Angeles');
        $now = Carbon::now($tz);

        return match ($period) {
            'this_week'  => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'last_week'  => [$now->copy()->subWeek()->startOfWeek(), $now->copy()->subWeek()->endOfWeek()],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'last_month' => [$now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth()],
            'last_30'    => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            'last_60'    => [$now->copy()->subDays(59)->startOfDay(), $now->copy()->endOfDay()],
            'last_90'    => [$now->copy()->subDays(89)->startOfDay(), $now->copy()->endOfDay()],
            'this_year'  => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'last_year'  => [$now->copy()->subYear()->startOfYear(), $now->copy()->subYear()->endOfYear()],
            'all_time'   => [null, null],
        };
    }
}
