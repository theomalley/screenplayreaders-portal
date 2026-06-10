<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Editor Pay</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            {{-- ── UNPAID ── --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden" x-data="{ adjOpen: false }">

                {{-- Header --}}
                <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-b border-amber-200 bg-amber-50">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-3">
                        <span class="font-semibold text-gray-800">
                            {{ $editor?->editorProfile?->displayName() ?? $editor?->name ?? 'Editor' }}
                        </span>
                        @if($editor?->editorProfile?->paypal_email)
                            <span class="text-sm text-gray-500">· PayPal: <span class="font-mono text-xs">{{ $editor->editorProfile->paypal_email }}</span></span>
                        @endif
                        <span class="text-sm font-semibold {{ $totalOwed >= 0 ? 'text-amber-700' : 'text-red-600' }}">
                            · {{ $unpaidOrders->count() }} commission(s) + {{ $unpaidAdjustments->count() }} adjustment(s)
                            &nbsp;·&nbsp; ${{ number_format($totalOwed, 2) }} owed
                        </span>
                    </div>
                    @if(auth()->user()->isAdmin())
                    <div class="flex items-center gap-2">
                        <button type="button" @click="adjOpen = !adjOpen"
                            class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-indigo-700 bg-white border border-indigo-300 hover:bg-indigo-50 rounded-md transition-colors">
                            + Adjustment
                        </button>
                        <form method="POST" action="{{ route('editor-pay.mark-paid') }}"
                            onsubmit="return confirm('Mark all pending editor pay as paid (${{ number_format($totalOwed, 2) }})?')">
                            @csrf
                            <button type="submit"
                                class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-md transition-colors">
                                Mark All Paid
                            </button>
                        </form>
                    </div>
                    @endif
                </div>

                {{-- Adjustment form --}}
                @if(auth()->user()->isAdmin())
                <div x-show="adjOpen" x-cloak class="px-5 py-4 bg-indigo-50 border-b border-indigo-100">
                    <form method="POST" action="{{ route('editor-pay.add-adjustment') }}" class="flex flex-wrap items-end gap-3">
                        @csrf
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Amount (negative to deduct)</label>
                            <input type="number" name="amount" step="0.01" placeholder="e.g. 500.00"
                                class="w-32 text-sm border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                required>
                        </div>
                        <div class="flex-1 min-w-48">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Description</label>
                            <input type="text" name="description" placeholder="e.g. Weekly flat rate"
                                class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                required maxlength="255">
                        </div>
                        <button type="button"
                            onclick="this.closest('form').querySelector('[name=amount]').value = '{{ number_format($weeklyFlat, 2) }}'; this.closest('form').querySelector('[name=description]').value = 'Weekly flat rate';"
                            class="px-3 py-2 text-xs font-medium text-indigo-600 bg-white border border-indigo-200 hover:bg-indigo-50 rounded-md transition-colors">
                            Fill Weekly Flat (${{ number_format($weeklyFlat, 2) }})
                        </button>
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
                @endif

                {{-- Commissions table --}}
                @if($unpaidOrders->isEmpty() && $unpaidAdjustments->isEmpty())
                    <div class="px-6 py-10 text-center text-gray-400 text-sm">No pending editor pay.</div>
                @else
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50 text-xs font-medium text-gray-500 uppercase tracking-wide">
                            <tr>
                                <th class="px-4 py-2 text-left">Type</th>
                                <th class="px-4 py-2 text-left">Detail</th>
                                <th class="px-4 py-2 text-left">Date</th>
                                <th class="px-4 py-2 text-right">Gross</th>
                                <th class="px-4 py-2 text-right">Commission</th>
                                <th class="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach($unpaidOrders as $order)
                            <tr class="hover:bg-gray-50" x-data="{ editingCommission: false }">
                                <td class="px-4 py-2 text-gray-500 text-xs uppercase">Commission</td>
                                <td class="px-4 py-2">
                                    <div class="text-gray-800 font-mono text-xs">{{ $order->order_number }}</div>
                                    <div class="text-xs text-gray-400 truncate max-w-xs">{{ $order->services_purchased }}</div>
                                </td>
                                <td class="px-4 py-2 text-gray-500 text-xs">{{ $order->ordered_at->format('M j, Y') }}</td>
                                <td class="px-4 py-2 text-right text-gray-500 text-xs">${{ number_format($order->order_total, 2) }}</td>
                                <td class="px-4 py-2 text-right font-medium text-gray-700">
                                    @if(auth()->user()->isAdmin())
                                        <span x-show="!editingCommission" @click="editingCommission = true"
                                              class="cursor-pointer hover:underline" title="Click to edit">
                                            ${{ number_format($order->cog_commission, 2) }}
                                        </span>
                                        <form x-show="editingCommission" x-cloak method="POST"
                                              action="{{ route('editor-pay.update-commission', $order->id) }}"
                                              class="flex items-center justify-end gap-1">
                                            @csrf @method('PATCH')
                                            <span class="text-gray-400">$</span>
                                            <input type="number" name="cog_commission" step="0.01" min="0"
                                                   value="{{ number_format((float) $order->cog_commission, 2, '.', '') }}"
                                                   class="w-20 text-right text-xs border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" />
                                            <button type="submit" class="text-xs text-indigo-600 hover:text-indigo-800">Save</button>
                                            <button type="button" @click="editingCommission = false" class="text-xs text-gray-400 hover:text-gray-600">Cancel</button>
                                        </form>
                                    @else
                                        ${{ number_format($order->cog_commission, 2) }}
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right">
                                    @if(auth()->user()->isAdmin())
                                    <form method="POST" action="{{ route('editor-pay.delete-commission', $order->id) }}"
                                        onsubmit="return confirm('Remove the commission for order {{ $order->order_number }}? This sets it to $0 and removes it from this list.')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-xs text-red-400 hover:text-red-600">Remove</button>
                                    </form>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                            @foreach($unpaidAdjustments as $adj)
                            <tr class="hover:bg-indigo-50">
                                <td class="px-4 py-2 text-indigo-600 text-xs uppercase font-medium">Adjustment</td>
                                <td class="px-4 py-2">
                                    <div class="text-gray-700">{{ $adj->description }}</div>
                                    <div class="text-xs text-gray-400">by {{ $adj->addedBy?->name }}</div>
                                </td>
                                <td class="px-4 py-2 text-gray-500 text-xs">{{ $adj->created_at->format('M j, Y') }}</td>
                                <td class="px-4 py-2"></td>
                                <td class="px-4 py-2 text-right font-medium {{ (float)$adj->amount >= 0 ? 'text-green-700' : 'text-red-600' }}">
                                    {{ (float)$adj->amount >= 0 ? '+' : '' }}${{ number_format($adj->amount, 2) }}
                                </td>
                                @if(auth()->user()->isAdmin())
                                <td class="px-4 py-2 text-right">
                                    <form method="POST" action="{{ route('editor-pay.delete-adjustment', $adj->id) }}"
                                        onsubmit="return confirm('Remove this adjustment?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-xs text-red-400 hover:text-red-600">Remove</button>
                                    </form>
                                </td>
                                @else
                                <td></td>
                                @endif
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-amber-50 border-t-2 border-amber-200 text-sm font-semibold">
                            <tr>
                                <td colspan="4" class="px-4 py-3 text-amber-700">Total owed</td>
                                <td class="px-4 py-3 text-right {{ $totalOwed >= 0 ? 'text-amber-700' : 'text-red-600' }}">${{ number_format($totalOwed, 2) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                @endif
            </div>

            {{-- ── HISTORY ── --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wide">Payment History</h3>
                    <div class="flex items-center gap-3">
                        @if($totalPages > 1)
                            <span class="text-xs text-gray-400">Page {{ $page }} of {{ $totalPages }}</span>
                        @endif
                        @if(auth()->user()->isAdmin() && !empty($history))
                        <form method="POST" action="{{ route('editor-pay.delete-all-history') }}"
                            onsubmit="return confirm('Permanently delete ALL editor payment history? This cannot be undone.')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-xs text-red-400 hover:text-red-600">Delete All History</button>
                        </form>
                        @endif
                    </div>
                </div>

                @if(empty($history))
                    <div class="px-6 py-8 text-center text-gray-400 text-sm">No payments recorded yet.</div>
                @else
                    <div class="divide-y divide-gray-100">
                        @foreach($history as $batch)
                        @php
                            $batchDate = $batch['paid_at']->toDateString();
                            $batchDateLabel = $batch['paid_at']->format('M j, Y');
                        @endphp
                        <details class="group">
                            <summary class="flex items-center justify-between px-5 py-3 cursor-pointer hover:bg-gray-50 list-none">
                                <div class="flex items-center gap-4">
                                    <span class="text-sm font-medium text-gray-700">Paid {{ $batch['paid_at']->format('M j, Y') }}</span>
                                    <span class="text-xs text-gray-400">
                                        {{ count($batch['orders']) }} commission(s)
                                        @if(count($batch['adjustments']) > 0)
                                            + {{ count($batch['adjustments']) }} adj.
                                        @endif
                                    </span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="font-semibold {{ $batch['total'] >= 0 ? 'text-green-700' : 'text-red-600' }}">
                                        ${{ number_format($batch['total'], 2) }}
                                    </span>
                                    @if(auth()->user()->isAdmin())
                                    <form method="POST" action="{{ route('editor-pay.delete-history-batch', $batchDate) }}"
                                        onsubmit="event.stopPropagation(); return confirm('Permanently delete this payment batch ({{ $batchDateLabel }})? This cannot be undone.')"
                                        onclick="event.stopPropagation()">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-xs text-red-400 hover:text-red-600">Delete</button>
                                    </form>
                                    @endif
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
                                            <td class="px-4 py-2 text-gray-400 text-xs truncate max-w-xs">{{ $o->services_purchased }}</td>
                                            <td class="px-4 py-2 text-right text-gray-700">${{ number_format($o->cog_commission, 2) }}</td>
                                        </tr>
                                        @endforeach
                                        @foreach($batch['adjustments'] as $adj)
                                        <tr>
                                            <td class="px-6 py-2 text-indigo-500 text-xs w-24">Adjustment</td>
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
</x-app-layout>
