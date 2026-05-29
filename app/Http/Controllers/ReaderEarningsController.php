<?php

// v1.0 — 2026-05-29 | Reader earnings dashboard — time-period aggregates and Chart.js data

namespace App\Http\Controllers;

use App\Models\Assignment;
use Carbon\Carbon;

class ReaderEarningsController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        abort_unless($user->isReader(), 403);

        $period = request()->input('period', 'this_month');
        if (! array_key_exists($period, RevenueController::$PERIODS)) {
            $period = 'this_month';
        }

        [$start, $end] = $this->dateRange($period);

        $query = Assignment::where('assigned_reader_id', $user->id)
            ->where('status', Assignment::STATUS_COMPLETED)
            ->whereNotNull('completed_at');

        if ($start) $query->where('completed_at', '>=', $start);
        if ($end)   $query->where('completed_at', '<=', $end);

        $assignments = $query->orderByDesc('completed_at')->get();

        $totals = [
            'earned'  => (float) $assignments->sum('pay_rate'),
            'paid'    => (float) $assignments->whereNotNull('reader_paid_at')->sum('pay_rate'),
            'pending' => (float) $assignments->whereNull('reader_paid_at')->sum('pay_rate'),
            'count'   => $assignments->count(),
        ];

        $chartData = $this->buildChartData($assignments, $period);

        return view('reader-earnings.index', compact('assignments', 'totals', 'period', 'chartData'));
    }

    private function dateRange(string $period): array
    {
        $tz  = config('app.timezone', 'America/Los_Angeles');
        $now = Carbon::now($tz);

        return match ($period) {
            'today'      => [$now->copy()->startOfDay(),                      $now->copy()->endOfDay()],
            'yesterday'  => [$now->copy()->subDay()->startOfDay(),             $now->copy()->subDay()->endOfDay()],
            'this_week'  => [$now->copy()->startOfWeek(),                      $now->copy()->endOfWeek()],
            'last_week'  => [$now->copy()->subWeek()->startOfWeek(),           $now->copy()->subWeek()->endOfWeek()],
            'this_month' => [$now->copy()->startOfMonth(),                     $now->copy()->endOfMonth()],
            'last_month' => [$now->copy()->subMonth()->startOfMonth(),         $now->copy()->subMonth()->endOfMonth()],
            'last_30'    => [$now->copy()->subDays(29)->startOfDay(),          $now->copy()->endOfDay()],
            'last_60'    => [$now->copy()->subDays(59)->startOfDay(),          $now->copy()->endOfDay()],
            'last_90'    => [$now->copy()->subDays(89)->startOfDay(),          $now->copy()->endOfDay()],
            'this_year'  => [$now->copy()->startOfYear(),                      $now->copy()->endOfYear()],
            'last_year'  => [$now->copy()->subYear()->startOfYear(),           $now->copy()->subYear()->endOfYear()],
            'all_time'   => [null, null],
        };
    }

    private function buildChartData($assignments, string $period): array
    {
        if ($assignments->isEmpty()) {
            return ['labels' => [], 'earned' => [], 'paid' => []];
        }

        $bucketByMonth = in_array($period, ['this_year', 'last_year', 'all_time', 'last_90', 'last_60']);

        $buckets = [];
        foreach ($assignments as $a) {
            $key = $bucketByMonth
                ? $a->completed_at->format('Y-m')
                : $a->completed_at->format('Y-m-d');
            $buckets[$key]['earned'] = ($buckets[$key]['earned'] ?? 0) + (float) $a->pay_rate;
            $buckets[$key]['paid']   = ($buckets[$key]['paid']   ?? 0) + ($a->reader_paid_at ? (float) $a->pay_rate : 0);
        }

        ksort($buckets);

        $labels = [];
        $earned = [];
        $paid   = [];

        foreach ($buckets as $key => $vals) {
            $labels[] = $bucketByMonth
                ? Carbon::createFromFormat('Y-m', $key)->format('M Y')
                : Carbon::createFromFormat('Y-m-d', $key)->format('M j');
            $earned[] = round($vals['earned'], 2);
            $paid[]   = round($vals['paid'],   2);
        }

        return compact('labels', 'earned', 'paid');
    }
}
