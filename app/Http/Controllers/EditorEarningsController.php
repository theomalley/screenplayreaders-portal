<?php

// v1.0 — 2026-06-02 | Editor earnings dashboard — commission and adjustment history with Chart.js

namespace App\Http\Controllers;

use App\Models\EditorPayAdjustment;
use App\Models\OrderRevenue;
use Carbon\Carbon;

class EditorEarningsController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        abort_unless($user->isAdminOrEditor(), 403);

        $period = request()->input('period', 'this_month');
        if (! array_key_exists($period, RevenueController::$PERIODS)) {
            $period = 'this_month';
        }

        [$start, $end] = $this->dateRange($period);

        $ordersQuery = OrderRevenue::where('cog_commission', '>', 0);
        if ($start) $ordersQuery->where('ordered_at', '>=', $start);
        if ($end)   $ordersQuery->where('ordered_at', '<=', $end);
        $orders = $ordersQuery->orderByDesc('ordered_at')->get();

        $adjustmentsQuery = EditorPayAdjustment::with('addedBy')
            ->where('user_id', $user->id);
        if ($start) $adjustmentsQuery->where('created_at', '>=', $start);
        if ($end)   $adjustmentsQuery->where('created_at', '<=', $end);
        $adjustments = $adjustmentsQuery->orderByDesc('created_at')->get();

        $commissionEarned  = (float) $orders->sum('cog_commission');
        $commissionPaid    = (float) $orders->whereNotNull('editor_paid_at')->sum('cog_commission');
        $commissionPending = $commissionEarned - $commissionPaid;
        $adjustmentTotal   = (float) $adjustments->sum('amount');

        $totals = [
            'commission_earned'  => $commissionEarned,
            'commission_paid'    => $commissionPaid,
            'commission_pending' => $commissionPending,
            'adjustment_total'   => $adjustmentTotal,
            'total_pending'      => $commissionPending + $adjustmentTotal,
            'order_count'        => $orders->count(),
        ];

        $chartData = $this->buildChartData($orders, $period);

        return view('editor-earnings.index', compact('orders', 'adjustments', 'totals', 'period', 'chartData'));
    }

    private function dateRange(string $period): array
    {
        $tz  = config('app.timezone', 'America/Los_Angeles');
        $now = Carbon::now($tz);

        return match ($period) {
            'today'      => [$now->copy()->startOfDay(),                    $now->copy()->endOfDay()],
            'yesterday'  => [$now->copy()->subDay()->startOfDay(),           $now->copy()->subDay()->endOfDay()],
            'this_week'  => [$now->copy()->startOfWeek(),                    $now->copy()->endOfWeek()],
            'last_week'  => [$now->copy()->subWeek()->startOfWeek(),         $now->copy()->subWeek()->endOfWeek()],
            'this_month' => [$now->copy()->startOfMonth(),                   $now->copy()->endOfMonth()],
            'last_month' => [$now->copy()->subMonth()->startOfMonth(),       $now->copy()->subMonth()->endOfMonth()],
            'last_30'    => [$now->copy()->subDays(29)->startOfDay(),        $now->copy()->endOfDay()],
            'last_60'    => [$now->copy()->subDays(59)->startOfDay(),        $now->copy()->endOfDay()],
            'last_90'    => [$now->copy()->subDays(89)->startOfDay(),        $now->copy()->endOfDay()],
            'this_year'  => [$now->copy()->startOfYear(),                    $now->copy()->endOfYear()],
            'last_year'  => [$now->copy()->subYear()->startOfYear(),         $now->copy()->subYear()->endOfYear()],
            'all_time'   => [null, null],
        };
    }

    private function buildChartData($orders, string $period): array
    {
        if ($orders->isEmpty()) {
            return ['labels' => [], 'earned' => [], 'paid' => []];
        }

        $bucketByMonth = in_array($period, ['this_year', 'last_year', 'all_time', 'last_90', 'last_60']);

        $buckets = [];
        foreach ($orders as $o) {
            $key = $bucketByMonth
                ? $o->ordered_at->format('Y-m')
                : $o->ordered_at->format('Y-m-d');
            $buckets[$key]['earned'] = ($buckets[$key]['earned'] ?? 0) + (float) $o->cog_commission;
            $buckets[$key]['paid']   = ($buckets[$key]['paid']   ?? 0) + ($o->editor_paid_at ? (float) $o->cog_commission : 0);
        }

        ksort($buckets);

        $labels = $earned = $paid = [];
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
