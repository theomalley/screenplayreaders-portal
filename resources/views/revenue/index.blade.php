<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Revenue</h2>
            {{-- Period selector --}}
            <form method="GET" action="{{ route('revenue.index') }}">
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
            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3">
                @php
                    $cards = [
                        ['Gross Revenue',   '$' . number_format($totals['gross'],    2), 'bg-indigo-50 border-indigo-200 text-indigo-700'],
                        ['Discounts',       '$' . number_format($totals['discount'], 2), 'bg-gray-50  border-gray-200  text-gray-500'],
                        ['Net Revenue',     '$' . number_format($totals['net'],      2), 'bg-green-50  border-green-200  text-green-700'],
                        ['Reader COG',      '$' . number_format($totals['cog_reader'], 2), 'bg-red-50  border-red-200  text-red-600'],
                        ['Processing COG',  '$' . number_format($totals['cog_proc'], 2), 'bg-orange-50 border-orange-200 text-orange-600'],
                        ['Commission COG',  '$' . number_format($totals['cog_comm'], 2), 'bg-yellow-50 border-yellow-200 text-yellow-700'],
                        ['Total COG',       '$' . number_format($totals['cog_total'], 2), 'bg-rose-50 border-rose-200 text-rose-700'],
                    ];
                @endphp
                @foreach($cards as [$label, $value, $classes])
                    <div class="rounded-lg border px-4 py-3 {{ $classes }}">
                        <div class="text-xs font-medium uppercase tracking-wide opacity-70">{{ $label }}</div>
                        <div class="mt-1 text-xl font-semibold">{{ $value }}</div>
                    </div>
                @endforeach
            </div>

            {{-- Orders count chip --}}
            <div class="text-sm text-gray-500">
                {{ $totals['count'] }} order{{ $totals['count'] === 1 ? '' : 's' }} in period
                @if($totals['count'] > 0 && $totals['gross'] > 0)
                    &nbsp;&middot;&nbsp; avg ${{ number_format($totals['gross'] / $totals['count'], 2) }} gross
                    &nbsp;&middot;&nbsp; avg ${{ number_format($totals['net'] / $totals['count'], 2) }} net
                @endif
            </div>

            {{-- Chart --}}
            @if(count($chartData['labels']) > 0)
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                <canvas id="revenueChart" height="80"></canvas>
            </div>
            @endif

            {{-- Orders table --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                @if($orders->isEmpty())
                    <div class="px-6 py-12 text-center text-gray-400 text-sm">No orders in this period.</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-xs font-medium text-gray-500 uppercase tracking-wide">
                                <tr>
                                    <th class="px-4 py-3 text-left">Date</th>
                                    <th class="px-4 py-3 text-left">Order #</th>
                                    <th class="px-4 py-3 text-left">Services</th>
                                    <th class="px-4 py-3 text-left">Coupon</th>
                                    <th class="px-4 py-3 text-right">Gross</th>
                                    <th class="px-4 py-3 text-right">Discount</th>
                                    <th class="px-4 py-3 text-right">Reader COG</th>
                                    <th class="px-4 py-3 text-right">Proc COG</th>
                                    <th class="px-4 py-3 text-right">Comm COG</th>
                                    <th class="px-4 py-3 text-right">Total COG</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-700">Net</th>
                                    <th class="px-4 py-3 text-left">Method</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($orders as $order)
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-4 py-3 text-gray-500 whitespace-nowrap">{{ $order->ordered_at->format('M j, Y') }}</td>
                                        <td class="px-4 py-3 font-mono text-xs text-gray-700">{{ $order->order_number }}</td>
                                        <td class="px-4 py-3 text-gray-600 max-w-xs truncate" title="{{ $order->services_purchased }}">{{ $order->services_purchased }}</td>
                                        <td class="px-4 py-3 text-gray-500 font-mono text-xs">{{ $order->coupon_code }}</td>
                                        <td class="px-4 py-3 text-right text-gray-700">${{ number_format($order->order_total, 2) }}</td>
                                        <td class="px-4 py-3 text-right {{ $order->discount_amount > 0 ? 'text-amber-600' : 'text-gray-400' }}">
                                            @if($order->discount_amount > 0)−${{ number_format($order->discount_amount, 2) }}@else—@endif
                                        </td>
                                        <td class="px-4 py-3 text-right text-red-500">${{ number_format($order->cog_reader, 2) }}</td>
                                        <td class="px-4 py-3 text-right text-orange-500">${{ number_format($order->cog_processing, 2) }}</td>
                                        <td class="px-4 py-3 text-right text-yellow-600">${{ number_format($order->cog_commission, 2) }}</td>
                                        <td class="px-4 py-3 text-right text-rose-600">${{ number_format($order->cog_total, 2) }}</td>
                                        <td class="px-4 py-3 text-right font-semibold text-green-700">${{ number_format($order->net_revenue, 2) }}</td>
                                        <td class="px-4 py-3 text-gray-500 text-xs">{{ $order->payment_method }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-50 border-t-2 border-gray-300 text-sm font-semibold">
                                <tr>
                                    <td colspan="4" class="px-4 py-3 text-gray-600">Totals ({{ $totals['count'] }} orders)</td>
                                    <td class="px-4 py-3 text-right text-gray-700">${{ number_format($totals['gross'], 2) }}</td>
                                    <td class="px-4 py-3 text-right text-amber-600">@if($totals['discount'] > 0)−${{ number_format($totals['discount'], 2) }}@else—@endif</td>
                                    <td class="px-4 py-3 text-right text-red-500">${{ number_format($totals['cog_reader'], 2) }}</td>
                                    <td class="px-4 py-3 text-right text-orange-500">${{ number_format($totals['cog_proc'], 2) }}</td>
                                    <td class="px-4 py-3 text-right text-yellow-600">${{ number_format($totals['cog_comm'], 2) }}</td>
                                    <td class="px-4 py-3 text-right text-rose-600">${{ number_format($totals['cog_total'], 2) }}</td>
                                    <td class="px-4 py-3 text-right text-green-700">${{ number_format($totals['net'], 2) }}</td>
                                    <td></td>
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
        const gross  = @js($chartData['gross']);
        const net    = @js($chartData['net']);

        new Chart(document.getElementById('revenueChart'), {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Gross Revenue',
                        data: gross,
                        borderColor: 'rgb(99,102,241)',
                        backgroundColor: 'rgba(99,102,241,0.08)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: labels.length > 60 ? 0 : 3,
                    },
                    {
                        label: 'Net Revenue',
                        data: net,
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
                            label: ctx => ctx.dataset.label + ': $' + ctx.parsed.y.toLocaleString('en-US', {minimumFractionDigits:2}),
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
