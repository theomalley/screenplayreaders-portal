<?php

// v2.1 — 2026-06-11 | Pass periodEnd (last day of current pay period) for PayPal payment ID
// v2.0 — 2026-06-11 | Consolidate Reader Pay + Editor Pay into Payroll: owed-so-far summary, unified searchable/sortable payment history
// v1.2 — 2026-06-04 | Current-period card, 1099 CSV export
// v1.1 — 2026-06-02 | Pass payout schedule config to view for admin schedule panel
// v1.0 — 2026-05-31 | Admin payroll dashboard — weekly payout history split by 1099 vs non-1099

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\EditorPayAdjustment;
use App\Models\OrderRevenue;
use App\Models\ReaderPayAdjustment;
use App\Models\Setting;
use App\Models\User;
use App\Support\PayPeriod;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollController extends Controller
{
    public static array $PERIODS = [
        'this_week'  => 'This Week',
        'last_week'  => 'Last Week',
        'this_month' => 'This Month',
        'last_month' => 'Last Month',
        'last_30'    => 'Last 30 Days',
        'last_60'    => 'Last 60 Days',
        'last_90'    => 'Last 90 Days',
        'this_year'  => 'This Year',
        'last_year'  => 'Last Year',
        'all_time'   => 'All Time',
    ];

    public function index()
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $period = request()->input('period', 'all_time');
        if (! array_key_exists($period, self::$PERIODS)) {
            $period = 'all_time';
        }

        $schedule   = Setting::getPayoutSchedule();
        $nextPayout = PayPeriod::nextPayoutDate();
        $periodEnd  = PayPeriod::current()[1];

        [$byReader, $readerPay1099, $readerPayNon1099] = $this->unpaidReaderSummary();
        [$unpaidOrders, $unpaidAdjustments, $editor, $weeklyFlat, $totalOwed] = $this->unpaidEditorSummary();

        $currentPeriod = [
            'label'        => PayPeriod::label(PayPeriod::current()[0]),
            'pay_1099'     => $readerPay1099,
            'pay_non_1099' => $readerPayNon1099,
            'editor_pay'   => $totalOwed,
            'total'        => $readerPay1099 + $readerPayNon1099 + $totalOwed,
        ];

        $items   = $this->buildPaidLineItems();
        $history = $this->buildHistory($items);

        return view('payroll.index', array_merge(compact(
            'period', 'schedule', 'nextPayout', 'periodEnd', 'currentPeriod',
            'byReader', 'unpaidOrders', 'unpaidAdjustments', 'editor', 'weeklyFlat', 'totalOwed'
        ), $history));
    }

    public function export1099(): StreamedResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $period = request()->input('period', 'all_time');
        if (! array_key_exists($period, self::$PERIODS)) {
            $period = 'all_time';
        }

        [$start, $end] = $this->dateRange($period);

        $assignments = Assignment::with(['assignedReader.readerProfile'])
            ->where('status', Assignment::STATUS_COMPLETED)
            ->where('vendor', 'sr')
            ->where('is_test', false)
            ->whereNotNull('reader_paid_at')
            ->whereHas('assignedReader.readerProfile', fn ($q) => $q->where('is_1099', true))
            ->when($start, fn ($q) => $q->where('reader_paid_at', '>=', $start))
            ->when($end,   fn ($q) => $q->where('reader_paid_at', '<=', $end))
            ->get();

        // Aggregate per reader
        $byReader = [];
        foreach ($assignments as $a) {
            $id      = $a->assigned_reader_id;
            $profile = $a->assignedReader?->readerProfile;
            if (! isset($byReader[$id])) {
                $byReader[$id] = [
                    'name'         => $profile?->displayName() ?? $a->assignedReader?->name ?? 'Unknown',
                    'paypal_email' => $profile?->paypal_email ?? '',
                    'count'        => 0,
                    'total'        => 0.0,
                ];
            }
            $byReader[$id]['count']++;
            $byReader[$id]['total'] += (float) $a->pay_rate;
        }

        usort($byReader, fn ($a, $b) => strcmp($a['name'], $b['name']));

        $periodLabel = self::$PERIODS[$period];
        $filename    = 'sr-1099-pay-' . $period . '-' . now()->format('Ymd') . '.csv';

        return response()->streamDownload(function () use ($byReader, $periodLabel) {
            $f = fopen('php://output', 'w');
            fputcsv($f, ['SR 1099 Pay Report — ' . $periodLabel]);
            fputcsv($f, ['Generated', now()->format('M j, Y g:i A T')]);
            fputcsv($f, []);
            fputcsv($f, ['Name', 'PayPal Email', 'Assignments', 'Total Paid']);
            foreach ($byReader as $row) {
                fputcsv($f, [
                    $row['name'],
                    $row['paypal_email'],
                    $row['count'],
                    number_format($row['total'], 2),
                ]);
            }
            $grandTotal = array_sum(array_column($byReader, 'total'));
            $grandCount = array_sum(array_column($byReader, 'count'));
            fputcsv($f, []);
            fputcsv($f, ['TOTAL', '', $grandCount, number_format($grandTotal, 2)]);
            fclose($f);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function unpaidReaderSummary(): array
    {
        $unpaidAssignments = Assignment::with(['assignedReader.readerProfile'])
            ->where('status', Assignment::STATUS_COMPLETED)
            ->where('vendor', 'sr')
            ->where('is_test', false)
            ->whereNull('reader_paid_at')
            ->whereNotNull('assigned_reader_id')
            ->orderBy('completed_at')
            ->get();

        $unpaidAdjustments = ReaderPayAdjustment::with(['reader.readerProfile', 'addedBy'])
            ->whereNull('reader_paid_at')
            ->orderBy('created_at')
            ->get();

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
                'is_1099'      => (bool) ($profile?->is_1099 ?? false),
                'assignments'  => $assignments->sortBy('completed_at'),
                'adjustments'  => $adjustments->sortBy('created_at'),
                'total_owed'   => $totalOwed,
            ];
        })->sortBy('reader_name')->values();

        $pay1099 = 0.0;
        $payNon1099 = 0.0;
        foreach ($byReader as $rd) {
            if ($rd['is_1099']) {
                $pay1099 += $rd['total_owed'];
            } else {
                $payNon1099 += $rd['total_owed'];
            }
        }

        return [$byReader, $pay1099, $payNon1099];
    }

    private function unpaidEditorSummary(): array
    {
        $unpaidOrders = OrderRevenue::whereNull('editor_paid_at')
            ->where('skip_commission', false)
            ->where('cog_commission', '>', 0)
            ->orderBy('ordered_at')
            ->get();

        $unpaidAdjustments = EditorPayAdjustment::with('addedBy')
            ->whereNull('editor_paid_at')
            ->orderBy('created_at')
            ->get();

        $orderTotal      = $unpaidOrders->sum(fn($o) => (float) $o->cog_commission);
        $adjustmentTotal = $unpaidAdjustments->sum(fn($a) => (float) $a->amount);
        $totalOwed       = round($orderTotal + $adjustmentTotal, 2);

        $editor = User::where('role', 'editor')->whereHas('editorProfile')->first();
        $weeklyFlat = (float) ($editor?->editorProfile?->editor_weekly_flat ?? 0.0);

        return [$unpaidOrders, $unpaidAdjustments, $editor, $weeklyFlat, $totalOwed];
    }

    private function buildPaidLineItems(): array
    {
        $items = [];

        $paidAssignments = Assignment::with(['assignedReader.readerProfile'])
            ->where('status', Assignment::STATUS_COMPLETED)
            ->where('vendor', 'sr')
            ->where('is_test', false)
            ->whereNotNull('reader_paid_at')
            ->whereNotNull('assigned_reader_id')
            ->get();

        foreach ($paidAssignments as $a) {
            $profile = $a->assignedReader?->readerProfile;
            $items[] = [
                'type'            => 'reader_coverage',
                'type_label'      => 'Coverage',
                'paid_at'         => $a->reader_paid_at,
                'person_id'       => $a->assigned_reader_id,
                'person_type'     => 'reader',
                'person_name'     => $profile?->displayName() ?? $a->assignedReader?->name ?? 'Unknown',
                'person_initials' => $profile?->initials ?? '?',
                'photo_url'       => $profile?->photo ? asset('storage/' . $profile->photo) : null,
                'order_number'    => $a->order_number,
                'detail'          => $a->script_title,
                'writer'          => $a->writer_name,
                'amount'          => (float) $a->pay_rate,
            ];
        }

        $paidReaderAdj = ReaderPayAdjustment::with(['reader.readerProfile'])
            ->whereNotNull('reader_paid_at')
            ->get();

        foreach ($paidReaderAdj as $adj) {
            $profile = $adj->reader?->readerProfile;
            $items[] = [
                'type'            => 'reader_adjustment',
                'type_label'      => 'Adjustment',
                'paid_at'         => $adj->reader_paid_at,
                'person_id'       => $adj->user_id,
                'person_type'     => 'reader',
                'person_name'     => $profile?->displayName() ?? $adj->reader?->name ?? 'Unknown',
                'person_initials' => $profile?->initials ?? '?',
                'photo_url'       => $profile?->photo ? asset('storage/' . $profile->photo) : null,
                'order_number'    => null,
                'detail'          => $adj->description,
                'writer'          => null,
                'amount'          => (float) $adj->amount,
            ];
        }

        $editor          = User::where('role', 'editor')->whereHas('editorProfile')->first();
        $editorProfile   = $editor?->editorProfile;
        $editorName      = $editorProfile?->displayName() ?? $editor?->name ?? 'Editor';
        $editorInitials  = $editorProfile?->initials ?? '?';
        $editorPhoto     = $editorProfile?->photo ? asset('storage/' . $editorProfile->photo) : null;

        $paidOrders = OrderRevenue::whereNotNull('editor_paid_at')
            ->where('cog_commission', '>', 0)
            ->get();

        foreach ($paidOrders as $o) {
            $items[] = [
                'type'            => 'editor_commission',
                'type_label'      => 'Commission',
                'paid_at'         => $o->editor_paid_at,
                'person_id'       => $editor?->id,
                'person_type'     => 'editor',
                'person_name'     => $editorName,
                'person_initials' => $editorInitials,
                'photo_url'       => $editorPhoto,
                'order_number'    => $o->order_number,
                'detail'          => $o->services_purchased,
                'writer'          => null,
                'amount'          => (float) $o->cog_commission,
            ];
        }

        $paidEditorAdj = EditorPayAdjustment::whereNotNull('editor_paid_at')->get();
        foreach ($paidEditorAdj as $adj) {
            $items[] = [
                'type'            => 'editor_adjustment',
                'type_label'      => 'Adjustment',
                'paid_at'         => $adj->editor_paid_at,
                'person_id'       => $editor?->id,
                'person_type'     => 'editor',
                'person_name'     => $editorName,
                'person_initials' => $editorInitials,
                'photo_url'       => $editorPhoto,
                'order_number'    => null,
                'detail'          => $adj->description,
                'writer'          => null,
                'amount'          => (float) $adj->amount,
            ];
        }

        return $items;
    }

    private function buildHistory(array $items): array
    {
        $search = trim((string) request()->input('q', ''));
        $sort   = request()->input('sort', 'date');
        if (! in_array($sort, ['date', 'reader'], true)) {
            $sort = 'date';
        }

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $items = array_values(array_filter($items, function ($item) use ($needle) {
                $haystacks = [
                    $item['order_number'],
                    $item['detail'],
                    $item['writer'],
                    $item['person_name'],
                    $item['person_initials'],
                    number_format($item['amount'], 2),
                ];
                foreach ($haystacks as $h) {
                    if ($h !== null && str_contains(mb_strtolower((string) $h), $needle)) {
                        return true;
                    }
                }
                return false;
            }));
        }

        if ($search !== '' || $sort === 'reader') {
            usort($items, function ($a, $b) use ($sort) {
                if ($sort === 'reader') {
                    $cmp = strcasecmp($a['person_name'], $b['person_name']);
                    if ($cmp !== 0) {
                        return $cmp;
                    }
                }
                return $b['paid_at'] <=> $a['paid_at'];
            });

            $perPage    = 25;
            $totalPages = max(1, (int) ceil(count($items) / $perPage));
            $page       = min(max(1, (int) request()->input('history_page', 1)), $totalPages);

            return [
                'historyMode'       => 'flat',
                'historyItems'      => array_slice($items, ($page - 1) * $perPage, $perPage),
                'historyBatches'    => [],
                'historyPage'       => $page,
                'historyTotalPages' => $totalPages,
                'search'            => $search,
                'sort'              => $sort,
            ];
        }

        usort($items, fn($a, $b) => $b['paid_at'] <=> $a['paid_at']);

        $batches = [];
        foreach ($items as $item) {
            $key = $item['paid_at']->toDateString();
            $batches[$key]['paid_at'] ??= $item['paid_at'];
            $batches[$key]['items'][] = $item;
            $batches[$key]['total']   = ($batches[$key]['total'] ?? 0) + $item['amount'];
        }
        usort($batches, fn($a, $b) => $b['paid_at'] <=> $a['paid_at']);
        $batches = array_values($batches);

        $perPage    = 10;
        $totalPages = max(1, (int) ceil(count($batches) / $perPage));
        $page       = min(max(1, (int) request()->input('history_page', 1)), $totalPages);

        return [
            'historyMode'       => 'batches',
            'historyItems'      => [],
            'historyBatches'    => array_slice($batches, ($page - 1) * $perPage, $perPage),
            'historyPage'       => $page,
            'historyTotalPages' => $totalPages,
            'search'            => $search,
            'sort'              => $sort,
        ];
    }

    private function dateRange(string $period): array
    {
        $tz  = config('app.timezone', 'America/Los_Angeles');
        $now = Carbon::now($tz);

        return match ($period) {
            'this_week'  => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'last_week'  => [$now->copy()->subWeek()->startOfWeek(), $now->copy()->subWeek()->endOfWeek()],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'last_month' => [$now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth()],
            'last_30'    => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            'last_60'    => [$now->copy()->subDays(59)->startOfDay(), $now->copy()->endOfDay()],
            'last_90'    => [$now->copy()->subDays(89)->startOfDay(), $now->copy()->endOfDay()],
            'this_year'  => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'last_year'  => [$now->copy()->subYear()->startOfYear(), $now->copy()->subYear()->endOfYear()],
            'all_time'   => [null, null],
        };
    }
}
