<?php

// v2.2 — 2026-06-11 | Pass periodEnd so the PayPal payment ID reflects the pay period's last day
// v2.1 — 2026-06-11 | Pass profile header data (photo, initials, PayPal) for "My Earnings" card
// v2.0 — 2026-06-10 | Restructure around pay periods — current period summary + paginated collapsible history
// v1.0 — 2026-06-02 | Editor earnings dashboard — commission and adjustment history with Chart.js

namespace App\Http\Controllers;

use App\Models\EditorPayAdjustment;
use App\Models\OrderRevenue;
use App\Support\PayPeriod;
use Carbon\Carbon;

class EditorEarningsController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        abort_unless($user->isAdminOrEditor(), 403);

        $orders = OrderRevenue::where('cog_commission', '>', 0)
            ->whereNotNull('ordered_at')
            ->orderByDesc('ordered_at')
            ->get();

        $adjustments = EditorPayAdjustment::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();

        $periods = $this->groupByPayPeriod($orders, $adjustments);

        [$curStart, $curEnd] = PayPeriod::current();
        $curKey = $curStart->toDateString();

        $current = $periods[$curKey] ?? $this->emptyPeriod($curStart, $curEnd);
        unset($periods[$curKey]);

        $current['label']       = PayPeriod::label($curStart);
        $current['payout_date'] = PayPeriod::nextPayoutDate();

        $historyAll = collect($periods)
            ->map(function ($p) {
                $p['label'] = PayPeriod::label($p['start']);
                return $p;
            })
            ->sortByDesc(fn($p) => $p['start'])
            ->values()
            ->all();

        $page       = max(1, (int) request()->input('page', 1));
        $perPage    = 25;
        $totalPages = max(1, (int) ceil(count($historyAll) / $perPage));
        $page       = min($page, $totalPages);
        $history    = array_slice($historyAll, ($page - 1) * $perPage, $perPage);

        $chartData = $this->buildChartData($current, $historyAll);

        $profile            = $user->editorProfile;
        $profileName        = $profile?->displayName() ?? $user->name;
        $profileInitials    = $profile?->initials ?? '?';
        $profilePhotoUrl    = $profile?->photo ? asset('storage/' . $profile->photo) : null;
        $profilePaypalEmail = $profile?->paypal_email;
        $periodEnd          = $curEnd;

        return view('editor-earnings.index', compact(
            'current', 'history', 'page', 'totalPages', 'chartData',
            'profileName', 'profileInitials', 'profilePhotoUrl', 'profilePaypalEmail', 'periodEnd'
        ));
    }

    private function groupByPayPeriod($orders, $adjustments): array
    {
        $periods = [];

        foreach ($orders as $o) {
            [$start, $end] = PayPeriod::bounds($o->ordered_at);
            $key = $start->toDateString();

            $periods[$key]['start']        ??= $start;
            $periods[$key]['end']          ??= $end;
            $periods[$key]['orders'][]      = $o;
            $periods[$key]['adjustments']  ??= [];
            $periods[$key]['count']         = ($periods[$key]['count']         ?? 0) + 1;
            $periods[$key]['total']         = ($periods[$key]['total']         ?? 0) + (float) $o->cog_commission;
            $periods[$key]['paid_total']    = ($periods[$key]['paid_total']    ?? 0) + ($o->editor_paid_at ? (float) $o->cog_commission : 0);
            $periods[$key]['pending_total'] = ($periods[$key]['pending_total'] ?? 0) + ($o->editor_paid_at ? 0 : (float) $o->cog_commission);
        }

        foreach ($adjustments as $adj) {
            [$start, $end] = PayPeriod::bounds($adj->created_at);
            $key = $start->toDateString();
            $amount = (float) $adj->amount;

            $periods[$key]['start']        ??= $start;
            $periods[$key]['end']          ??= $end;
            $periods[$key]['orders']       ??= [];
            $periods[$key]['adjustments'][] = $adj;
            $periods[$key]['count']        ??= 0;
            $periods[$key]['total']         = ($periods[$key]['total']         ?? 0) + $amount;
            $periods[$key]['paid_total']    = ($periods[$key]['paid_total']    ?? 0) + ($adj->editor_paid_at ? $amount : 0);
            $periods[$key]['pending_total'] = ($periods[$key]['pending_total'] ?? 0) + ($adj->editor_paid_at ? 0 : $amount);
        }

        return $periods;
    }

    private function emptyPeriod(Carbon $start, Carbon $end): array
    {
        return [
            'start' => $start, 'end' => $end, 'orders' => [], 'adjustments' => [],
            'count' => 0, 'total' => 0, 'paid_total' => 0, 'pending_total' => 0,
        ];
    }

    /** Build Chart.js series across the most recent pay periods (current + up to 11 prior). */
    private function buildChartData(array $current, array $historyAll): array
    {
        $all = array_merge($historyAll, [$current]);
        usort($all, fn($a, $b) => $a['start'] <=> $b['start']);
        $recent = array_slice($all, -12);

        $labels = $earned = $paid = [];
        foreach ($recent as $p) {
            $labels[] = $p['label'] ?? PayPeriod::label($p['start']);
            $earned[] = round($p['total'], 2);
            $paid[]   = round($p['paid_total'], 2);
        }

        return compact('labels', 'earned', 'paid');
    }
}
