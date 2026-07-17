<?php

// v1.4 — 2026-06-23 | Add bulkDestroy() for multi-select delete
// v1.3 — 2026-06-23 | Unified order log: open index to editors with admin-configurable filters
//                      (hide $0 / woo / invoice orders, block by product ID, column visibility).
//                      Admins see all orders unfiltered. WC orders are clickable to detail view.
// v1.2 — 2026-05-26 | Add invoicePdf(): generate and stream a PDF invoice for any WooCommerce order
// v1.1 — 2026-05-26 | Add create/edit/delete CRUD
// v1.0 — 2026-05-25 | Order log — one row per WooCommerce order, admin only

namespace App\Http\Controllers;

use App\Models\OrderRevenue;
use App\Models\Setting;
use App\Models\User;
use App\Services\GoogleDocsService;
use App\Services\InvoiceService;
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
        abort_unless(auth()->user()?->isAdminOrEditor(), 403);

        $isAdmin = auth()->user()->isAdmin();
        $period  = $request->input('period', 'last_30');
        if (! array_key_exists($period, self::$PERIODS)) {
            $period = 'last_30';
        }

        $q = trim((string) $request->input('q', ''));

        $query = OrderRevenue::query()->with('editor.editorProfile')->orderByDesc('ordered_at');

        // Admins see everything; editors get admin-configured filters.
        $editorSettings  = null;
        $hiddenColumns   = [];
        if (! $isAdmin) {
            $editorSettings = Setting::getOrderLogEditorSettings();
            $hiddenColumns  = $editorSettings['hidden_columns'];

            if ($editorSettings['hide_zero_dollar']) {
                $query->where('order_total', '>', 0);
            }
            if ($editorSettings['hide_woo_orders']) {
                $query->whereNull('woocommerce_order_id');
            }
            if ($editorSettings['hide_invoice_orders']) {
                $query->where('order_number', 'not like', 'INV-%');
            }

            // Block orders containing specific WooCommerce product IDs.
            $blockedIds = array_filter(array_map('intval', $editorSettings['blocked_product_ids']));
            foreach ($blockedIds as $pid) {
                $query->where(function ($q) use ($pid) {
                    $q->whereNull('line_items_json')
                      ->orWhere(function ($inner) use ($pid) {
                          $inner->where('line_items_json', 'not like', "%\"product_id\":{$pid},%")
                                ->where('line_items_json', 'not like', "%\"product_id\":{$pid}}%");
                      });
                });
            }
        }

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

        $allColumns = Setting::ORDER_LOG_COLUMNS;
        $visibleColumns = $isAdmin
            ? array_keys($allColumns)
            : array_values(array_diff(array_keys($allColumns), $hiddenColumns));

        return view('order-log.index', [
            'orders'         => $orders,
            'period'         => $period,
            'periods'        => self::$PERIODS,
            'q'              => $q,
            'isAdmin'        => $isAdmin,
            'allColumns'     => $allColumns,
            'visibleColumns' => $visibleColumns,
        ]);
    }

    public function create()
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        return view('order-log.form', ['order' => null, 'editors' => $this->editorOptions()]);
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
        return view('order-log.form', ['order' => $orderLog, 'editors' => $this->editorOptions()]);
    }

    private function editorOptions()
    {
        return User::where('role', 'editor')->where('is_test', false)->with('editorProfile')->orderBy('name')->get();
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

    public function bulkDestroy(Request $request)
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $data = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $count = OrderRevenue::whereIn('id', $data['ids'])->delete();

        return back()->with('success', $count . ' order' . ($count === 1 ? '' : 's') . ' deleted.');
    }

    /**
     * Generate and stream a PDF invoice for a WooCommerce order using the invoice Google Doc template.
     * The temp copy is deleted immediately after export — nothing is saved to Drive.
     */
    public function invoicePdf(OrderRevenue $orderLog, GoogleDocsService $docs)
    {
        abort_unless(auth()->user()?->isAdminOrEditor(), 403);

        $srAddress   = Setting::getValue('sr_invoice_address', '');
        $description = $orderLog->ticket_summary ?: $orderLog->services_purchased ?: 'Order #' . $orderLog->order_number;
        $amount      = (float) $orderLog->order_total;

        $addrParts = array_filter([
            $orderLog->customer_address,
        ]);

        $placeholders = [
            '{{SR_ADDRESS}}'    => $srAddress,
            '{{INVOICENUMBER}}' => $orderLog->invoice_number ?: $orderLog->order_number,
            '{{DATE}}'          => now()->format('F j, Y'),
            '{{name}}'          => $orderLog->customer_name ?? '',
            '{{company}}'       => '',
            '{{addressline1}}' => $orderLog->customer_address ?? '',
            '{{addressline2}}' => '',
            '{{notes}}'         => '',
            '{{TOTAL}}'         => number_format($amount, 2),
            '{{URL}}'           => '',
        ];

        for ($i = 1; $i <= 8; $i++) {
            $placeholders["{{service{$i}}}"]    = $i === 1 ? $description : '';
            $placeholders["{{title{$i}}}"]      = '';
            $placeholders["{{price{$i}}}"]      = $i === 1 ? number_format($amount, 2) : '';
            $placeholders["{{qty{$i}}}"]        = $i === 1 ? '1' : '';
            $placeholders["{{FINALPRICE{$i}}}"] = $i === 1 ? number_format($amount, 2) : '';
        }

        $pdfBytes = $docs->generatePdfBytesAndCleanup(InvoiceService::INVOICE_TEMPLATE_ID, $placeholders);

        $filename = 'Invoice-' . preg_replace('/[^A-Za-z0-9\-]/', '-', $orderLog->order_number) . '.pdf';

        return response($pdfBytes, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'private, no-store',
        ]);
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
            'order_quantity'   => ['nullable', 'string', 'max:100'],
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
            'editor_id'        => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', 'editor')],
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
