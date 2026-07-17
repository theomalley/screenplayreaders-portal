{{-- Editor Pay card — unpaid commissions/adjustments for one editor --}}
@php $editorId = $ed['editor_id']; $editor = $ed['editor']; @endphp
<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden" x-data="{ adjOpen: false }">

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-b border-blue-200 bg-blue-50">
        <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-3">
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.editors.edit', $editor) }}" title="Edit {{ $ed['editor_name'] }}">
                    @if($ed['photo_url'])
                        <img src="{{ $ed['photo_url'] }}" alt="{{ $ed['initials'] }}"
                             class="w-7 h-7 rounded-full object-cover ring-1 ring-gray-300 hover:ring-indigo-400 transition-shadow">
                    @else
                        <div class="w-7 h-7 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-[10px] font-semibold ring-1 ring-gray-300 hover:ring-indigo-400 transition-shadow">{{ $ed['initials'] }}</div>
                    @endif
                </a>
                <span class="font-semibold text-gray-800">{{ $ed['editor_name'] }}</span>
                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold {{ $ed['is_1099'] ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-500' }}">
                    {{ $ed['is_1099'] ? '1099' : 'Non-1099' }}
                </span>
                @php $paymentId = strtoupper($ed['initials']) . $periodEnd->format('Ymd'); @endphp
                <span x-data="{ copied: false }"
                      class="inline-flex items-center gap-1 cursor-pointer select-all"
                      @click="navigator.clipboard.writeText('{{ $paymentId }}'); copied = true; setTimeout(() => copied = false, 1500)"
                      title="Copy payment ID for PayPal note">
                    <span class="font-mono text-xs px-1.5 py-0.5 rounded bg-gray-100 border border-gray-300 text-gray-600 hover:border-indigo-400 hover:text-indigo-700 transition-colors">{{ $paymentId }}</span>
                    <span x-show="!copied" class="text-[10px] text-gray-400">copy</span>
                    <span x-show="copied" x-cloak class="text-[10px] text-green-600 font-medium">✓ copied</span>
                </span>
            </div>
            @if($ed['paypal_email'])
                <span class="text-sm text-gray-500">· PayPal: <span class="font-mono text-xs">{{ $ed['paypal_email'] }}</span></span>
            @endif
            <span class="text-sm font-semibold {{ $ed['total_owed'] >= 0 ? 'text-blue-700' : 'text-red-600' }}">
                · {{ $ed['unpaid_orders']->count() }} commission(s) + {{ $ed['unpaid_adjustments']->count() }} adjustment(s)@if($ed['period_flat_rate'] > 0) + flat rate @endif
                &nbsp;·&nbsp; ${{ number_format($ed['total_owed'], 2) }} owed
            </span>
        </div>
        @if(auth()->user()->isAdmin())
        <div class="flex items-center gap-2">
            <form method="POST" action="{{ route('editor-pay.clear-unpaid', $editorId) }}"
                onsubmit="return confirm('Clear all pending commissions and adjustments for {{ $ed['editor_name'] }}? This sets commissions to $0 and removes pending adjustments.')">
                @csrf
                <button type="submit"
                    class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-red-600 bg-white border border-red-300 hover:bg-red-50 rounded-md transition-colors">
                    Remove
                </button>
            </form>
            <button type="button" @click="adjOpen = !adjOpen"
                class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-indigo-700 bg-white border border-indigo-300 hover:bg-indigo-50 rounded-md transition-colors">
                + Adjustment
            </button>
            <form method="POST" action="{{ route('editor-pay.mark-paid', $editorId) }}"
                onsubmit="return confirm('Mark all pending pay for {{ $ed['editor_name'] }} as paid (${{ number_format($ed['total_owed'], 2) }})?')">
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
        <form method="POST" action="{{ route('editor-pay.add-adjustment', $editorId) }}" class="flex flex-wrap items-end gap-3">
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
                onclick="this.closest('form').querySelector('[name=amount]').value = '{{ number_format($ed['weekly_flat'], 2) }}'; this.closest('form').querySelector('[name=description]').value = 'Weekly flat rate';"
                class="px-3 py-2 text-xs font-medium text-indigo-600 bg-white border border-indigo-200 hover:bg-indigo-50 rounded-md transition-colors">
                Fill Weekly Flat (${{ number_format($ed['weekly_flat'], 2) }})
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
    @if($ed['unpaid_orders']->isEmpty() && $ed['unpaid_adjustments']->isEmpty() && $ed['period_flat_rate'] <= 0)
        <div class="px-6 py-10 text-center text-gray-400 text-sm">No pending pay.</div>
    @else
        <table class="min-w-full table-fixed divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 text-xs font-medium text-gray-500 uppercase tracking-wide">
                <tr>
                    <th class="px-4 py-2 text-left w-28">Type</th>
                    <th class="px-4 py-2 text-left">Detail</th>
                    <th class="px-4 py-2 text-left w-24">Date</th>
                    <th class="px-4 py-2 text-right w-24">Gross</th>
                    <th class="px-4 py-2 text-right w-24">Commission</th>
                    <th class="px-4 py-2 w-16"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($ed['unpaid_orders'] as $order)
                @php
                    $commissionRate = $order->cog_precommission != 0
                        ? ($order->cog_commission / $order->cog_precommission) * 100
                        : 0;
                @endphp
                <tr class="hover:bg-gray-50" x-data="{ editingCommission: false, grossHover: false, tipX: 0, tipY: 0 }">
                    <td class="px-4 py-2 text-gray-500 text-xs uppercase">Commission</td>
                    <td class="px-4 py-2">
                        <div class="text-gray-800">{{ $order->script_title }}</div>
                        <div class="text-xs text-gray-400 font-mono">{{ $order->order_number }}</div>
                        <div class="text-xs text-gray-400 truncate max-w-xs">{{ $order->services_purchased }}</div>
                    </td>
                    <td class="px-4 py-2 text-gray-500 text-xs">{{ $order->ordered_at->format('M j, Y') }}</td>
                    <td class="px-4 py-2 text-right text-gray-500 text-xs">
                        <span class="cursor-help border-b border-dotted border-gray-300"
                              @mouseenter="grossHover = true; const r = $el.getBoundingClientRect(); tipX = r.left + r.width / 2; tipY = r.top"
                              @mouseleave="grossHover = false">${{ number_format($order->order_total, 2) }}</span>
                        <div x-show="grossHover" x-cloak
                             :style="`position:fixed;left:${tipX}px;top:${tipY}px;transform:translate(-50%,calc(-100% - 8px))`"
                             class="z-50 w-60 bg-gray-800 text-white text-xs rounded-md px-2.5 py-2 shadow-lg text-left font-mono whitespace-pre pointer-events-none">
                            <div class="flex justify-between"><span>Gross</span><span>${{ number_format($order->order_total, 2) }}</span></div>
                            <div class="flex justify-between"><span>− Reader COGS</span><span>${{ number_format($order->cog_reader, 2) }}</span></div>
                            <div class="flex justify-between"><span>− Processing</span><span>${{ number_format($order->cog_processing, 2) }}</span></div>
                            <div class="flex justify-between border-t border-gray-600 mt-1 pt-1"><span>= Commission base</span><span>${{ number_format($order->cog_precommission, 2) }}</span></div>
                            <div class="flex justify-between"><span>&times; {{ number_format($commissionRate, 1) }}%</span><span>${{ number_format($order->cog_commission, 2) }}</span></div>
                            <div class="absolute top-full left-1/2 -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-l-transparent border-r-transparent border-t-4 border-t-gray-800"></div>
                        </div>
                    </td>
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
                @foreach($ed['unpaid_adjustments'] as $adj)
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
                @if($ed['period_flat_rate'] > 0)
                <tr class="bg-blue-50/50 hover:bg-blue-50 border-t border-blue-100" x-data="{ editingFlat: false }">
                    <td class="px-4 py-2 text-blue-600 text-xs uppercase font-medium">Flat Rate</td>
                    <td class="px-4 py-2">
                        <div class="text-gray-700">Weekly flat rate{{ $ed['period_weeks'] > 1 ? " &times; {$ed['period_weeks']} weeks" : '' }}</div>
                        <div class="text-xs text-gray-400">Auto-included at end of pay period</div>
                    </td>
                    <td class="px-4 py-2 text-gray-500 text-xs">{{ $periodEnd->format('M j, Y') }}</td>
                    <td class="px-4 py-2"></td>
                    <td class="px-4 py-2 text-right font-medium text-green-700">
                        @if(auth()->user()->isAdmin())
                            <span x-show="!editingFlat" @click="editingFlat = true"
                                  class="cursor-pointer hover:underline" title="Click to edit">
                                +${{ number_format($ed['period_flat_rate'], 2) }}
                            </span>
                            <form x-show="editingFlat" x-cloak method="POST"
                                  action="{{ route('editor-pay.update-flat-rate', $editorId) }}"
                                  class="flex items-center justify-end gap-1">
                                @csrf @method('PATCH')
                                <span class="text-gray-400">$</span>
                                <input type="number" name="period_flat_rate" step="0.01" min="0"
                                       value="{{ number_format($ed['period_flat_rate'], 2, '.', '') }}"
                                       class="w-20 text-right text-xs border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" />
                                <button type="submit" class="text-xs text-indigo-600 hover:text-indigo-800">Save</button>
                                <button type="button" @click="editingFlat = false" class="text-xs text-gray-400 hover:text-gray-600">Cancel</button>
                            </form>
                        @else
                            +${{ number_format($ed['period_flat_rate'], 2) }}
                        @endif
                    </td>
                    <td class="px-4 py-2 text-right">
                        @if(auth()->user()->isAdmin())
                        <form method="POST" action="{{ route('editor-pay.delete-flat-rate', $editorId) }}"
                            onsubmit="return confirm('Remove the flat rate? This sets {{ $ed['editor_name'] }}\'s weekly flat to $0.')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-xs text-red-400 hover:text-red-600">Remove</button>
                        </form>
                        @endif
                    </td>
                </tr>
                @endif
            </tbody>
            <tfoot class="bg-blue-50 border-t-2 border-blue-200 text-sm font-semibold">
                <tr>
                    <td colspan="4" class="px-4 py-3 text-blue-700">Total owed</td>
                    <td class="px-4 py-3 text-right {{ $ed['total_owed'] >= 0 ? 'text-blue-700' : 'text-red-600' }}">${{ number_format($ed['total_owed'], 2) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    @endif
</div>
