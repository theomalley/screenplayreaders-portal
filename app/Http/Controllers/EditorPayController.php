<?php

// v1.6 — 2026-06-23 | Auto-create flat rate adjustment on markPaid() so it appears in payment history
// v1.5 — 2026-06-11 | Add clearUnpaidBatch() — zero pending commissions + delete pending adjustments
// v1.4 — 2026-06-11 | Move dashboard into Payroll — remove index(), add markUnpaid(), redirect actions to payroll.index
// v1.3 — 2026-06-10 | Admin can permanently delete a payment-history batch or wipe all history
// v1.2 — 2026-06-10 | Admin can edit/zero-out an order's commission directly from the editor pay view
// v1.1 — 2026-05-28 | Source weekly flat from editor profile; remove global Setting dependency

namespace App\Http\Controllers;

use App\Models\EditorPayAdjustment;
use App\Models\OrderRevenue;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class EditorPayController extends Controller
{
    public function markPaid()
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $now = Carbon::now();

        $editor = User::where('role', 'editor')->where('is_test', false)->whereHas('editorProfile')->first();
        $weeklyFlat = (float) ($editor?->editorProfile?->editor_weekly_flat ?? 0.0);

        if ($weeklyFlat > 0 && $editor) {
            $schedule = Setting::getPayoutSchedule();
            $weeks = $schedule['frequency'] === 'biweekly' ? 2 : 1;
            $periodFlatRate = round($weeklyFlat * $weeks, 2);

            EditorPayAdjustment::create([
                'user_id'          => $editor->id,
                'amount'           => $periodFlatRate,
                'description'      => $weeks > 1
                    ? "Weekly flat rate × {$weeks} weeks"
                    : 'Weekly flat rate',
                'added_by_user_id' => auth()->id(),
            ]);
        }

        OrderRevenue::whereNull('editor_paid_at')
            ->where('skip_commission', false)
            ->where('cog_commission', '>', 0)
            ->update(['editor_paid_at' => $now]);

        EditorPayAdjustment::whereNull('editor_paid_at')
            ->update(['editor_paid_at' => $now]);

        return redirect()->route('payroll.index')
            ->with('success', 'All pending editor pay marked as paid.');
    }

    public function clearUnpaidBatch()
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        OrderRevenue::whereNull('editor_paid_at')
            ->where('skip_commission', false)
            ->where('cog_commission', '>', 0)
            ->update(['cog_commission' => 0]);

        EditorPayAdjustment::whereNull('editor_paid_at')->delete();

        return redirect()->route('payroll.index')
            ->with('success', 'Cleared all pending editor commissions and adjustments.');
    }

    public function markUnpaid(Request $request)
    {
        abort_unless(auth()->user()->isAdminOrEditor(), 403);

        $validated = $request->validate(['paid_at' => 'required|date']);
        $date = Carbon::parse($validated['paid_at'])->toDateString();

        $orderCount = OrderRevenue::whereNotNull('editor_paid_at')
            ->whereDate('editor_paid_at', $date)
            ->update(['editor_paid_at' => null]);

        $adjustmentCount = EditorPayAdjustment::whereNotNull('editor_paid_at')
            ->whereDate('editor_paid_at', $date)
            ->update(['editor_paid_at' => null]);

        return redirect()->route('payroll.index')
            ->with('success', "Reverted editor's {$date} payment to unpaid ({$orderCount} commission(s), {$adjustmentCount} adjustment(s)).");
    }

    public function addAdjustment(Request $request)
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $editor = User::where('role', 'editor')->where('is_test', false)->whereHas('editorProfile')->firstOrFail();

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

        return redirect()->route('payroll.index')
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

        return redirect()->route('payroll.index')
            ->with('success', "Commission for order {$order->order_number} updated to $" . number_format((float) $validated['cog_commission'], 2) . '.');
    }

    public function deleteCommission(OrderRevenue $order)
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        abort_unless(is_null($order->editor_paid_at), 422);

        $order->update(['cog_commission' => 0]);

        return redirect()->route('payroll.index')
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

        return redirect()->route('payroll.index')
            ->with('success', "Payment history for {$date} permanently deleted.");
    }

    public function deleteAllHistory()
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        OrderRevenue::whereNotNull('editor_paid_at')
            ->where('cog_commission', '>', 0)
            ->update(['cog_commission' => 0, 'editor_paid_at' => null]);

        EditorPayAdjustment::whereNotNull('editor_paid_at')->delete();

        return redirect()->route('payroll.index')
            ->with('success', 'All editor payment history permanently deleted.');
    }

    public function deleteAdjustment(EditorPayAdjustment $adjustment)
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        abort_unless(is_null($adjustment->editor_paid_at), 422);

        $adjustment->delete();

        return redirect()->route('payroll.index')
            ->with('success', 'Adjustment removed.');
    }
}
