<?php

// v1.1 — 2026-05-26 | Add create/edit/delete CRUD
// v1.0 — 2026-05-25 | Order log — one row per WooCommerce order, admin only

namespace App\Http\Controllers;

use App\Models\OrderRevenue;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

    public function create()
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        return view('order-log.form', ['order' => null]);
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $data = $this->validated($request);

        OrderRevenue::create($data);

        return redirect()->route('order-log.index', ['period' => 'all'])
            ->with('success', 'Order created.');
    }

    public function edit(OrderRevenue $orderLog)
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        return view('order-log.form', ['order' => $orderLog]);
    }

    public function update(Request $request, OrderRevenue $orderLog)
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $data = $this->validated($request, $orderLog->id);

        $orderLog->update($data);

        return redirect()->route('order-log.index', ['period' => 'all'])
            ->with('success', 'Order updated.');
    }

    public function destroy(OrderRevenue $orderLog)
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $orderLog->delete();

        return back()->with('success', 'Order deleted.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'ordered_at'       => ['required', 'date'],
            'order_number'     => ['required', 'string', 'max:64', Rule::unique('order_revenues', 'order_number')->ignore($ignoreId)],
            'invoice_number'   => ['nullable', 'string', 'max:100'],
            'customer_name'    => ['nullable', 'string', 'max:255'],
            'customer_email'   => ['nullable', 'email', 'max:255'],
            'customer_phone'   => ['nullable', 'string', 'max:50'],
            'customer_address' => ['nullable', 'string'],
            'script_title'     => ['nullable', 'string', 'max:500'],
            'services_purchased' => ['nullable', 'string'],
            'ticket_summary'   => ['nullable', 'string', 'max:500'],
            'sku'              => ['nullable', 'string', 'max:255'],
            'order_quantity'   => ['nullable', 'integer', 'min:0'],
            'order_total'      => ['required', 'numeric'],
            'discount_amount'  => ['nullable', 'numeric'],
            'cog_reader'       => ['nullable', 'numeric'],
            'cog_processing'   => ['nullable', 'numeric'],
            'cog_precommission'=> ['nullable', 'numeric'],
            'cog_commission'   => ['nullable', 'numeric'],
            'cog_total'        => ['nullable', 'numeric'],
            'net_revenue'      => ['nullable', 'numeric'],
            'payment_method'   => ['nullable', 'string', 'max:64'],
            'coupon_code'      => ['nullable', 'string', 'max:128'],
            'staff_member'     => ['nullable', 'string', 'max:128'],
            'skip_commission'  => ['boolean'],
        ]);

        // Default NOT NULL money columns to 0 if omitted
        foreach (['discount_amount', 'cog_reader', 'cog_processing', 'cog_precommission',
                  'cog_commission', 'cog_total', 'net_revenue'] as $col) {
            $data[$col] = $data[$col] ?? 0;
        }

        $data['skip_commission'] = $request->boolean('skip_commission');

        return $data;
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
