<?php

// v2.4 — 2026-07-11 | Exclude is_test assignments — the tier-0 onboarding sandbox must never
//                     appear as real completed work/pay on a reader's own earnings dashboard.
// v2.3 — 2026-06-12 | Eager-load coverageSubmission so line items can show turnaround/page count/view-coverage details
// v2.2 — 2026-06-11 | Pass periodEnd so the PayPal payment ID reflects the pay period's last day
// v2.1 — 2026-06-11 | Pass profile header data (photo, initials, PayPal) for "My Earnings" card
// v2.0 — 2026-06-10 | Restructure around pay periods — current period summary + paginated collapsible history
// v1.0 — 2026-05-29 | Reader earnings dashboard — time-period aggregates and Chart.js data

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Support\PayPeriod;
use Carbon\Carbon;

class ReaderEarningsController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        abort_unless($user->isReader(), 403);

        $assignments = Assignment::where('assigned_reader_id', $user->id)
            ->where('status', Assignment::STATUS_COMPLETED)
            ->where('is_test', false)
            ->whereNotNull('completed_at')
            ->with('coverageSubmission')
            ->orderByDesc('completed_at')
            ->get();

        $periods = $this->groupByPayPeriod($assignments);

        [$curStart, $curEnd] = PayPeriod::current();
        $curKey = $curStart->toDateString();

        $current = $periods[$curKey] ?? $this->emptyPeriod($curStart, $curEnd);
        unset($periods[$curKey]);

        $current['label']        = PayPeriod::label($curStart);
        $current['payout_date']  = PayPeriod::nextPayoutDate();
        $current['type_counts']  = $this->typeCounts($current['assignments']);

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

        $profile            = $user->readerProfile;
        $profileName        = $profile?->displayName() ?? $user->name;
        $profileInitials    = $profile?->initials ?? '?';
        $profilePhotoUrl    = $profile?->photo ? asset('storage/' . $profile->photo) : null;
        $profilePaypalEmail = $profile?->paypal_email;
        $periodEnd          = $curEnd;

        return view('reader-earnings.index', compact(
            'current', 'history', 'page', 'totalPages', 'chartData',
            'profileName', 'profileInitials', 'profilePhotoUrl', 'profilePaypalEmail', 'periodEnd'
        ));
    }

    private function groupByPayPeriod($assignments): array
    {
        $periods = [];

        foreach ($assignments as $a) {
            [$start, $end] = PayPeriod::bounds($a->completed_at);
            $key = $start->toDateString();

            $periods[$key]['start']        ??= $start;
            $periods[$key]['end']          ??= $end;
            $periods[$key]['assignments'][] = $a;
            $periods[$key]['count']         = ($periods[$key]['count']         ?? 0) + 1;
            $periods[$key]['total']         = ($periods[$key]['total']         ?? 0) + (float) $a->pay_rate;
            $periods[$key]['paid_total']    = ($periods[$key]['paid_total']    ?? 0) + ($a->reader_paid_at ? (float) $a->pay_rate : 0);
            $periods[$key]['pending_total'] = ($periods[$key]['pending_total'] ?? 0) + ($a->reader_paid_at ? 0 : (float) $a->pay_rate);
        }

        return $periods;
    }

    private function emptyPeriod(Carbon $start, Carbon $end): array
    {
        return [
            'start' => $start, 'end' => $end, 'assignments' => [],
            'count' => 0, 'total' => 0, 'paid_total' => 0, 'pending_total' => 0,
        ];
    }

    /** Count completed assignments in a period by type, for the "this period" breakdown. */
    private function typeCounts($assignments): array
    {
        $labels = [
            'script_coverage'   => 'Script Coverage',
            'notes_only'        => 'Notes-Only',
            'deep_dive'         => 'Advanced Script Coverage',
            'short'             => 'Short',
            'budget'            => 'Budget Coverage',
            'book'              => 'Book',
            'coverage'          => 'Coverage',
            'development_notes' => 'Dev Notes',
        ];

        $counts = [];
        foreach ($assignments as $a) {
            $label = $labels[$a->assignment_type] ?? ucfirst(str_replace('_', ' ', $a->assignment_type ?? '—'));
            if ($a->vendor === 'wd') $label = 'WD ' . $label;
            $counts[$label] = ($counts[$label] ?? 0) + 1;
        }

        arsort($counts);

        return $counts;
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
