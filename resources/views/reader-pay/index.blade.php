<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Reader Pay</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            {{-- ── UNPAID ── --}}
            @if($byReader->isEmpty())
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 px-6 py-12 text-center text-gray-400 text-sm">
                    No unpaid coverages. All readers are paid up.
                </div>
            @else
                @foreach($byReader as $rd)
                @php $readerId = $rd['reader_id']; @endphp
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden" x-data="{ adjOpen: false }">

                    {{-- Reader header --}}
                    <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-b border-amber-200 bg-amber-50">
                        <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-3">
                            <span class="font-semibold text-gray-800">{{ $rd['reader_name'] }}</span>
                            @if($rd['paypal_email'])
                                <span class="text-sm text-gray-500">· PayPal: <span class="font-mono text-xs">{{ $rd['paypal_email'] }}</span></span>
                            @endif
                            <span class="text-sm font-semibold {{ $rd['total_owed'] >= 0 ? 'text-amber-700' : 'text-red-600' }}">
                                · {{ $rd['assignments']->count() + $rd['adjustments']->count() }} item(s)
                                &nbsp;·&nbsp; ${{ number_format($rd['total_owed'], 2) }} owed
                            </span>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="adjOpen = !adjOpen"
                                class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-indigo-700 bg-white border border-indigo-300 hover:bg-indigo-50 rounded-md transition-colors">
                                + Adjustment
                            </button>
                            <form method="POST" action="{{ route('reader-pay.mark-paid', $readerId) }}"
                                onsubmit="return confirm('Mark all items for {{ $rd['reader_name'] }} as paid (${{ number_format($rd['total_owed'], 2) }})?')">
                                @csrf
                                <button type="submit"
                                    class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-md transition-colors">
                                    Mark All Paid
                                </button>
                            </form>
                        </div>
                    </div>

                    {{-- Adjustment form (collapsed by default) --}}
                    <div x-show="adjOpen" x-cloak class="px-5 py-4 bg-indigo-50 border-b border-indigo-100">
                        <form method="POST" action="{{ route('reader-pay.add-adjustment', $readerId) }}" class="flex flex-wrap items-end gap-3">
                            @csrf
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Amount (negative to deduct)</label>
                                <input type="number" name="amount" step="0.01" placeholder="e.g. -15.00"
                                    class="w-32 text-sm border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                    required>
                            </div>
                            <div class="flex-1 min-w-48">
                                <label class="block text-xs font-medium text-gray-600 mb-1">Description</label>
                                <input type="text" name="description" placeholder="e.g. Overpay correction"
                                    class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                    required maxlength="255">
                            </div>
                            <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-md transition-colors">
                                Add
                            </button>
                            <button type="button" @click="adjOpen = false"
                                class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 rounded-md transition-colors">
                                Cancel
                            </button>
                        </form>
                    </div>

                    {{-- Line items table --}}
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50 text-xs font-medium text-gray-500 uppercase tracking-wide">
                            <tr>
                                <th class="px-4 py-2 text-left">Type</th>
                                <th class="px-4 py-2 text-left">Detail</th>
                                <th class="px-4 py-2 text-left">Date</th>
                                <th class="px-4 py-2 text-right">Amount</th>
                                <th class="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach($rd['assignments'] as $a)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 text-gray-500 text-xs uppercase">Coverage</td>
                                <td class="px-4 py-2">
                                    <div class="text-gray-800">{{ $a->script_title }}</div>
                                    <div class="text-xs text-gray-400 font-mono">{{ $a->order_number }}</div>
                                </td>
                                <td class="px-4 py-2 text-gray-500 text-xs">{{ $a->completed_at?->format('M j, Y') }}</td>
                                <td class="px-4 py-2 text-right font-medium text-gray-700">${{ number_format($a->pay_rate, 2) }}</td>
                                <td class="px-4 py-2"></td>
                            </tr>
                            @endforeach
                            @foreach($rd['adjustments'] as $adj)
                            <tr class="hover:bg-indigo-50">
                                <td class="px-4 py-2 text-indigo-600 text-xs uppercase font-medium">Adjustment</td>
                                <td class="px-4 py-2">
                                    <div class="text-gray-700">{{ $adj->description }}</div>
                                    <div class="text-xs text-gray-400">by {{ $adj->addedBy?->name }}</div>
                                </td>
                                <td class="px-4 py-2 text-gray-500 text-xs">{{ $adj->created_at->format('M j, Y') }}</td>
                                <td class="px-4 py-2 text-right font-medium {{ (float)$adj->amount >= 0 ? 'text-green-700' : 'text-red-600' }}">
                                    {{ (float)$adj->amount >= 0 ? '+' : '' }}${{ number_format($adj->amount, 2) }}
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <form method="POST" action="{{ route('reader-pay.delete-adjustment', $adj->id) }}"
                                        onsubmit="return confirm('Remove this adjustment?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-xs text-red-400 hover:text-red-600">Remove</button>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endforeach
            @endif

            {{-- ── HISTORY ── --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wide">Payment History</h3>
                    @if($totalPages > 1)
                        <span class="text-xs text-gray-400">Page {{ $page }} of {{ $totalPages }}</span>
                    @endif
                </div>

                @if(empty($history))
                    <div class="px-6 py-8 text-center text-gray-400 text-sm">No payments recorded yet.</div>
                @else
                    <div class="divide-y divide-gray-100">
                        @foreach($history as $batch)
                        <details class="group">
                            <summary class="flex items-center justify-between px-5 py-3 cursor-pointer hover:bg-gray-50 list-none">
                                <div class="flex items-center gap-4">
                                    <span class="font-medium text-gray-800">{{ $batch['reader_name'] }}</span>
                                    <span class="text-xs text-gray-400">Paid {{ $batch['paid_at']->format('M j, Y') }}</span>
                                    <span class="text-xs text-gray-500">
                                        {{ count($batch['assignments']) }} coverage(s)
                                        @if(count($batch['adjustments']) > 0)
                                            + {{ count($batch['adjustments']) }} adj.
                                        @endif
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
                                        @foreach($batch['assignments'] as $a)
                                        <tr>
                                            <td class="px-6 py-2 text-gray-500 text-xs w-20">Coverage</td>
                                            <td class="px-4 py-2 text-gray-700">{{ $a->script_title }}</td>
                                            <td class="px-4 py-2 text-gray-400 text-xs font-mono">{{ $a->order_number }}</td>
                                            <td class="px-4 py-2 text-gray-400 text-xs">{{ $a->completed_at?->format('M j') }}</td>
                                            <td class="px-4 py-2 text-right text-gray-700">${{ number_format($a->pay_rate, 2) }}</td>
                                        </tr>
                                        @endforeach
                                        @foreach($batch['adjustments'] as $adj)
                                        <tr>
                                            <td class="px-6 py-2 text-indigo-500 text-xs w-20">Adjustment</td>
                                            <td class="px-4 py-2 text-gray-700" colspan="3">{{ $adj->description }}</td>
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

                    {{-- Pagination --}}
                    @if($totalPages > 1)
                    <div class="flex items-center justify-between px-5 py-3 border-t border-gray-100 text-sm">
                        @if($page > 1)
                            <a href="{{ request()->fullUrlWithQuery(['page' => $page - 1]) }}"
                                class="text-indigo-600 hover:text-indigo-800">&larr; Newer</a>
                        @else
                            <span></span>
                        @endif
                        @if($page < $totalPages)
                            <a href="{{ request()->fullUrlWithQuery(['page' => $page + 1]) }}"
                                class="text-indigo-600 hover:text-indigo-800">Older &rarr;</a>
                        @endif
                    </div>
                    @endif
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
