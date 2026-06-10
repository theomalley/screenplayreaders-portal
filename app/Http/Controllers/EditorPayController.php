<?php

// v1.3 — 2026-06-10 | Admin can permanently delete a payment-history batch or wipe all history
// v1.2 — 2026-06-10 | Admin can edit/zero-out an order's commission directly from the editor pay view
// v1.1 — 2026-05-28 | Source weekly flat from editor profile; remove global Setting dependency

namespace App\Http\Controllers;

use App\Models\EditorPayAdjustment;
use App\Models\OrderRevenue;
use App\Models\User;
use App\Support\PayPeriod;
use Carbon\Carbon;
use Illuminate\Http\Request;

class EditorPayController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->isAdminOrEditor(), 403);

        // --- UNPAID COMMISSIONS (order_revenues with no editor_paid_at) ---
        $unpaidOrders = OrderRevenue::whereNull('editor_paid_at')
            ->where('skip_commission', false)
            ->where('cog_commission', '>', 0)
            ->orderBy('ordered_at')
            ->get();

        $unpaidAdjustments = EditorPayAdjustment::with('addedBy')
            ->whereNull('editor_paid_at')
            ->orderBy('created_at')
            ->get();

        $orderTotal      = $unpaidOrders->sum(fn($o) => (float) $o->cog_commission);
        $adjustmentTotal = $unpaidAdjustments->sum(fn($a) => (float) $a->amount);
        $totalOwed       = round($orderTotal + $adjustmentTotal, 2);

        // Find the editor user for PayPal email + mark-paid action target
        $editor = User::where('role', 'editor')->whereHas('editorProfile')->first();

        $weeklyFlat = (float) ($editor?->editorProfile?->editor_weekly_flat ?? 0.0);

        // --- HISTORY ---
        $paidOrders = OrderRevenue::whereNotNull('editor_paid_at')
            ->where('cog_commission', '>', 0)
            ->orderByDesc('editor_paid_at')
            ->get();

        $paidAdjustments = EditorPayAdjustment::whereNotNull('editor_paid_at')
            ->orderByDesc('editor_paid_at')
            ->get();

        $historyBatches = $this->buildHistoryBatches($paidOrders, $paidAdjustments);

        $page       = max(1, (int) request()->input('page', 1));
        $perPage    = 10;
        $totalPages = max(1, (int) ceil(count($historyBatches) / $perPage));
        $page       = min($page, $totalPages);
        $history    = array_slice($historyBatches, ($page - 1) * $perPage, $perPage);

        return view('editor-pay.index', compact(
            'unpaidOrders', 'unpaidAdjustments', 'totalOwed', 'editor', 'weeklyFlat',
            'history', 'page', 'totalPages'
        ));
    }

    public function markPaid()
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $now = Carbon::now();

        OrderRevenue::whereNull('editor_paid_at')
            ->where('skip_commission', false)
            ->where('cog_commission', '>', 0)
            ->update(['editor_paid_at' => $now]);

        EditorPayAdjustment::whereNull('editor_paid_at')
            ->update(['editor_paid_at' => $now]);

        return redirect()->route('editor-pay.index')
            ->with('success', 'All pending editor pay marked as paid.');
    }

    public function addAdjustment(Request $request)
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $editor = User::where('role', 'editor')->whereHas('editorProfile')->firstOrFail();

        $validated = $request->validate([
            'amount'      => 'required|numeric|not_in:0',
            'description' => 'required|string|max:255',
        ]);

        EditorPayAdjustment::create([
            'user_id'          => $editor->id,
            'amount'           => $validated['amount'],
            'description'      => $validated['description'],
            'added_by_user_id' => auth()->id(),
        ]);

        $sign = (float) $validated['amount'] >= 0 ? '+' : '';

        return redirect()->route('editor-pay.index')
            ->with('success', "Adjustment {$sign}{$validated['amount']} added.");
    }

    public function updateCommission(Request $request, OrderRevenue $order)
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        abort_unless(is_null($order->editor_paid_at), 422);

        $validated = $request->validate([
            'cog_commission' => 'required|numeric|min:0',
        ]);

        $order->update(['cog_commission' => $validated['cog_commission']]);

        return redirect()->route('editor-pay.index')
            ->with('success', "Commission for order {$order->order_number} updated to $" . number_format((float) $validated['cog_commission'], 2) . '.');
    }

    public function deleteCommission(OrderRevenue $order)
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        abort_unless(is_null($order->editor_paid_at), 422);

        $order->update(['cog_commission' => 0]);

        return redirect()->route('editor-pay.index')
            ->with('success', "Commission for order {$order->order_number} removed.");
    }

    public function deleteHistoryBatch(string $date)
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            abort(404);
        }

        OrderRevenue::whereNotNull('editor_paid_at')
            ->whereDate('editor_paid_at', $date)
            ->update(['cog_commission' => 0, 'editor_paid_at' => null]);

        EditorPayAdjustment::whereNotNull('editor_paid_at')
            ->whereDate('editor_paid_at', $date)
            ->delete();

        return redirect()->route('editor-pay.index')
            ->with('success', "Payment history for {$date} permanently deleted.");
    }

    public function deleteAllHistory()
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        OrderRevenue::whereNotNull('editor_paid_at')
            ->where('cog_commission', '>', 0)
            ->update(['cog_commission' => 0, 'editor_paid_at' => null]);

        EditorPayAdjustment::whereNotNull('editor_paid_at')->delete();

        return redirect()->route('editor-pay.index')
            ->with('success', 'All editor payment history permanently deleted.');
    }

    public function deleteAdjustment(EditorPayAdjustment $adjustment)
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        abort_unless(is_null($adjustment->editor_paid_at), 422);

        $adjustment->delete();

        return redirect()->route('editor-pay.index')
            ->with('success', 'Adjustment removed.');
    }

    private function buildHistoryBatches($paidOrders, $paidAdjustments): array
    {
        $batches = [];

        foreach ($paidOrders as $o) {
            $key = $o->editor_paid_at->toDateString();
            $batches[$key]['paid_at'] ??= $o->editor_paid_at;
            $batches[$key]['orders'][]  = $o;
            $batches[$key]['adjustments'] ??= [];
            $batches[$key]['total']  = ($batches[$key]['total'] ?? 0) + (float) $o->cog_commission;
        }

        foreach ($paidAdjustments as $adj) {
            $key = $adj->editor_paid_at->toDateString();
            $batches[$key]['paid_at']     ??= $adj->editor_paid_at;
            $batches[$key]['orders']      ??= [];
            $batches[$key]['adjustments'][] = $adj;
            $batches[$key]['total']          = ($batches[$key]['total'] ?? 0) + (float) $adj->amount;
        }

        usort($batches, fn($a, $b) => $b['paid_at'] <=> $a['paid_at']);

        return array_values($batches);
    }
}
