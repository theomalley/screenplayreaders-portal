<?php

// v1.9 — 2026-11-23 | deleteAssignmentPay()/deleteAdjustment() now wrap their check-then-act
//                     in DB::transaction()+lockForUpdate() to close a benign but unnecessary
//                     race between the abort_unless() read and the update/delete.
// v1.8 — 2026-11-23 | deleteAssignmentPay() now explicitly calls ReaderCogsService::syncForOrder()
//                     after zeroing pay_rate — Assignment's saved() hook only re-syncs reader
//                     COGS when assigned_reader_id changes, not pay_rate, so OrderRevenue.cog_reader/
//                     cog_total/net_revenue were going stale after a pay removal.
// v1.7 — 2026-07-23 | Authorization moved to UserPolicy readerPay* abilities (app/Policies),
//                     replacing inline abort_unless(...) calls. Covered by
//                     tests/Feature/ReaderPayControllerTest.php.
// v1.6 — 2026-07-18 | Removed where('vendor', 'sr') from markPaid()/markUnpaid()/clearUnpaidBatch()/
//                      removeHistoryBatch() — it silently skipped wd (Writers Digest) assignments
//                      when marking pay, so even after fixing the Payroll page's own unpaid-list
//                      query, this controller's "Mark Paid" button would have kept excluding them.
// v1.5 — 2026-06-13 | deleteAssignmentPay() now also sets reader_paid_at so the line item
//                      is actually dropped from the unpaid batch, not just zeroed
// v1.4 — 2026-06-11 | Add deleteAssignmentPay() — zero an individual unpaid assignment's pay_rate
// v1.3 — 2026-06-11 | Move dashboard into Payroll — remove index(), redirect actions to payroll.index
// v1.2 — 2026-05-28 | Add markUnpaid — revert a paid batch back to unpaid
// v1.1 — 2026-05-25 | Add manual adjustments, paginated history, PayPeriod grouping
// v1.0 — 2026-05-25 | Initial — unpaid completed assignments grouped by reader, mark-as-paid

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\ReaderPayAdjustment;
use App\Models\User;
use App\Services\ReaderCogsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReaderPayController extends Controller
{
    public function markPaid(User $reader)
    {
        $this->authorize('readerPayMarkPaid', User::class);

        $now = Carbon::now();

        // Both sr and wd assignments share pay_rate/reader_paid_at — never filter by vendor here.
        $assignmentCount = Assignment::where('assigned_reader_id', $reader->id)
            ->where('status', Assignment::STATUS_COMPLETED)
            ->whereNull('reader_paid_at')
            ->update(['reader_paid_at' => $now]);

        $adjustmentCount = ReaderPayAdjustment::where('user_id', $reader->id)
            ->whereNull('reader_paid_at')
            ->update(['reader_paid_at' => $now]);

        $name = $reader->readerProfile?->displayName() ?? $reader->name;

        return redirect()->route('payroll.index')
            ->with('success', "Marked {$name} as paid ({$assignmentCount} coverage(s), {$adjustmentCount} adjustment(s)).");
    }

    public function markUnpaid(Request $request, User $reader)
    {
        $this->authorize('readerPayMarkUnpaid', User::class);

        $validated = $request->validate(['paid_at' => 'required|date']);
        $date = Carbon::parse($validated['paid_at'])->toDateString();

        $assignmentCount = Assignment::where('assigned_reader_id', $reader->id)
            ->whereDate('reader_paid_at', $date)
            ->update(['reader_paid_at' => null]);

        $adjustmentCount = ReaderPayAdjustment::where('user_id', $reader->id)
            ->whereDate('reader_paid_at', $date)
            ->update(['reader_paid_at' => null]);

        $name = $reader->readerProfile?->displayName() ?? $reader->name;

        return redirect()->route('payroll.index')
            ->with('success', "Reverted {$name}'s {$date} payment to unpaid ({$assignmentCount} coverage(s), {$adjustmentCount} adjustment(s)).");
    }

    public function addAdjustment(Request $request, User $reader)
    {
        $this->authorize('readerPayAddAdjustment', User::class);

        $validated = $request->validate([
            'amount'      => 'required|numeric|not_in:0',
            'description' => 'required|string|max:255',
        ]);

        ReaderPayAdjustment::create([
            'user_id'           => $reader->id,
            'amount'            => $validated['amount'],
            'description'       => $validated['description'],
            'added_by_user_id'  => auth()->id(),
        ]);

        $name = $reader->readerProfile?->displayName() ?? $reader->name;
        $sign = (float) $validated['amount'] >= 0 ? '+' : '';

        return redirect()->route('payroll.index')
            ->with('success', "Adjustment {$sign}{$validated['amount']} added for {$name}.");
    }

    public function deleteAssignmentPay(Assignment $assignment)
    {
        $this->authorize('readerPayDeleteAssignmentPay', User::class);

        $orderNumber = $assignment->order_number;

        DB::transaction(function () use ($assignment) {
            $locked = Assignment::lockForUpdate()->find($assignment->id);
            abort_unless(is_null($locked->reader_paid_at), 422);

            // Zero the pay and mark as paid (now) so it drops out of the unpaid batch entirely,
            // while keeping the assignment record intact for order/coverage history.
            $locked->update(['pay_rate' => 0, 'reader_paid_at' => now()]);
        });

        // Assignment's saved() hook only re-syncs reader COGS when assigned_reader_id changes,
        // not pay_rate — so trigger the resync explicitly here, or OrderRevenue.cog_reader/
        // cog_total/net_revenue for this order go stale (overstating reader cost).
        if ($orderNumber) {
            app(ReaderCogsService::class)->syncForOrder($orderNumber);
        }

        return redirect()->route('payroll.index')
            ->with('success', "Pay for order {$orderNumber} removed.");
    }

    public function clearUnpaidBatch(User $reader)
    {
        $this->authorize('readerPayClearUnpaidBatch', User::class);

        // Hard-delete unpaid completed TEST assignments for this reader + all pending adjustments
        // (real, non-test assignments must never be hard-deleted here — see removeHistoryBatch())
        $deleted = Assignment::where('assigned_reader_id', $reader->id)
            ->where('status', Assignment::STATUS_COMPLETED)
            ->whereNull('reader_paid_at')
            ->where('is_test', true)
            ->delete();

        ReaderPayAdjustment::where('user_id', $reader->id)
            ->whereNull('reader_paid_at')
            ->delete();

        $name = $reader->readerProfile?->displayName() ?? $reader->name;

        return redirect()->route('payroll.index')
            ->with('success', "Cleared unpaid queue for {$name} ({$deleted} test assignment(s) removed).");
    }

    public function removeHistoryBatch(Request $request, User $reader)
    {
        $this->authorize('readerPayRemoveHistoryBatch', User::class);

        $validated = $request->validate(['paid_at' => 'required|date']);
        $date = Carbon::parse($validated['paid_at'])->toDateString();

        // Test assignments: delete entirely. Non-test: revert to unpaid (safer for production data).
        Assignment::where('assigned_reader_id', $reader->id)
            ->whereDate('reader_paid_at', $date)
            ->where('is_test', true)
            ->delete();

        Assignment::where('assigned_reader_id', $reader->id)
            ->whereDate('reader_paid_at', $date)
            ->where('is_test', false)
            ->update(['reader_paid_at' => null]);

        ReaderPayAdjustment::where('user_id', $reader->id)
            ->whereDate('reader_paid_at', $date)
            ->delete();

        $name = $reader->readerProfile?->displayName() ?? $reader->name;

        return redirect()->route('payroll.index')
            ->with('success', "Removed batch for {$name} ({$date}).");
    }

    public function deleteAdjustment(ReaderPayAdjustment $adjustment)
    {
        $this->authorize('readerPayDeleteAdjustment', User::class);

        DB::transaction(function () use ($adjustment) {
            $locked = ReaderPayAdjustment::lockForUpdate()->find($adjustment->id);
            abort_unless(is_null($locked->reader_paid_at), 422);

            $locked->delete();
        });

        return redirect()->route('payroll.index')
            ->with('success', 'Adjustment removed.');
    }
}
