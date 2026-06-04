<?php

// v1.1 — 2026-06-04 | By-customer / by-client revenue breakdown
// v1.0 — 2026-05-25 | Admin-only revenue dashboard — time-period aggregates and Chart.js data

namespace App\Http\Controllers;

use App\Models\OrderRevenue;
use App\Support\Permission;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RevenueController extends Controller
{
    public static array $PERIODS = [
        'today'      => 'Today',
        'yesterday'  => 'Yesterday',
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

        $period = request()->input('period', 'this_month');
        if (! array_key_exists($period, self::$PERIODS)) {
            $period = 'this_month';
        }

        [$start, $end] = $this->dateRange($period);

        $query = OrderRevenue::when($start, fn($q) => $q->where('ordered_at', '>=', $start))
                             ->when($end,   fn($q) => $q->where('ordered_at', '<=', $end));

        $orders = $query->orderByDesc('ordered_at')->get();

        $totals = [
            'gross'      => $orders->sum('order_total'),
            'discount'   => $orders->sum('discount_amount'),
            'cog_reader' => $orders->sum('cog_reader'),
            'cog_proc'   => $orders->sum('cog_processing'),
            'cog_comm'   => $orders->sum('cog_commission'),
            'cog_total'  => $orders->sum('cog_total'),
            'net'        => $orders->sum('net_revenue'),
            'count'      => $orders->count(),
        ];

        // Chart data — daily net/gross bucketed within the selected period
        $chartData = $this->buildChartData($orders, $start, $end, $period);

        return view('revenue.index', compact('orders', 'totals', 'period', 'chartData'));
    }

    public function byCustomer()
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $period = request()->input('period', 'this_month');
        if (! array_key_exists($period, self::$PERIODS)) {
            $period = 'this_month';
        }

        [$start, $end] = $this->dateRange($period);

        $baseQuery = OrderRevenue::when($start, fn ($q) => $q->where('ordered_at', '>=', $start))
                                  ->when($end,   fn ($q) => $q->where('ordered_at', '<=', $end));

        // Per WooCommerce customer (by email)
        $byCustomer = (clone $baseQuery)
            ->selectRaw('customer_email, MAX(customer_name) as customer_name, COUNT(*) as order_count,
                         SUM(order_total) as gross, SUM(discount_amount) as discount,
                         SUM(net_revenue) as net')
            ->whereNotNull('customer_email')
            ->where('customer_email', '!=', '')
            ->groupBy('customer_email')
            ->orderByDesc('net')
            ->get();

        // Per Client (joined through assignments)
        $byClient = DB::table('order_revenues as r')
            ->join('assignments as a', 'a.order_number', '=', 'r.order_number')
            ->join('clients as c', 'c.id', '=', 'a.client_id')
            ->when($start, fn ($q) => $q->where('r.ordered_at', '>=', $start))
            ->when($end,   fn ($q) => $q->where('r.ordered_at', '<=', $end))
            ->selectRaw('c.id as client_id, c.name as client_name,
                         COUNT(DISTINCT r.order_number) as order_count,
                         SUM(r.order_total) as gross, SUM(r.discount_amount) as discount,
                         SUM(r.net_revenue) as net')
            ->groupBy('c.id', 'c.name')
            ->orderByDesc('net')
            ->get();

        return view('revenue.by-customer', compact('byCustomer', 'byClient', 'period'));
    }

    private function dateRange(string $period): array
    {
        $tz = config('app.timezone', 'America/Los_Angeles');
        $now = Carbon::now($tz);

        return match ($period) {
            'today'      => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'yesterday'  => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
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

    private function buildChartData($orders, $start, $end, string $period): array
    {
        if ($orders->isEmpty()) {
            return ['labels' => [], 'gross' => [], 'net' => []];
        }

        // For multi-month or all_time periods, bucket by month; otherwise by day
        $bucketByMonth = in_array($period, ['this_year', 'last_year', 'all_time', 'last_90', 'last_60']);

        $buckets = [];
        foreach ($orders as $order) {
            $key = $bucketByMonth
                ? $order->ordered_at->format('Y-m')
                : $order->ordered_at->format('Y-m-d');
            $buckets[$key]['gross'] = ($buckets[$key]['gross'] ?? 0) + (float) $order->order_total;
            $buckets[$key]['net']   = ($buckets[$key]['net']   ?? 0) + (float) $order->net_revenue;
        }

        ksort($buckets);

        $labels = [];
        $gross  = [];
        $net    = [];

        foreach ($buckets as $key => $vals) {
            $labels[] = $bucketByMonth
                ? Carbon::createFromFormat('Y-m', $key)->format('M Y')
                : Carbon::createFromFormat('Y-m-d', $key)->format('M j');
            $gross[]  = round($vals['gross'], 2);
            $net[]    = round($vals['net'], 2);
        }

        return compact('labels', 'gross', 'net');
    }
}
