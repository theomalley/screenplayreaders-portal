<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">My Earnings</h2>
            <form method="GET" action="{{ route('editor-earnings.index') }}">
                <select name="period" onchange="this.form.submit()"
                    class="text-sm border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                    @foreach(\App\Http\Controllers\RevenueController::$PERIODS as $key => $label)
                        <option value="{{ $key }}" @selected($period === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </form>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">

            {{-- Summary cards --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                @php
                    $cards = [
                        ['Commission Earned',  '$' . number_format($totals['commission_earned'],  2), 'bg-indigo-50 border-indigo-200 text-indigo-700'],
                        ['Paid Out',           '$' . number_format($totals['commission_paid'],    2), 'bg-green-50  border-green-200  text-green-700'],
                        ['Commission Pending', '$' . number_format($totals['commission_pending'], 2), 'bg-amber-50  border-amber-200  text-amber-700'],
                        ['Orders',             $totals['order_count'],                               'bg-gray-50   border-gray-200   text-gray-600'],
                    ];
                @endphp
                @foreach($cards as [$label, $value, $classes])
                    <div class="rounded-lg border px-4 py-3 {{ $classes }}">
                        <div class="text-xs font-medium uppercase tracking-wide opacity-70">{{ $label }}</div>
                        <div class="mt-1 text-xl font-semibold">{{ $value }}</div>
                    </div>
                @endforeach
            </div>

            @if($totals['adjustment_total'] != 0)
            <div class="text-sm text-gray-500">
                Adjustments in period:
                <span class="{{ $totals['adjustment_total'] > 0 ? 'text-green-600' : 'text-red-500' }} font-medium">
                    {{ $totals['adjustment_total'] > 0 ? '+' : '' }}${{ number_format(abs($totals['adjustment_total']), 2) }}
                </span>
                &nbsp;&middot;&nbsp; Total pending incl. adjustments:
                <span class="font-medium text-gray-700">${{ number_format($totals['total_pending'], 2) }}</span>
            </div>
            @endif

            {{-- Chart --}}
            @if(count($chartData['labels']) > 0)
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                <canvas id="earningsChart" height="80"></canvas>
            </div>
            @endif

            {{-- Orders / commissions table --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100">
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Commissions</h3>
                </div>
                @if($orders->isEmpty())
                    <div class="px-6 py-8 text-center text-gray-400 text-sm">No commission orders in this period.</div>
                @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs font-medium text-gray-500 uppercase tracking-wide">
                            <tr>
                                <th class="px-4 py-3 text-left">Date</th>
                                <th class="px-4 py-3 text-left">Order #</th>
                                <th class="px-4 py-3 text-left">Description</th>
                                <th class="px-4 py-3 text-right">Commission</th>
                                <th class="px-4 py-3 text-left">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($orders as $o)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3 text-gray-500 whitespace-nowrap">{{ $o->ordered_at?->format('M j, Y') ?? '—' }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-gray-700">{{ $o->order_number }}</td>
                                <td class="px-4 py-3 text-gray-700 max-w-xs truncate" title="{{ $o->services_purchased }}">{{ $o->services_purchased }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-indigo-700">${{ number_format($o->cog_commission, 2) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if($o->editor_paid_at)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                            Paid {{ $o->editor_paid_at->format('M j') }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">
                                            Pending
                                        </span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50 border-t-2 border-gray-300 text-sm font-semibold">
                            <tr>
                                <td colspan="3" class="px-4 py-3 text-gray-600">Totals ({{ $totals['order_count'] }} orders)</td>
                                <td class="px-4 py-3 text-right text-indigo-700">${{ number_format($totals['commission_earned'], 2) }}</td>
                                <td class="px-4 py-3 text-gray-500 text-xs">
                                    ${{ number_format($totals['commission_paid'], 2) }} paid · ${{ number_format($totals['commission_pending'], 2) }} pending
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @endif
            </div>

            {{-- Adjustments table --}}
            @if($adjustments->isNotEmpty())
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100">
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Adjustments</h3>
                </div>
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-xs font-medium text-gray-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-4 py-3 text-left">Date</th>
                            <th class="px-4 py-3 text-left">Description</th>
                            <th class="px-4 py-3 text-right">Amount</th>
                            <th class="px-4 py-3 text-left">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($adjustments as $adj)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-gray-500 whitespace-nowrap">{{ $adj->created_at->format('M j, Y') }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $adj->description }}</td>
                            <td class="px-4 py-3 text-right font-semibold {{ (float)$adj->amount >= 0 ? 'text-green-700' : 'text-red-600' }}">
                                {{ (float)$adj->amount >= 0 ? '+' : '' }}${{ number_format(abs((float)$adj->amount), 2) }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if($adj->editor_paid_at)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                        Paid {{ $adj->editor_paid_at->format('M j') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">
                                        Pending
                                    </span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

        </div>
    </div>

    @if(count($chartData['labels']) > 0)
    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <script>
    (function () {
        const labels = @js($chartData['labels']);
        const earned = @js($chartData['earned']);
        const paid   = @js($chartData['paid']);

        new Chart(document.getElementById('earningsChart'), {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Earned',
                        data: earned,
                        borderColor: 'rgb(99,102,241)',
                        backgroundColor: 'rgba(99,102,241,0.08)',
                        fill: true, tension: 0.3,
                        pointRadius: labels.length > 60 ? 0 : 3,
                    },
                    {
                        label: 'Paid Out',
                        data: paid,
                        borderColor: 'rgb(34,197,94)',
                        backgroundColor: 'rgba(34,197,94,0.08)',
                        fill: true, tension: 0.3,
                        pointRadius: labels.length > 60 ? 0 : 3,
                    },
                ],
            },
            options: {
                responsive: true,
                interaction: { mode: 'index', intersect: false },
                scales: { y: { beginAtZero: true, ticks: { callback: v => '$' + v.toLocaleString() } } },
                plugins: {
                    tooltip: { callbacks: { label: ctx => ctx.dataset.label + ': $' + ctx.parsed.y.toLocaleString('en-US', { minimumFractionDigits: 2 }) } },
                    legend: { position: 'top' },
                },
            },
        });
    })();
    </script>
    @endpush
    @endif

</x-app-layout>
