<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">My Earnings</h2>
            <form method="GET" action="{{ route('reader-earnings.index') }}">
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
                        ['Total Earned',  '$' . number_format($totals['earned'],  2), 'bg-indigo-50 border-indigo-200 text-indigo-700'],
                        ['Paid Out',      '$' . number_format($totals['paid'],    2), 'bg-green-50  border-green-200  text-green-700'],
                        ['Pending Pay',   '$' . number_format($totals['pending'], 2), 'bg-amber-50  border-amber-200  text-amber-700'],
                        ['Assignments',   $totals['count'],                           'bg-gray-50   border-gray-200   text-gray-600'],
                    ];
                @endphp
                @foreach($cards as [$label, $value, $classes])
                    <div class="rounded-lg border px-4 py-3 {{ $classes }}">
                        <div class="text-xs font-medium uppercase tracking-wide opacity-70">{{ $label }}</div>
                        <div class="mt-1 text-xl font-semibold">{{ $value }}</div>
                    </div>
                @endforeach
            </div>

            {{-- Avg chip --}}
            @if($totals['count'] > 0)
            <div class="text-sm text-gray-500">
                {{ $totals['count'] }} assignment{{ $totals['count'] === 1 ? '' : 's' }} completed in period
                &nbsp;&middot;&nbsp; avg ${{ number_format($totals['earned'] / $totals['count'], 2) }} per assignment
            </div>
            @endif

            {{-- Chart --}}
            @if(count($chartData['labels']) > 0)
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                <canvas id="earningsChart" height="80"></canvas>
            </div>
            @endif

            {{-- Assignments table --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                @if($assignments->isEmpty())
                    <div class="px-6 py-12 text-center text-gray-400 text-sm">No completed assignments in this period.</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-xs font-medium text-gray-500 uppercase tracking-wide">
                                <tr>
                                    <th class="px-4 py-3 text-left">Completed</th>
                                    <th class="px-4 py-3 text-left">Order #</th>
                                    <th class="px-4 py-3 text-left">Script</th>
                                    <th class="px-4 py-3 text-left">Type</th>
                                    <th class="px-4 py-3 text-right">Pay</th>
                                    <th class="px-4 py-3 text-left">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($assignments as $a)
                                    @php
                                        $typeLabel = match($a->assignment_type) {
                                            'script_coverage'   => 'Script Coverage',
                                            'notes_only'        => 'Notes-Only',
                                            'deep_dive'         => 'Deep-Dive',
                                            'short'             => 'Short',
                                            'budget'            => 'Budget',
                                            'book'              => 'Book',
                                            'coverage'          => 'Coverage',
                                            'development_notes' => 'Dev Notes',
                                            default             => ucfirst(str_replace('_', ' ', $a->assignment_type ?? '—')),
                                        };
                                        if ($a->vendor === 'wd') $typeLabel = 'WD ' . $typeLabel;
                                    @endphp
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-4 py-3 text-gray-500 whitespace-nowrap">{{ $a->completed_at->format('M j, Y') }}</td>
                                        <td class="px-4 py-3 font-mono text-xs text-gray-700">{{ $a->order_number }}</td>
                                        <td class="px-4 py-3 text-gray-700 max-w-xs truncate" title="{{ $a->script_title }}">{{ $a->script_title }}</td>
                                        <td class="px-4 py-3 text-gray-500 text-xs whitespace-nowrap">{{ $typeLabel }}</td>
                                        <td class="px-4 py-3 text-right font-semibold text-indigo-700">${{ number_format($a->pay_rate, 2) }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            @if($a->reader_paid_at)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                                    Paid {{ $a->reader_paid_at->format('M j') }}
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
                                    <td colspan="4" class="px-4 py-3 text-gray-600">Totals ({{ $totals['count'] }} assignments)</td>
                                    <td class="px-4 py-3 text-right text-indigo-700">${{ number_format($totals['earned'], 2) }}</td>
                                    <td class="px-4 py-3 text-gray-500 text-xs">
                                        ${{ number_format($totals['paid'], 2) }} paid · ${{ number_format($totals['pending'], 2) }} pending
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
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
                        fill: true,
                        tension: 0.3,
                        pointRadius: labels.length > 60 ? 0 : 3,
                    },
                    {
                        label: 'Paid Out',
                        data: paid,
                        borderColor: 'rgb(34,197,94)',
                        backgroundColor: 'rgba(34,197,94,0.08)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: labels.length > 60 ? 0 : 3,
                    },
                ],
            },
            options: {
                responsive: true,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { callback: v => '$' + v.toLocaleString() },
                    },
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: ctx => ctx.dataset.label + ': $' + ctx.parsed.y.toLocaleString('en-US', { minimumFractionDigits: 2 }),
                        },
                    },
                    legend: { position: 'top' },
                },
            },
        });
    })();
    </script>
    @endpush
    @endif

</x-app-layout>
