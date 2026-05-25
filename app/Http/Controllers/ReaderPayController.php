<?php

// v1.0 — 2026-05-25 | Admin-only reader pay dashboard — unpaid completed assignments, mark-as-paid

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\User;
use Carbon\Carbon;

class ReaderPayController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        // All completed SR assignments that haven't been paid yet
        $unpaid = Assignment::with(['assignedReader.readerProfile'])
            ->where('status', Assignment::STATUS_COMPLETED)
            ->where('vendor', 'sr')
            ->whereNull('reader_paid_at')
            ->whereNotNull('assigned_reader_id')
            ->orderBy('completed_at')
            ->get();

        // Group by reader — includes pay_rate and paypal_email from profile
        $byReader = $unpaid->groupBy('assigned_reader_id')->map(function ($assignments) {
            $first       = $assignments->first();
            $reader      = $first->assignedReader;
            $profile     = $reader?->readerProfile;
            $totalOwed   = $assignments->sum('pay_rate');
            $paypalEmail = $profile?->paypal_email;

            return [
                'reader_id'    => $first->assigned_reader_id,
                'reader_name'  => $profile?->displayName() ?? $reader?->name ?? 'Unknown',
                'paypal_email' => $paypalEmail,
                'assignments'  => $assignments->sortBy('completed_at'),
                'total_owed'   => $totalOwed,
            ];
        })->sortBy('reader_name');

        // Previously paid — most recent 50
        $recentPaid = Assignment::with(['assignedReader.readerProfile'])
            ->where('status', Assignment::STATUS_COMPLETED)
            ->where('vendor', 'sr')
            ->whereNotNull('reader_paid_at')
            ->whereNotNull('assigned_reader_id')
            ->orderByDesc('reader_paid_at')
            ->limit(50)
            ->get();

        return view('reader-pay.index', compact('byReader', 'recentPaid'));
    }

    public function markPaid(User $reader)
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $now = Carbon::now();

        Assignment::where('assigned_reader_id', $reader->id)
            ->where('status', Assignment::STATUS_COMPLETED)
            ->where('vendor', 'sr')
            ->whereNull('reader_paid_at')
            ->update(['reader_paid_at' => $now]);

        $name = $reader->readerProfile?->displayName() ?? $reader->name;

        return redirect()->route('reader-pay.index')
            ->with('success', "All unpaid coverages for {$name} marked as paid.");
    }
}
