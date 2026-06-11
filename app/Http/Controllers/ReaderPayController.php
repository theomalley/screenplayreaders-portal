<?php

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
        abort_unless(auth()->user()->isAdminOrEditor(), 403);

        $now = Carbon::now();

        $assignmentCount = Assignment::where('assigned_reader_id', $reader->id)
            ->where('status', Assignment::STATUS_COMPLETED)
            ->where('vendor', 'sr')
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
        abort_unless(auth()->user()->isAdminOrEditor(), 403);

        $validated = $request->validate(['paid_at' => 'required|date']);
        $date = Carbon::parse($validated['paid_at'])->toDateString();

        $assignmentCount = Assignment::where('assigned_reader_id', $reader->id)
            ->where('vendor', 'sr')
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
        abort_unless(auth()->user()->isAdminOrEditor(), 403);

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
        abort_unless(auth()->user()->isAdmin(), 403);
        abort_unless(is_null($assignment->reader_paid_at), 422);

        $assignment->update(['pay_rate' => 0]);

        return redirect()->route('payroll.index')
            ->with('success', "Pay for order {$assignment->order_number} removed.");
    }

    public function clearUnpaidBatch(User $reader)
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        // Hard-delete all unpaid completed assignments for this reader + all pending adjustments
        $deleted = Assignment::where('assigned_reader_id', $reader->id)
            ->where('vendor', 'sr')
            ->where('status', Assignment::STATUS_COMPLETED)
            ->whereNull('reader_paid_at')
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
        abort_unless(auth()->user()->isAdmin(), 403);

        $validated = $request->validate(['paid_at' => 'required|date']);
        $date = Carbon::parse($validated['paid_at'])->toDateString();

        // Test assignments: delete entirely. Non-test: revert to unpaid (safer for production data).
        Assignment::where('assigned_reader_id', $reader->id)
            ->where('vendor', 'sr')
            ->whereDate('reader_paid_at', $date)
            ->where('is_test', true)
            ->delete();

        Assignment::where('assigned_reader_id', $reader->id)
            ->where('vendor', 'sr')
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
        abort_unless(auth()->user()->isAdminOrEditor(), 403);
        abort_unless(is_null($adjustment->reader_paid_at), 422);

        $adjustment->delete();

        return redirect()->route('payroll.index')
            ->with('success', 'Adjustment removed.');
    }
}
