<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4 flex-wrap">
            <div class="flex items-center gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Order Log</h2>
                <a href="{{ route('order-log.create') }}"
                   class="inline-flex items-center px-3 py-1.5 bg-indigo-600 border border-transparent rounded text-xs font-medium text-white hover:bg-indigo-700 transition">
                    + Add Order
                </a>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                {{-- Search --}}
                <form method="GET" action="{{ route('order-log.index') }}" class="flex items-center gap-2">
                    <input type="hidden" name="period" value="{{ $period }}">
                    <input type="text" name="q" value="{{ $q }}"
                           placeholder="Order #, name, email…"
                           class="text-sm border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 w-52" />
                    <x-primary-button class="py-1.5 px-3 text-xs">Search</x-primary-button>
                    @if($q)
                        <a href="{{ route('order-log.index', ['period' => $period]) }}"
                           class="text-xs text-gray-500 hover:text-gray-700">Clear</a>
                    @endif
                </form>
                {{-- Period --}}
                <form method="GET" action="{{ route('order-log.index') }}" id="period-form">
                    <input type="hidden" name="q" value="{{ $q }}">
                    <select name="period"
                            onchange="document.getElementById('period-form').submit()"
                            class="text-sm border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        @foreach($periods as $key => $label)
                            <option value="{{ $key }}" {{ $period === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">

                <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                    <p class="text-xs text-gray-500">
                        {{ number_format($orders->total()) }} order{{ $orders->total() === 1 ? '' : 's' }}
                        @if($q) matching <span class="font-medium">"{{ $q }}"</span>@endif
                    </p>
                    <p class="text-xs text-gray-400">Scroll right to see all columns →</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs whitespace-nowrap divide-y divide-gray-100">
                        <thead class="bg-gray-50 text-[10px] font-semibold text-gray-500 uppercase tracking-wide">
                            <tr>
                                <th class="px-3 py-2 sticky left-0 bg-gray-50 z-10 border-r border-gray-200"></th>
                            <th class="px-3 py-2 text-left">Date</th>
                                <th class="px-3 py-2 text-left">Invoice #</th>
                                <th class="px-3 py-2 text-left">Order #</th>
                                <th class="px-3 py-2 text-left">Customer</th>
                                <th class="px-3 py-2 text-left">Email</th>
                                <th class="px-3 py-2 text-left">Phone</th>
                                <th class="px-3 py-2 text-left">Address</th>
                                <th class="px-3 py-2 text-left">Script Title</th>
                                <th class="px-3 py-2 text-left">Services</th>
                                <th class="px-3 py-2 text-left">SKU</th>
                                <th class="px-3 py-2 text-center">Qty</th>
                                <th class="px-3 py-2 text-right">Total</th>
                                <th class="px-3 py-2 text-right">Discount</th>
                                <th class="px-3 py-2 text-right">COG Reader</th>
                                <th class="px-3 py-2 text-right">COG Proc.</th>
                                <th class="px-3 py-2 text-right">Pre-Comm.</th>
                                <th class="px-3 py-2 text-right">Commission</th>
                                <th class="px-3 py-2 text-right">COG Total</th>
                                <th class="px-3 py-2 text-right">Net Rev.</th>
                                <th class="px-3 py-2 text-left">Payment</th>
                                <th class="px-3 py-2 text-left">Coupon</th>
                                <th class="px-3 py-2 text-left">Staff</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @forelse($orders as $o)
                            <tr class="hover:bg-gray-50">
                                <td class="px-2 py-2 sticky left-0 bg-white z-10 border-r border-gray-100">
                                    <div class="flex items-center gap-1">
                                        <a href="{{ route('order-log.edit', $o) }}"
                                           class="p-1 text-gray-400 hover:text-indigo-600 rounded transition"
                                           title="Edit">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </a>
                                        <form method="POST" action="{{ route('order-log.destroy', $o) }}"
                                              onsubmit="return confirm('Delete order {{ $o->order_number }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="p-1 text-gray-400 hover:text-red-600 rounded transition"
                                                    title="Delete">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                                <td class="px-3 py-2 text-gray-600 font-mono">
                                    {{ $o->ordered_at?->format('Y-m-d') ?? '—' }}
                                </td>
                                <td class="px-3 py-2 text-gray-500 font-mono">{{ $o->invoice_number ?: '—' }}</td>
                                <td class="px-3 py-2 text-gray-600 font-mono">{{ $o->order_number }}</td>
                                <td class="px-3 py-2 text-gray-700 max-w-[140px] truncate">{{ $o->customer_name ?: '—' }}</td>
                                <td class="px-3 py-2 text-gray-500 max-w-[160px] truncate">{{ $o->customer_email ?: '—' }}</td>
                                <td class="px-3 py-2 text-gray-500">{{ $o->customer_phone ?: '—' }}</td>
                                <td class="px-3 py-2 text-gray-400 max-w-[180px] truncate" title="{{ $o->customer_address }}">
                                    {{ $o->customer_address ?: '—' }}
                                </td>
                                <td class="px-3 py-2 text-gray-600 max-w-[160px] truncate" title="{{ $o->script_title }}">
                                    {{ $o->script_title ?: '—' }}
                                </td>
                                <td class="px-3 py-2 text-gray-600 max-w-[200px] truncate" title="{{ $o->ticket_summary ?: $o->services_purchased }}">
                                    {{ $o->ticket_summary ?: $o->services_purchased ?: '—' }}
                                </td>
                                <td class="px-3 py-2 text-gray-500 font-mono">{{ $o->sku ?: '—' }}</td>
                                <td class="px-3 py-2 text-center text-gray-600">{{ $o->order_quantity ?? '—' }}</td>
                                <td class="px-3 py-2 text-right font-medium text-gray-800">${{ number_format($o->order_total, 2) }}</td>
                                <td class="px-3 py-2 text-right text-{{ (float)$o->discount_amount > 0 ? 'amber-600' : 'gray-400' }}">
                                    {{ (float)$o->discount_amount > 0 ? '-$' . number_format($o->discount_amount, 2) : '—' }}
                                </td>
                                <td class="px-3 py-2 text-right text-gray-600">${{ number_format($o->cog_reader, 2) }}</td>
                                <td class="px-3 py-2 text-right text-gray-600">${{ number_format($o->cog_processing, 2) }}</td>
                                <td class="px-3 py-2 text-right text-gray-600">${{ number_format($o->cog_precommission, 2) }}</td>
                                <td class="px-3 py-2 text-right text-gray-600">${{ number_format($o->cog_commission, 2) }}</td>
                                <td class="px-3 py-2 text-right text-gray-700 font-medium">${{ number_format($o->cog_total, 2) }}</td>
                                <td class="px-3 py-2 text-right font-semibold {{ (float)$o->net_revenue >= 0 ? 'text-green-700' : 'text-red-600' }}">
                                    ${{ number_format($o->net_revenue, 2) }}
                                </td>
                                <td class="px-3 py-2 text-gray-500 capitalize">{{ $o->payment_method ?: '—' }}</td>
                                <td class="px-3 py-2 text-gray-500 font-mono">{{ $o->coupon_code ?: '—' }}</td>
                                <td class="px-3 py-2 text-gray-500">{{ $o->staff_member ?: '—' }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="23" class="px-5 py-10 text-center text-sm text-gray-400">
                                    No orders found{{ $q ? ' matching "' . $q . '"' : '' }} for the selected period.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($orders->hasPages())
                <div class="px-5 py-4 border-t border-gray-100">
                    {{ $orders->links() }}
                </div>
                @endif

            </div>
        </div>
    </div>
</x-app-layout>
