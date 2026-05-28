<?php

// v1.2 — 2026-05-28 | Add markUnpaid — revert a paid batch back to unpaid
// v1.1 — 2026-05-25 | Add manual adjustments, paginated history, PayPeriod grouping
// v1.0 — 2026-05-25 | Initial — unpaid completed assignments grouped by reader, mark-as-paid

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\ReaderPayAdjustment;
use App\Models\User;
use App\Support\PayPeriod;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReaderPayController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->isAdminOrEditor(), 403);

        // --- UNPAID ---
        $unpaidAssignments = Assignment::with(['assignedReader.readerProfile'])
            ->where('status', Assignment::STATUS_COMPLETED)
            ->where('vendor', 'sr')
            ->whereNull('reader_paid_at')
            ->whereNotNull('assigned_reader_id')
            ->orderBy('completed_at')
            ->get();

        $unpaidAdjustments = ReaderPayAdjustment::with(['reader.readerProfile', 'addedBy'])
            ->whereNull('reader_paid_at')
            ->orderBy('created_at')
            ->get();

        // Merge into per-reader buckets
        $readerIds = $unpaidAssignments->pluck('assigned_reader_id')
            ->merge($unpaidAdjustments->pluck('user_id'))
            ->unique();

        $byReader = $readerIds->map(function ($readerId) use ($unpaidAssignments, $unpaidAdjustments) {
            $assignments  = $unpaidAssignments->where('assigned_reader_id', $readerId);
            $adjustments  = $unpaidAdjustments->where('user_id', $readerId);
            $firstRecord  = $assignments->first() ?? $adjustments->first();
            $reader       = $firstRecord instanceof Assignment
                ? $firstRecord->assignedReader
                : $firstRecord->reader;
            $profile      = $reader?->readerProfile;

            $assignmentTotal  = $assignments->sum('pay_rate');
            $adjustmentTotal  = $adjustments->sum(fn($a) => (float) $a->amount);
            $totalOwed        = round($assignmentTotal + $adjustmentTotal, 2);

            return [
                'reader_id'    => $readerId,
                'reader_name'  => $profile?->displayName() ?? $reader?->name ?? 'Unknown',
                'paypal_email' => $profile?->paypal_email,
                'photo_url'    => $profile?->photo ? asset('storage/' . $profile->photo) : null,
                'initials'     => $profile?->initials ?? '?',
                'assignments'  => $assignments->sortBy('completed_at'),
                'adjustments'  => $adjustments->sortBy('created_at'),
                'total_owed'   => $totalOwed,
            ];
        })->sortBy('reader_name')->values();

        // --- HISTORY (paginated — 10 pay batches per page across all readers) ---
        // A "batch" = all records with the same reader_paid_at (stamped together by markPaid)
        $paidAssignments = Assignment::with(['assignedReader.readerProfile'])
            ->where('status', Assignment::STATUS_COMPLETED)
            ->where('vendor', 'sr')
            ->whereNotNull('reader_paid_at')
            ->whereNotNull('assigned_reader_id')
            ->orderByDesc('reader_paid_at')
            ->get();

        $paidAdjustments = ReaderPayAdjustment::with(['reader.readerProfile'])
            ->whereNotNull('reader_paid_at')
            ->orderByDesc('reader_paid_at')
            ->get();

        // Group history by reader + paid-on date (YYYY-MM-DD) to form batches
        $historyBatches = $this->buildHistoryBatches($paidAssignments, $paidAdjustments);

        $page        = max(1, (int) request()->input('page', 1));
        $perPage     = 10;
        $totalPages  = max(1, (int) ceil(count($historyBatches) / $perPage));
        $page        = min($page, $totalPages);
        $history     = array_slice($historyBatches, ($page - 1) * $perPage, $perPage);

        return view('reader-pay.index', compact('byReader', 'history', 'page', 'totalPages'));
    }

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

        return redirect()->route('reader-pay.index')
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

        return redirect()->route('reader-pay.index')
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

        return redirect()->route('reader-pay.index')
            ->with('success', "Adjustment {$sign}{$validated['amount']} added for {$name}.");
    }

    public function deleteAdjustment(ReaderPayAdjustment $adjustment)
    {
        abort_unless(auth()->user()->isAdminOrEditor(), 403);
        abort_unless(is_null($adjustment->reader_paid_at), 422);

        $adjustment->delete();

        return redirect()->route('reader-pay.index')
            ->with('success', 'Adjustment removed.');
    }

    // --- Private helpers ---

    private function buildHistoryBatches($paidAssignments, $paidAdjustments): array
    {
        $batches = [];

        foreach ($paidAssignments as $a) {
            $key = $a->assigned_reader_id . '|' . $a->reader_paid_at->toDateString();
            $profile = $a->assignedReader?->readerProfile;
            $batches[$key]['reader_id']   ??= $a->assigned_reader_id;
            $batches[$key]['reader_name'] ??= $profile?->displayName() ?? $a->assignedReader?->name ?? 'Unknown';
            $batches[$key]['photo_url']   ??= $profile?->photo ? asset('storage/' . $profile->photo) : null;
            $batches[$key]['initials']    ??= $profile?->initials ?? '?';
            $batches[$key]['paid_at']     ??= $a->reader_paid_at;
            $batches[$key]['assignments'][] = $a;
            $batches[$key]['adjustments']   ??= [];
            $batches[$key]['total']         = ($batches[$key]['total'] ?? 0) + (float) $a->pay_rate;
        }

        foreach ($paidAdjustments as $adj) {
            $key = $adj->user_id . '|' . $adj->reader_paid_at->toDateString();
            $profile = $adj->reader?->readerProfile;
            $batches[$key]['reader_id']   ??= $adj->user_id;
            $batches[$key]['reader_name'] ??= $profile?->displayName() ?? $adj->reader?->name ?? 'Unknown';
            $batches[$key]['photo_url']   ??= $profile?->photo ? asset('storage/' . $profile->photo) : null;
            $batches[$key]['initials']    ??= $profile?->initials ?? '?';
            $batches[$key]['paid_at']     ??= $adj->reader_paid_at;
            $batches[$key]['assignments'] ??= [];
            $batches[$key]['adjustments'][] = $adj;
            $batches[$key]['total']         = ($batches[$key]['total'] ?? 0) + (float) $adj->amount;
        }

        // Sort newest first
        usort($batches, fn($a, $b) => $b['paid_at'] <=> $a['paid_at']);

        return array_values($batches);
    }
}
