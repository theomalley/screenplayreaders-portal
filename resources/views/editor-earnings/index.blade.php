<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">My Earnings</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">

            @include('partials._pay-profile-header', [
                'name'        => $profileName,
                'initials'    => $profileInitials,
                'photoUrl'    => $profilePhotoUrl,
                'paypalEmail' => $profilePaypalEmail,
                'color'       => 'blue',
            ])

            {{-- ── CURRENT PAY PERIOD ── --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-indigo-100 bg-indigo-50 flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-sm font-semibold text-indigo-700 uppercase tracking-wide">
                        Current Pay Period &middot; {{ $current['label'] }}
                    </h3>
                    <span class="text-sm text-indigo-700">
                        Next payout: <span class="font-semibold">{{ $current['payout_date']->format('M j, Y') }}</span>
                    </span>
                </div>

                <div class="p-5 space-y-4">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        @php
                            $cards = [
                                ['Commission Earned',  '$' . number_format($current['total'],  2), 'bg-indigo-50 border-indigo-200 text-indigo-700'],
                                ['Paid Out',           '$' . number_format($current['paid_total'],    2), 'bg-green-50  border-green-200  text-green-700'],
                                ['Pending',            '$' . number_format($current['pending_total'], 2), 'bg-amber-50  border-amber-200  text-amber-700'],
                                ['Orders',             count($current['orders']),                        'bg-gray-50   border-gray-200   text-gray-600'],
                            ];
                        @endphp
                        @foreach($cards as [$label, $value, $classes])
                            <div class="rounded-lg border px-4 py-3 {{ $classes }}">
                                <div class="text-xs font-medium uppercase tracking-wide opacity-70">{{ $label }}</div>
                                <div class="mt-1 text-xl font-semibold">{{ $value }}</div>
                            </div>
                        @endforeach
                    </div>

                    @if(count($current['orders']) > 0 || count($current['adjustments']) > 0)
                        <div class="text-sm text-gray-500">
                            This period:
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 ml-1">
                                {{ count($current['orders']) }} commission(s)
                            </span>
                            @if(count($current['adjustments']) > 0)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 ml-1">
                                    {{ count($current['adjustments']) }} adjustment(s)
                                </span>
                            @endif
                        </div>
                    @else
                        <div class="text-sm text-gray-400">No commissions or adjustments yet this period.</div>
                    @endif
                </div>
            </div>

            {{-- Chart --}}
            @if(count($chartData['labels']) > 0)
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                <canvas id="earningsChart" height="80"></canvas>
            </div>
            @endif

            {{-- ── PAY PERIOD HISTORY ── --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wide">Pay Period History</h3>
                    @if($totalPages > 1)
                        <span class="text-xs text-gray-400">Page {{ $page }} of {{ $totalPages }}</span>
                    @endif
                </div>

                @if(empty($history))
                    <div class="px-6 py-8 text-center text-gray-400 text-sm">No commissions or adjustments in prior pay periods yet.</div>
                @else
                    <div class="divide-y divide-gray-100">
                        @foreach($history as $period)
                        <details class="group">
                            <summary class="flex items-center justify-between px-5 py-3 cursor-pointer hover:bg-gray-50 list-none">
                                <div class="flex items-center gap-4">
                                    <span class="text-sm font-medium text-gray-700">{{ $period['label'] }}</span>
                                    <span class="text-xs text-gray-400">
                                        {{ count($period['orders']) }} commission(s)
                                        @if(count($period['adjustments']) > 0)
                                            + {{ count($period['adjustments']) }} adj.
                                        @endif
                                    </span>
                                    <span class="text-xs text-gray-400">
                                        ${{ number_format($period['paid_total'], 2) }} paid
                                        @if($period['pending_total'] > 0)
                                            &middot; ${{ number_format($period['pending_total'], 2) }} pending
                                        @endif
                                    </span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="font-semibold {{ $period['total'] >= 0 ? 'text-indigo-700' : 'text-red-600' }}">
                                        ${{ number_format($period['total'], 2) }}
                                    </span>
                                    <svg class="w-4 h-4 text-gray-400 group-open:rotate-180 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </div>
                            </summary>
                            <div class="border-t border-gray-100 bg-gray-50 overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach($period['orders'] as $o)
                                        <tr>
                                            <td class="px-6 py-2 text-gray-500 text-xs w-24">Commission</td>
                                            <td class="px-4 py-2 font-mono text-xs text-gray-600">{{ $o->order_number }}</td>
                                            <td class="px-4 py-2 text-gray-500 text-xs">{{ $o->ordered_at->format('M j') }}</td>
                                            <td class="px-4 py-2 text-gray-400 text-xs truncate max-w-xs">{{ $o->services_purchased }}</td>
                                            <td class="px-4 py-2 text-right text-gray-700">${{ number_format($o->cog_commission, 2) }}</td>
                                            <td class="px-4 py-2 whitespace-nowrap">
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
                                        @foreach($period['adjustments'] as $adj)
                                        <tr>
                                            <td class="px-6 py-2 text-indigo-500 text-xs w-24">Adjustment</td>
                                            <td class="px-4 py-2 text-gray-700" colspan="3">{{ $adj->description }}</td>
                                            <td class="px-4 py-2 text-right {{ (float)$adj->amount >= 0 ? 'text-green-700' : 'text-red-600' }}">
                                                {{ (float)$adj->amount >= 0 ? '+' : '' }}${{ number_format($adj->amount, 2) }}
                                            </td>
                                            <td class="px-4 py-2 whitespace-nowrap">
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
                        </details>
                        @endforeach
                    </div>

                    {{-- Pagination --}}
                    @if($totalPages > 1)
                    <div class="flex items-center justify-between px-5 py-3 border-t border-gray-100 text-sm">
                        @if($page > 1)
                            <a href="{{ request()->fullUrlWithQuery(['page' => $page - 1]) }}" class="text-indigo-600 hover:text-indigo-800">&larr; Newer</a>
                        @else
                            <span></span>
                        @endif
                        @if($page < $totalPages)
                            <a href="{{ request()->fullUrlWithQuery(['page' => $page + 1]) }}" class="text-indigo-600 hover:text-indigo-800">Older &rarr;</a>
                        @endif
                    </div>
                    @endif
                @endif
            </div>

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
