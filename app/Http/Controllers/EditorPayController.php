<?php

// v2.3 — 2026-09-09 | SECURITY/BUG FIX: markPaid()'s duplicate-flat-rate-adjustment guard
//                     compared UTC-converted bounds against a naive LA-local created_at
//                     (app.timezone/PHP default tz are both America/Los_Angeles — nothing
//                     is ever actually stored in UTC), so the guard was effectively
//                     always false; re-running "Mark Paid" reliably double-paid the
//                     editor's weekly flat rate. Also fixed the "past" scope's reference
//                     date landing outside the very period bounds the guard checked
//                     against. Wrapped the check+insert in a transaction with a locking
//                     read to close the remaining double-click race.
// v2.2 — 2026-07-23 | Authorization moved to UserPolicy editorPay* abilities (app/Policies)
//                     and two editor-pay.* Gate abilities (AppServiceProvider) for the
//                     OrderRevenue/EditorPayAdjustment-targeted actions, replacing inline
//                     abort_unless(...) calls. Covered by tests/Feature/EditorPayControllerTest.php.
// v2.1 — 2026-07-18 | markPaid()/clearUnpaidBatch() now take a required "scope" ('past'|'current')
//                     since the payroll card is split into a past-due card and a current-period
//                     card per editor — each card's button only acts on its own bucket, scoped by
//                     PayPeriod::current() rather than marking every unpaid item for the editor at
//                     once. Removed updateFlatRate()/deleteFlatRate() — the payroll page's synthetic
//                     "Flat Rate" row they edited was deleted (it double-counted against the real
//                     ledger adjustment); the editor's weekly flat is now only edited from their
//                     profile page (already reachable from this same card via the name/photo link).
// v2.0 — 2026-07-17 | Scope every action to a specific $editor (route-bound) instead of "the"
//                     editor — commission/adjustments/flat-rate no longer commingle once more
//                     than one editor exists. markUnpaid() now also restricts non-admin editors
//                     to their own pay (previously any editor could revert any editor's payment).
// v1.8 — 2026-06-23 | Backdate flat rate adjustment created_at into the period being paid
// v1.7 — 2026-06-23 | Add updateFlatRate() and deleteFlatRate() for inline editing of flat rate line item
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
use App\Support\PayPeriod;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EditorPayController extends Controller
{
    public function markPaid(Request $request, User $editor)
    {
        $this->authorize('editorPayMarkPaid', User::class);
        abort_unless($editor->isEditor(), 404);

        $validated = $request->validate(['scope' => 'required|in:past,current']);
        $scope     = $validated['scope'];
        $now       = Carbon::now();

        $currentPeriodStart = PayPeriod::current()[0];

        $weeklyFlat = (float) ($editor->editorProfile?->editor_weekly_flat ?? 0.0);

        if ($weeklyFlat > 0) {
            $schedule = Setting::getPayoutSchedule();
            $weeks = $schedule['frequency'] === 'biweekly' ? 2 : 1;
            $periodFlatRate = round($weeklyFlat * $weeks, 2);

            // The period being paid: the one that just closed (for "past"), or the
            // still-open current one (for "current", i.e. paying early).
            // FIX: was `subMinute()` for "past" — with the default 1-hour gap between
            // periods (PayPeriod::end() = next period's start minus 1 hour), that landed
            // AFTER the previous period's own end, so PayPeriod::bounds() on it resolved
            // to bounds that didn't contain the timestamp we were about to save as
            // created_at. subHours(2) safely lands inside the previous period regardless
            // of how short the configured gap is.
            $referenceDate = $scope === 'past'
                ? $currentPeriodStart->copy()->subHours(2)
                : $currentPeriodStart->copy();
            [$paidStart, $paidEnd] = PayPeriod::bounds($referenceDate);

            // FIX: PayPeriod's Carbon instances carry the America/Los_Angeles timezone
            // (PayPeriod::TZ). app.timezone is also America/Los_Angeles and PHP's default
            // timezone is set to match, so created_at is written and read back as a naive
            // LA-local wall-clock string — no UTC conversion ever happens on either side.
            // The previous ->utc() calls here shifted the comparison bounds by several
            // hours away from what was actually stored, so this "already paid this
            // period?" check was effectively always false, and re-running "Mark Paid"
            // (a double-click, a retry, or running it again before the period rolls)
            // inserted a second full-amount flat-rate adjustment every time.
            //
            // Wrapped in a transaction with a locking read so a genuinely concurrent
            // double-click serializes on InnoDB's gap lock instead of both requests
            // reading "not found" before either commits.
            DB::transaction(function () use ($editor, $paidStart, $paidEnd, $periodFlatRate, $weeks) {
                $alreadyExists = EditorPayAdjustment::where('user_id', $editor->id)
                    ->where('created_at', '>=', $paidStart)
                    ->where('created_at', '<=', $paidEnd)
                    ->where('description', 'like', 'Weekly flat rate%')
                    ->lockForUpdate()
                    ->exists();

                if (! $alreadyExists) {
                    $adj = new EditorPayAdjustment([
                        'user_id'          => $editor->id,
                        'amount'           => $periodFlatRate,
                        'description'      => $weeks > 1
                            ? "Weekly flat rate × {$weeks} weeks"
                            : 'Weekly flat rate',
                        'added_by_user_id' => auth()->id(),
                    ]);
                    // Set to the period's own start — guaranteed to fall within
                    // [$paidStart, $paidEnd], unlike deriving it from an offset guess.
                    $adj->created_at = $paidStart;
                    $adj->save();
                }
            });
        }

        $ordersQuery = OrderRevenue::where('editor_id', $editor->id)
            ->whereNull('editor_paid_at')
            ->where('skip_commission', false)
            ->where('cog_commission', '>', 0);

        $adjustmentsQuery = EditorPayAdjustment::where('user_id', $editor->id)
            ->whereNull('editor_paid_at');

        if ($scope === 'past') {
            $ordersQuery->where('ordered_at', '<', $currentPeriodStart);
            $adjustmentsQuery->where('created_at', '<', $currentPeriodStart);
        } else {
            $ordersQuery->where('ordered_at', '>=', $currentPeriodStart);
            $adjustmentsQuery->where('created_at', '>=', $currentPeriodStart);
        }

        $ordersQuery->update(['editor_paid_at' => $now]);
        $adjustmentsQuery->update(['editor_paid_at' => $now]);

        return redirect()->route('payroll.index')
            ->with('success', 'Pending pay for ' . ($editor->editorProfile?->displayName() ?? $editor->name) . ' marked as paid.');
    }

    public function clearUnpaidBatch(Request $request, User $editor)
    {
        $this->authorize('editorPayClearUnpaidBatch', User::class);
        abort_unless($editor->isEditor(), 404);

        $validated = $request->validate(['scope' => 'required|in:past,current']);
        $currentPeriodStart = PayPeriod::current()[0];

        $ordersQuery = OrderRevenue::where('editor_id', $editor->id)
            ->whereNull('editor_paid_at')
            ->where('skip_commission', false)
            ->where('cog_commission', '>', 0);

        $adjustmentsQuery = EditorPayAdjustment::where('user_id', $editor->id)
            ->whereNull('editor_paid_at');

        if ($validated['scope'] === 'past') {
            $ordersQuery->where('ordered_at', '<', $currentPeriodStart);
            $adjustmentsQuery->where('created_at', '<', $currentPeriodStart);
        } else {
            $ordersQuery->where('ordered_at', '>=', $currentPeriodStart);
            $adjustmentsQuery->where('created_at', '>=', $currentPeriodStart);
        }

        $ordersQuery->update(['cog_commission' => 0]);
        $adjustmentsQuery->delete();

        return redirect()->route('payroll.index')
            ->with('success', 'Cleared pending commissions and adjustments for ' . ($editor->editorProfile?->displayName() ?? $editor->name) . '.');
    }

    public function markUnpaid(Request $request, User $editor)
    {
        $this->authorize('editorPayMarkUnpaid', $editor);
        abort_unless($editor->isEditor(), 404);

        $validated = $request->validate(['paid_at' => 'required|date']);
        $date = Carbon::parse($validated['paid_at'])->toDateString();

        $orderCount = OrderRevenue::where('editor_id', $editor->id)
            ->whereNotNull('editor_paid_at')
            ->whereDate('editor_paid_at', $date)
            ->update(['editor_paid_at' => null]);

        $adjustmentCount = EditorPayAdjustment::where('user_id', $editor->id)
            ->whereNotNull('editor_paid_at')
            ->whereDate('editor_paid_at', $date)
            ->update(['editor_paid_at' => null]);

        return redirect()->route('payroll.index')
            ->with('success', "Reverted {$date} payment to unpaid ({$orderCount} commission(s), {$adjustmentCount} adjustment(s)).");
    }

    public function addAdjustment(Request $request, User $editor)
    {
        $this->authorize('editorPayAddAdjustment', User::class);
        abort_unless($editor->isEditor(), 404);

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
        $this->authorize('editor-pay.manage-commission');
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
        $this->authorize('editor-pay.manage-commission');
        abort_unless(is_null($order->editor_paid_at), 422);

        $order->update(['cog_commission' => 0]);

        return redirect()->route('payroll.index')
            ->with('success', "Commission for order {$order->order_number} removed.");
    }

    public function deleteHistoryBatch(User $editor, string $date)
    {
        $this->authorize('editorPayDeleteHistoryBatch', User::class);
        abort_unless($editor->isEditor(), 404);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            abort(404);
        }

        OrderRevenue::where('editor_id', $editor->id)
            ->whereNotNull('editor_paid_at')
            ->whereDate('editor_paid_at', $date)
            ->update(['cog_commission' => 0, 'editor_paid_at' => null]);

        EditorPayAdjustment::where('user_id', $editor->id)
            ->whereNotNull('editor_paid_at')
            ->whereDate('editor_paid_at', $date)
            ->delete();

        return redirect()->route('payroll.index')
            ->with('success', "Payment history for {$date} permanently deleted.");
    }

    public function deleteAllHistory(User $editor)
    {
        $this->authorize('editorPayDeleteAllHistory', User::class);
        abort_unless($editor->isEditor(), 404);

        OrderRevenue::where('editor_id', $editor->id)
            ->whereNotNull('editor_paid_at')
            ->where('cog_commission', '>', 0)
            ->update(['cog_commission' => 0, 'editor_paid_at' => null]);

        EditorPayAdjustment::where('user_id', $editor->id)
            ->whereNotNull('editor_paid_at')
            ->delete();

        return redirect()->route('payroll.index')
            ->with('success', 'All payment history permanently deleted for ' . ($editor->editorProfile?->displayName() ?? $editor->name) . '.');
    }

    public function deleteAdjustment(EditorPayAdjustment $adjustment)
    {
        $this->authorize('editor-pay.delete-adjustment');
        abort_unless(is_null($adjustment->editor_paid_at), 422);

        $adjustment->delete();

        return redirect()->route('payroll.index')
            ->with('success', 'Adjustment removed.');
    }
}
