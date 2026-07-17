<?php

// v1.1 — 2026-07-17 | Scope $unpaidOrders/$paidOrders by editor_id = $user->id — previously
//                     showed every editor's commissions to whichever editor viewed the page.
// v1.0 — 2026-05-25 | Editor-facing payments view — pending commissions, adjustments, history

namespace App\Http\Controllers;

use App\Models\EditorPayAdjustment;
use App\Models\OrderRevenue;
use App\Support\PayPeriod;
use Carbon\Carbon;

class EditorPaymentsController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        abort_unless($user->isEditor(), 403);

        [$currentStart, $currentEnd] = PayPeriod::current();

        // Unpaid commissions grouped by pay period
        $unpaidOrders = OrderRevenue::where('editor_id', $user->id)
            ->whereNull('editor_paid_at')
            ->where('skip_commission', false)
            ->where('cog_commission', '>', 0)
            ->orderBy('ordered_at')
            ->get();

        $unpaidAdjustments = EditorPayAdjustment::where('user_id', $user->id)
            ->whereNull('editor_paid_at')
            ->orderBy('created_at')
            ->get();

        $unpaidPeriods = $this->groupIntoPeriods($unpaidOrders, $unpaidAdjustments);

        $currentPeriod = null;
        $priorPeriods  = [];

        foreach ($unpaidPeriods as $period) {
            if ($period['period_start']->eq($currentStart)) {
                $currentPeriod = $period;
            } else {
                $priorPeriods[] = $period;
            }
        }

        // Paid history
        $paidOrders = OrderRevenue::where('editor_id', $user->id)
            ->whereNotNull('editor_paid_at')
            ->where('cog_commission', '>', 0)
            ->orderByDesc('editor_paid_at')
            ->get();

        $paidAdjustments = EditorPayAdjustment::where('user_id', $user->id)
            ->whereNotNull('editor_paid_at')
            ->orderByDesc('editor_paid_at')
            ->get();

        $historyBatches = $this->buildHistoryBatches($paidOrders, $paidAdjustments);

        $page       = max(1, (int) request()->input('page', 1));
        $perPage    = 8;
        $totalPages = max(1, (int) ceil(count($historyBatches) / $perPage));
        $page       = min($page, $totalPages);
        $history    = array_slice($historyBatches, ($page - 1) * $perPage, $perPage);

        return view('editor-payments.index', compact(
            'currentPeriod', 'priorPeriods', 'history', 'page', 'totalPages', 'currentStart', 'currentEnd'
        ));
    }

    private function groupIntoPeriods($orders, $adjustments): array
    {
        $periods = [];

        foreach ($orders as $o) {
            $start = PayPeriod::start($o->ordered_at);
            $key   = $start->toDateTimeString();
            $periods[$key]['period_start'] ??= $start;
            $periods[$key]['period_end']   ??= PayPeriod::end($start);
            $periods[$key]['orders'][]      = $o;
            $periods[$key]['adjustments']  ??= [];
            $periods[$key]['total']         = ($periods[$key]['total'] ?? 0) + (float) $o->cog_commission;
        }

        foreach ($adjustments as $adj) {
            $start = PayPeriod::start($adj->created_at);
            $key   = $start->toDateTimeString();
            $periods[$key]['period_start'] ??= $start;
            $periods[$key]['period_end']   ??= PayPeriod::end($start);
            $periods[$key]['orders']       ??= [];
            $periods[$key]['adjustments'][] = $adj;
            $periods[$key]['total']         = ($periods[$key]['total'] ?? 0) + (float) $adj->amount;
        }

        usort($periods, fn($a, $b) => $b['period_start'] <=> $a['period_start']);

        return array_values($periods);
    }

    private function buildHistoryBatches($orders, $adjustments): array
    {
        $batches = [];

        foreach ($orders as $o) {
            $key = $o->editor_paid_at->toDateString();
            $batches[$key]['paid_at']     ??= $o->editor_paid_at;
            $batches[$key]['orders'][]     = $o;
            $batches[$key]['adjustments'] ??= [];
            $batches[$key]['total']        = ($batches[$key]['total'] ?? 0) + (float) $o->cog_commission;
        }

        foreach ($adjustments as $adj) {
            $key = $adj->editor_paid_at->toDateString();
            $batches[$key]['paid_at']      ??= $adj->editor_paid_at;
            $batches[$key]['orders']       ??= [];
            $batches[$key]['adjustments'][] = $adj;
            $batches[$key]['total']         = ($batches[$key]['total'] ?? 0) + (float) $adj->amount;
        }

        usort($batches, fn($a, $b) => $b['paid_at'] <=> $a['paid_at']);

        return array_values($batches);
    }
}
