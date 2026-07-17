{{-- Unified payment history — readers + editor, batched by paid date or flat/searchable --}}
<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3">
        <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wide">Payment History</h3>

        <form method="GET" action="{{ route('payroll.index') }}" class="flex flex-wrap items-center gap-2">
            <input type="hidden" name="period" value="{{ $period }}">
            <input type="text" name="q" value="{{ $search }}" placeholder="Search order #, title, writer, reader, amount&hellip;"
                class="text-sm border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 w-64">
            <select name="sort" class="text-sm border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                <option value="date" @selected($sort === 'date')>Sort: Date</option>
                <option value="reader" @selected($sort === 'reader')>Sort: Reader</option>
            </select>
            <button type="submit"
                class="px-3 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-md transition-colors">
                Search
            </button>
            @if($search !== '' || $sort !== 'date')
                <a href="{{ route('payroll.index', ['period' => $period]) }}" class="text-sm text-gray-500 hover:text-gray-700">Clear</a>
            @endif
            <a href="{{ route('payroll.export-1099', ['period' => $period]) }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-indigo-700 bg-indigo-50 border border-indigo-200 hover:bg-indigo-100 rounded-md transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Export 1099 CSV
            </a>
        </form>
    </div>

    @if($historyMode === 'flat')
        @if(empty($historyItems))
            <div class="px-6 py-8 text-center text-gray-400 text-sm">No matching payments.</div>
        @else
            <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50 text-xs font-medium text-gray-500 uppercase tracking-wide">
                    <tr>
                        <th class="px-4 py-2 text-left">Person</th>
                        <th class="px-4 py-2 text-left">Type</th>
                        <th class="px-4 py-2 text-left">Detail</th>
                        <th class="px-4 py-2 text-left">Order #</th>
                        <th class="px-4 py-2 text-left">Date</th>
                        <th class="px-4 py-2 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($historyItems as $item)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2">
                            <div class="flex items-center gap-2">
                                @if($item['photo_url'])
                                    <img src="{{ $item['photo_url'] }}" alt="{{ $item['person_initials'] }}"
                                         class="w-6 h-6 rounded-full object-cover ring-1 ring-gray-300">
                                @else
                                    <div class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-[9px] font-semibold ring-1 ring-gray-300">{{ $item['person_initials'] }}</div>
                                @endif
                                <span class="text-gray-800">{{ $item['person_name'] }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-2 text-gray-500 text-xs uppercase">{{ $item['type_label'] }}</td>
                        <td class="px-4 py-2 text-gray-700">{{ $item['detail'] }}</td>
                        <td class="px-4 py-2 text-gray-400 text-xs font-mono">{{ $item['order_number'] }}</td>
                        <td class="px-4 py-2 text-gray-500 text-xs">{{ $item['paid_at']->format('M j, Y') }}</td>
                        <td class="px-4 py-2 text-right font-medium {{ $item['amount'] >= 0 ? 'text-gray-700' : 'text-red-600' }}">
                            {{ $item['amount'] < 0 ? '-' : '' }}${{ number_format(abs($item['amount']), 2) }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            </div>

            @if($historyTotalPages > 1)
            <div class="flex items-center justify-between px-5 py-3 border-t border-gray-100 text-sm">
                <span class="text-xs text-gray-400">Page {{ $historyPage }} of {{ $historyTotalPages }}</span>
                <div class="flex items-center gap-3">
                    @if($historyPage > 1)
                        <a href="{{ request()->fullUrlWithQuery(['history_page' => $historyPage - 1]) }}" class="text-indigo-600 hover:text-indigo-800">&larr; Newer</a>
                    @endif
                    @if($historyPage < $historyTotalPages)
                        <a href="{{ request()->fullUrlWithQuery(['history_page' => $historyPage + 1]) }}" class="text-indigo-600 hover:text-indigo-800">Older &rarr;</a>
                    @endif
                </div>
            </div>
            @endif
        @endif
    @else
        @if(empty($historyBatches))
            <div class="px-6 py-8 text-center text-gray-400 text-sm">No payments recorded yet.</div>
        @else
            <div class="divide-y divide-gray-100">
                @foreach($historyBatches as $batch)
                @php
                    $batchDate = $batch['paid_at']->toDateString();
                    $personGroups = collect($batch['items'])->groupBy('person_id');
                @endphp
                <details class="group">
                    <summary class="flex items-center justify-between px-5 py-3 cursor-pointer hover:bg-gray-50 list-none">
                        <div class="flex items-center gap-4">
                            <span class="text-sm font-medium text-gray-700">Paid {{ $batch['paid_at']->format('M j, Y') }}</span>
                            <span class="text-xs text-gray-400">{{ count($batch['items']) }} item(s)</span>
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
                    <div class="border-t border-gray-100 bg-gray-50 divide-y divide-gray-100">
                        @foreach($personGroups as $personId => $personItems)
                        @php
                            $first = $personItems->first();
                            $subtotal = $personItems->sum('amount');
                        @endphp
                        <div class="px-5 py-3">
                            <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                                <div class="flex items-center gap-2">
                                    @if($first['photo_url'])
                                        <img src="{{ $first['photo_url'] }}" alt="{{ $first['person_initials'] }}"
                                             class="w-6 h-6 rounded-full object-cover ring-1 ring-gray-300">
                                    @else
                                        <div class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-[9px] font-semibold ring-1 ring-gray-300">{{ $first['person_initials'] }}</div>
                                    @endif
                                    <span class="font-medium text-gray-800 text-sm">{{ $first['person_name'] }}</span>
                                    <span class="font-semibold text-sm {{ $subtotal >= 0 ? 'text-green-700' : 'text-red-600' }}">
                                        ${{ number_format($subtotal, 2) }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-2">
                                    @if($first['person_type'] === 'reader')
                                        <form method="POST"
                                              action="{{ route('reader-pay.mark-unpaid', $personId) }}"
                                              onsubmit="return confirm('Revert this payment to unpaid?')">
                                            @csrf
                                            <input type="hidden" name="paid_at" value="{{ $batchDate }}">
                                            <button type="submit"
                                                    class="inline-flex items-center px-2 py-1 text-xs font-medium text-gray-500 bg-white border border-gray-300 hover:bg-red-50 hover:text-red-600 hover:border-red-300 rounded transition-colors">
                                                Mark Unpaid
                                            </button>
                                        </form>
                                        @if(auth()->user()->isAdmin())
                                        <form method="POST"
                                              action="{{ route('reader-pay.remove-batch', $personId) }}"
                                              onsubmit="return confirm('Permanently remove this batch? Test assignments will be deleted; real assignments will be reverted to unpaid.')">
                                            @csrf
                                            <input type="hidden" name="paid_at" value="{{ $batchDate }}">
                                            <button type="submit"
                                                    class="inline-flex items-center px-2 py-1 text-xs font-medium text-white bg-red-600 hover:bg-red-700 rounded transition-colors">
                                                Remove
                                            </button>
                                        </form>
                                        @endif
                                    @elseif($personId)
                                        <form method="POST"
                                              action="{{ route('editor-pay.mark-unpaid', $personId) }}"
                                              onsubmit="return confirm('Revert this payment to unpaid?')">
                                            @csrf
                                            <input type="hidden" name="paid_at" value="{{ $batchDate }}">
                                            <button type="submit"
                                                    class="inline-flex items-center px-2 py-1 text-xs font-medium text-gray-500 bg-white border border-gray-300 hover:bg-red-50 hover:text-red-600 hover:border-red-300 rounded transition-colors">
                                                Mark Unpaid
                                            </button>
                                        </form>
                                        @if(auth()->user()->isAdmin())
                                        <form method="POST"
                                              action="{{ route('editor-pay.delete-history-batch', [$personId, $batchDate]) }}"
                                              onsubmit="return confirm('Permanently delete this payment batch ({{ $batch['paid_at']->format('M j, Y') }})? This cannot be undone.')">
                                            @csrf @method('DELETE')
                                            <button type="submit"
                                                    class="inline-flex items-center px-2 py-1 text-xs font-medium text-white bg-red-600 hover:bg-red-700 rounded transition-colors">
                                                Delete
                                            </button>
                                        </form>
                                        @endif
                                    @else
                                        <span class="text-xs text-gray-400 italic">Editor deleted — no actions available</span>
                                    @endif
                                </div>
                            </div>
                            <table class="min-w-full text-sm">
                                <tbody class="divide-y divide-gray-100">
                                    @foreach($personItems as $item)
                                    <tr>
                                        <td class="px-2 py-1.5 text-gray-500 text-xs w-24">{{ $item['type_label'] }}</td>
                                        <td class="px-2 py-1.5 text-gray-700">{{ $item['detail'] }}</td>
                                        <td class="px-2 py-1.5 text-gray-400 text-xs font-mono">{{ $item['order_number'] }}</td>
                                        <td class="px-2 py-1.5 text-right {{ $item['amount'] >= 0 ? 'text-gray-700' : 'text-red-600' }}">
                                            {{ $item['amount'] < 0 ? '-' : '' }}${{ number_format(abs($item['amount']), 2) }}
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @endforeach
                    </div>
                </details>
                @endforeach
            </div>

            @if($historyTotalPages > 1)
            <div class="flex items-center justify-between px-5 py-3 border-t border-gray-100 text-sm">
                <span class="text-xs text-gray-400">Page {{ $historyPage }} of {{ $historyTotalPages }}</span>
                <div class="flex items-center gap-3">
                    @if($historyPage > 1)
                        <a href="{{ request()->fullUrlWithQuery(['history_page' => $historyPage - 1]) }}" class="text-indigo-600 hover:text-indigo-800">&larr; Newer</a>
                    @endif
                    @if($historyPage < $historyTotalPages)
                        <a href="{{ request()->fullUrlWithQuery(['history_page' => $historyPage + 1]) }}" class="text-indigo-600 hover:text-indigo-800">Older &rarr;</a>
                    @endif
                </div>
            </div>
            @endif
        @endif
    @endif
</div>
