<?php

// v1.0 — 2026-05-25 | Reader-facing payments view — current period, prior unpaid, paginated history

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\ReaderPayAdjustment;
use App\Support\PayPeriod;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PaymentsController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        abort_unless($user->isReader(), 403);

        [$currentStart, $currentEnd] = PayPeriod::current();

        // All unpaid completed SR assignments for this reader
        $unpaidAssignments = Assignment::where('assigned_reader_id', $user->id)
            ->where('status', Assignment::STATUS_COMPLETED)
            ->where('vendor', 'sr')
            ->whereNull('reader_paid_at')
            ->orderBy('completed_at')
            ->get();

        $unpaidAdjustments = ReaderPayAdjustment::where('user_id', $user->id)
            ->whereNull('reader_paid_at')
            ->orderBy('created_at')
            ->get();

        // Group unpaid into pay periods
        $unpaidPeriods = $this->groupIntoPeriods($unpaidAssignments, $unpaidAdjustments);

        // Separate current period from prior unpaid
        $currentPeriod = null;
        $priorPeriods  = [];

        foreach ($unpaidPeriods as $period) {
            if ($period['period_start']->eq($currentStart)) {
                $currentPeriod = $period;
            } else {
                $priorPeriods[] = $period;
            }
        }

        // Paginated history (paid batches for this reader)
        $paidAssignments = Assignment::where('assigned_reader_id', $user->id)
            ->where('status', Assignment::STATUS_COMPLETED)
            ->where('vendor', 'sr')
            ->whereNotNull('reader_paid_at')
            ->orderByDesc('reader_paid_at')
            ->get();

        $paidAdjustments = ReaderPayAdjustment::where('user_id', $user->id)
            ->whereNotNull('reader_paid_at')
            ->orderByDesc('reader_paid_at')
            ->get();

        $historyBatches = $this->buildHistoryBatches($paidAssignments, $paidAdjustments);

        $page       = max(1, (int) request()->input('page', 1));
        $perPage    = 8;
        $totalPages = max(1, (int) ceil(count($historyBatches) / $perPage));
        $page       = min($page, $totalPages);
        $history    = array_slice($historyBatches, ($page - 1) * $perPage, $perPage);

        return view('payments.index', compact(
            'currentPeriod', 'priorPeriods', 'history', 'page', 'totalPages', 'currentStart', 'currentEnd'
        ));
    }

    private function groupIntoPeriods(Collection $assignments, Collection $adjustments): array
    {
        $periods = [];

        foreach ($assignments as $a) {
            $start = PayPeriod::start($a->completed_at);
            $key   = $start->toDateTimeString();
            $periods[$key]['period_start']   ??= $start;
            $periods[$key]['period_end']     ??= PayPeriod::end($start);
            $periods[$key]['assignments'][]  = $a;
            $periods[$key]['adjustments']    ??= [];
            $periods[$key]['total']           = ($periods[$key]['total'] ?? 0) + (float) $a->pay_rate;
        }

        foreach ($adjustments as $adj) {
            $start = PayPeriod::start($adj->created_at);
            $key   = $start->toDateTimeString();
            $periods[$key]['period_start']  ??= $start;
            $periods[$key]['period_end']    ??= PayPeriod::end($start);
            $periods[$key]['assignments']   ??= [];
            $periods[$key]['adjustments'][] = $adj;
            $periods[$key]['total']          = ($periods[$key]['total'] ?? 0) + (float) $adj->amount;
        }

        // Newest period first
        usort($periods, fn($a, $b) => $b['period_start'] <=> $a['period_start']);

        return array_values($periods);
    }

    private function buildHistoryBatches(Collection $assignments, Collection $adjustments): array
    {
        $batches = [];

        foreach ($assignments as $a) {
            $key = $a->reader_paid_at->toDateString();
            $batches[$key]['paid_at']      ??= $a->reader_paid_at;
            $batches[$key]['assignments'][] = $a;
            $batches[$key]['adjustments']   ??= [];
            $batches[$key]['total']          = ($batches[$key]['total'] ?? 0) + (float) $a->pay_rate;
        }

        foreach ($adjustments as $adj) {
            $key = $adj->reader_paid_at->toDateString();
            $batches[$key]['paid_at']      ??= $adj->reader_paid_at;
            $batches[$key]['assignments']  ??= [];
            $batches[$key]['adjustments'][] = $adj;
            $batches[$key]['total']         = ($batches[$key]['total'] ?? 0) + (float) $adj->amount;
        }

        usort($batches, fn($a, $b) => $b['paid_at'] <=> $a['paid_at']);

        return array_values($batches);
    }
}
