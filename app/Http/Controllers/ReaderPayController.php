<?php

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
use Carbon\Carbon;
use Illuminate\Http\Request;

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
        abort_unless(is_null($assignment->reader_paid_at), 422);

        // Zero the pay and mark as paid (now) so it drops out of the unpaid batch entirely,
        // while keeping the assignment record intact for order/coverage history.
        $assignment->update(['pay_rate' => 0, 'reader_paid_at' => now()]);

        return redirect()->route('payroll.index')
            ->with('success', "Pay for order {$assignment->order_number} removed.");
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
        abort_unless(is_null($adjustment->reader_paid_at), 422);

        $adjustment->delete();

        return redirect()->route('payroll.index')
            ->with('success', 'Adjustment removed.');
    }
}
