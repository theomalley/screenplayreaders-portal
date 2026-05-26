<?php

// v1.0 — 2026-05-25 | Order log — one row per WooCommerce order, admin only

namespace App\Http\Controllers;

use App\Models\OrderRevenue;
use Carbon\Carbon;
use Illuminate\Http\Request;

class OrderLogController extends Controller
{
    private static array $PERIODS = [
        'today'      => 'Today',
        'yesterday'  => 'Yesterday',
        'last_7'     => 'Last 7 Days',
        'last_30'    => 'Last 30 Days',
        'last_90'    => 'Last 90 Days',
        'this_month' => 'This Month',
        'last_month' => 'Last Month',
        'this_year'  => 'This Year',
        'last_year'  => 'Last Year',
        'last_6m'    => 'Last 6 Months',
        'last_12m'   => 'Last 12 Months',
        'all'        => 'All Time',
    ];

    public function index(Request $request)
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $period = $request->input('period', 'last_30');
        if (! array_key_exists($period, self::$PERIODS)) {
            $period = 'last_30';
        }

        $q = trim((string) $request->input('q', ''));

        $query = OrderRevenue::orderByDesc('ordered_at');

        if ($period !== 'all') {
            [$start, $end] = $this->dateRange($period);
            $query->whereBetween('ordered_at', [$start, $end]);
        }

        if ($q !== '') {
            $query->where(function ($sq) use ($q) {
                $sq->where('order_number', 'like', "%{$q}%")
                   ->orWhere('customer_name', 'like', "%{$q}%")
                   ->orWhere('customer_email', 'like', "%{$q}%")
                   ->orWhere('invoice_number', 'like', "%{$q}%");
            });
        }

        $orders = $query->paginate(50)->withQueryString();

        return view('order-log.index', [
            'orders'  => $orders,
            'period'  => $period,
            'periods' => self::$PERIODS,
            'q'       => $q,
        ]);
    }

    private function dateRange(string $period): array
    {
        $tz = config('app.timezone', 'UTC');
        $now = Carbon::now($tz);

        return match ($period) {
            'today'      => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'yesterday'  => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            'last_7'     => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            'last_30'    => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            'last_90'    => [$now->copy()->subDays(89)->startOfDay(), $now->copy()->endOfDay()],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'last_month' => [$now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth()],
            'this_year'  => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'last_year'  => [$now->copy()->subYear()->startOfYear(), $now->copy()->subYear()->endOfYear()],
            'last_6m'    => [$now->copy()->subMonths(6)->startOfDay(), $now->copy()->endOfDay()],
            'last_12m'   => [$now->copy()->subMonths(12)->startOfDay(), $now->copy()->endOfDay()],
            default      => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
        };
    }
}
