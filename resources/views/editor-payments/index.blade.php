<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">My Payments</h2>
            <div class="text-sm text-gray-500">
                Current period: <span class="font-medium text-gray-700">{{ \App\Support\PayPeriod::label($currentStart) }}</span>
                &nbsp;· next payout Sat {{ $currentEnd->addHour()->format('M j') }}
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- ── CURRENT PERIOD ── --}}
            @if($currentPeriod)
                <div class="bg-white rounded-lg shadow-sm border border-green-200 overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-4 bg-green-50 border-b border-green-200">
                        <div>
                            <div class="font-semibold text-green-800">This Period — {{ \App\Support\PayPeriod::label($currentStart) }}</div>
                            <div class="text-xs text-green-600 mt-0.5">Payout Saturday {{ $currentEnd->addHour()->format('M j') }} ~8 AM PT</div>
                        </div>
                        <div class="text-2xl font-bold text-green-700">${{ number_format($currentPeriod['total'], 2) }}</div>
                    </div>
                    @include('editor-payments._period_table', ['period' => $currentPeriod])
                </div>
            @else
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 px-6 py-10 text-center text-gray-400 text-sm">
                    No pending pay for the current period
                    ({{ \App\Support\PayPeriod::label($currentStart) }}).
                    Payout Saturday {{ $currentEnd->addHour()->format('M j') }} ~8 AM PT.
                </div>
            @endif

            {{-- ── PRIOR UNPAID ── --}}
            @foreach($priorPeriods as $period)
                <div class="bg-white rounded-lg shadow-sm border border-amber-200 overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-3 bg-amber-50 border-b border-amber-200">
                        <div class="font-medium text-amber-800">
                            Pending (Prior Period) — {{ \App\Support\PayPeriod::label($period['period_start']) }}
                        </div>
                        <div class="font-bold text-amber-700">${{ number_format($period['total'], 2) }}</div>
                    </div>
                    @include('editor-payments._period_table', ['period' => $period])
                </div>
            @endforeach

            {{-- ── HISTORY ── --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wide">Payment History</h3>
                    @if($totalPages > 1)
                        <span class="text-xs text-gray-400">Page {{ $page }} of {{ $totalPages }}</span>
                    @endif
                </div>

                @if(empty($history))
                    <div class="px-6 py-8 text-center text-gray-400 text-sm">No payments received yet.</div>
                @else
                    <div class="divide-y divide-gray-100">
                        @foreach($history as $batch)
                        <details class="group">
                            <summary class="flex items-center justify-between px-5 py-3 cursor-pointer hover:bg-gray-50 list-none">
                                <div class="flex items-center gap-4">
                                    <span class="text-sm font-medium text-gray-700">Paid {{ $batch['paid_at']->format('M j, Y') }}</span>
                                    <span class="text-xs text-gray-400">
                                        {{ count($batch['orders']) }} commission(s)
                                        @if(count($batch['adjustments']) > 0)+ {{ count($batch['adjustments']) }} adj.@endif
                                    </span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="font-semibold {{ $batch['total'] >= 0 ? 'text-green-700' : 'text-red-600' }}">
                                        ${{ number_format($batch['total'], 2) }}
                                    </span>
                                    <svg class="w-4 h-4 text-gray-400 group-open:rotate-180 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </div>
                            </summary>
                            <div class="border-t border-gray-100 bg-gray-50">
                                <table class="min-w-full text-sm">
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach($batch['orders'] as $o)
                                        <tr>
                                            <td class="px-6 py-2 text-gray-500 text-xs w-24">Commission</td>
                                            <td class="px-4 py-2 font-mono text-xs text-gray-600">{{ $o->order_number }}</td>
                                            <td class="px-4 py-2 text-gray-500 text-xs">{{ $o->ordered_at->format('M j') }}</td>
                                            <td class="px-4 py-2 text-right text-gray-700">${{ number_format($o->cog_commission, 2) }}</td>
                                        </tr>
                                        @endforeach
                                        @foreach($batch['adjustments'] as $adj)
                                        <tr>
                                            <td class="px-6 py-2 text-indigo-500 text-xs w-24">Adjustment</td>
                                            <td class="px-4 py-2 text-gray-700" colspan="2">{{ $adj->description }}</td>
                                            <td class="px-4 py-2 text-right {{ (float)$adj->amount >= 0 ? 'text-green-700' : 'text-red-600' }}">
                                                {{ (float)$adj->amount >= 0 ? '+' : '' }}${{ number_format($adj->amount, 2) }}
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </details>
                        @endforeach
                    </div>
                    @if($totalPages > 1)
                    <div class="flex items-center justify-between px-5 py-3 border-t border-gray-100 text-sm">
                        @if($page > 1)
                            <a href="{{ request()->fullUrlWithQuery(['page' => $page - 1]) }}" class="text-indigo-600 hover:text-indigo-800">&larr; Newer</a>
                        @else <span></span>
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
</x-app-layout>
