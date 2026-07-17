<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4 flex-wrap">
            <div class="flex items-center gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Orders</h2>
                @if($isAdmin)
                <a href="{{ route('order-log.create') }}"
                   class="inline-flex items-center px-3 py-1.5 bg-indigo-600 border border-transparent rounded text-xs font-medium text-white hover:bg-indigo-700 transition">
                    + Add Order
                </a>
                @endif
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                {{-- Search --}}
                <form method="GET" action="{{ route('order-log.index') }}" class="flex items-center gap-2">
                    <input type="hidden" name="period" value="{{ $period }}">
                    <input type="text" name="q" value="{{ $q }}"
                           placeholder="Order #, invoice #, name, email…"
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

    <div class="py-6" @if($isAdmin) x-data="{
        selected: [],
        allIds: @js($orders->pluck('id')->values()),
        get allChecked() { return this.allIds.length > 0 && this.selected.length === this.allIds.length },
        toggleAll() {
            this.selected = this.allChecked ? [] : [...this.allIds];
        },
        toggle(id) {
            const i = this.selected.indexOf(id);
            i === -1 ? this.selected.push(id) : this.selected.splice(i, 1);
        }
    }" @endif>
        <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-4 px-4 py-3 rounded bg-green-50 border border-green-200 text-green-800 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Bulk action bar --}}
            @if($isAdmin)
            <div x-show="selected.length > 0" x-cloak
                 class="mb-3 flex items-center gap-3 px-4 py-2.5 bg-indigo-50 border border-indigo-200 rounded-lg text-sm">
                <span class="text-indigo-800 font-medium" x-text="selected.length + ' selected'"></span>
                <form method="POST" action="{{ route('order-log.bulk-destroy') }}"
                      x-ref="bulkForm"
                      @submit.prevent="if(confirm('Delete ' + selected.length + ' order(s)? This cannot be undone.')) { $el.submit() }">
                    @csrf
                    <template x-for="id in selected" :key="id">
                        <input type="hidden" name="ids[]" :value="id">
                    </template>
                    <button type="submit"
                            class="inline-flex items-center gap-1 px-3 py-1.5 bg-red-600 border border-transparent rounded text-xs font-medium text-white hover:bg-red-700 transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        Delete Selected
                    </button>
                </form>
                <button type="button" @click="selected = []" class="text-xs text-indigo-600 hover:text-indigo-800">Clear</button>
            </div>
            @endif

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">

                <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                    <p class="text-xs text-gray-500">
                        {{ number_format($orders->total()) }} order{{ $orders->total() === 1 ? '' : 's' }}
                        @if($q) matching <span class="font-medium">"{{ $q }}"</span>@endif
                    </p>
                    <p class="text-xs text-gray-400">Scroll right to see all columns →</p>
                </div>

                @php
                    $vis = array_flip($visibleColumns);
                    $show = fn(string $col) => isset($vis[$col]);
                @endphp

                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs whitespace-nowrap divide-y divide-gray-100">
                        <thead class="bg-gray-50 text-[10px] font-semibold text-gray-500 uppercase tracking-wide">
                            <tr>
                                @if($isAdmin)
                                <th class="px-3 py-2 sticky left-0 bg-gray-50 z-10 border-r border-gray-200">
                                    <div class="flex items-center gap-2">
                                        <input type="checkbox" :checked="allChecked" @click="toggleAll()"
                                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                    </div>
                                </th>
                                @endif
                                @if($show('customer_name'))  <th class="px-3 py-2 text-left">Customer</th>    @endif
                                @if($show('date'))           <th class="px-3 py-2 text-left">Date</th>        @endif
                                @if($show('order_number'))   <th class="px-3 py-2 text-left">Order #</th>     @endif
                                @if($show('email'))          <th class="px-3 py-2 text-left">Email</th>       @endif
                                @if($show('phone'))          <th class="px-3 py-2 text-left">Phone</th>       @endif
                                @if($show('address'))        <th class="px-3 py-2 text-left">Address</th>     @endif
                                @if($show('script_title'))   <th class="px-3 py-2 text-left">Script Title</th>@endif
                                @if($show('services'))       <th class="px-3 py-2 text-left">Services</th>    @endif
                                @if($show('qty'))            <th class="px-3 py-2 text-center">Qty</th>       @endif
                                @if($show('total'))          <th class="px-3 py-2 text-right">Total</th>      @endif
                                @if($show('discount'))       <th class="px-3 py-2 text-right">Discount</th>   @endif
                                @if($show('cog_reader'))     <th class="px-3 py-2 text-right">COG Reader</th> @endif
                                @if($show('cog_processing')) <th class="px-3 py-2 text-right">COG Proc.</th>  @endif
                                @if($show('cog_precommission'))<th class="px-3 py-2 text-right">Pre-Comm.</th>@endif
                                @if($show('cog_commission')) <th class="px-3 py-2 text-right">Commission</th> @endif
                                @if($show('editor'))         <th class="px-3 py-2 text-left">Editor</th>      @endif
                                @if($show('cog_total'))      <th class="px-3 py-2 text-right">COG Total</th>  @endif
                                @if($show('net_revenue'))    <th class="px-3 py-2 text-right">Net Rev.</th>   @endif
                                @if($show('payment_method')) <th class="px-3 py-2 text-left">Payment</th>     @endif
                                @if($show('coupon'))         <th class="px-3 py-2 text-left">Coupon</th>      @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @forelse($orders as $o)
                            @php
                                $isWoo     = (bool) $o->woocommerce_order_id;
                                $isInvoice = str_starts_with($o->order_number, 'INV-');
                                $clickUrl  = $isWoo ? route('woo-orders.show', $o->woocommerce_order_id) : null;
                            @endphp
                            <tr class="hover:bg-gray-50 {{ $clickUrl ? 'cursor-pointer' : '' }}"
                                @if($clickUrl) onclick="window.location='{{ $clickUrl }}'" @endif
                                @if($isAdmin) :class="selected.includes({{ $o->id }}) ? 'bg-indigo-50/50' : ''" @endif>

                                {{-- Admin: checkbox + actions (sticky) --}}
                                @if($isAdmin)
                                <td class="px-2 py-2 sticky left-0 z-10 border-r border-gray-100"
                                    :class="selected.includes({{ $o->id }}) ? 'bg-indigo-50/50' : 'bg-white'"
                                    onclick="event.stopPropagation()">
                                    <div class="flex items-center gap-1.5">
                                        <input type="checkbox" :checked="selected.includes({{ $o->id }})" @click="toggle({{ $o->id }})"
                                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                        <a href="{{ route('order-log.edit', $o) }}"
                                           class="p-1 text-gray-400 hover:text-indigo-600 rounded transition"
                                           title="Edit">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </a>
                                    </div>
                                </td>
                                @endif

                                {{-- Customer Name --}}
                                @if($show('customer_name'))
                                <td class="px-3 py-2 text-gray-700 max-w-[140px] truncate font-medium">{{ $o->customer_name ?: '—' }}</td>
                                @endif

                                {{-- Date --}}
                                @if($show('date'))
                                <td class="px-3 py-2 text-gray-600 font-mono">
                                    {{ $o->ordered_at?->format('Y-m-d') ?? '—' }}
                                </td>
                                @endif

                                {{-- Order # + source badge --}}
                                @if($show('order_number'))
                                <td class="px-3 py-2 font-mono">
                                    @if($isInvoice)
                                        <span class="text-indigo-700">{{ $o->invoice_number ?: $o->order_number }}</span>
                                        <span class="ml-1 inline-flex px-1 py-0.5 rounded text-[9px] font-semibold bg-indigo-100 text-indigo-600 uppercase tracking-wide">invoice</span>
                                    @elseif($isWoo)
                                        <span class="text-gray-600">{{ $o->order_number }}</span>
                                        <span class="ml-1 inline-flex px-1 py-0.5 rounded text-[9px] font-semibold bg-blue-100 text-blue-600 uppercase tracking-wide">woo</span>
                                    @else
                                        <span class="text-gray-600">{{ $o->order_number }}</span>
                                    @endif
                                </td>
                                @endif

                                @if($show('email'))          <td class="px-3 py-2 text-gray-500 max-w-[160px] truncate">{{ $o->customer_email ?: '—' }}</td> @endif
                                @if($show('phone'))          <td class="px-3 py-2 text-gray-500">{{ $o->customer_phone ?: '—' }}</td> @endif
                                @if($show('address'))        <td class="px-3 py-2 text-gray-400 max-w-[180px] truncate" title="{{ $o->customer_address }}">{{ $o->customer_address ?: '—' }}</td> @endif
                                @if($show('script_title'))   <td class="px-3 py-2 text-gray-600 max-w-[160px] truncate" title="{{ $o->script_title }}">{{ $o->script_title ?: '—' }}</td> @endif
                                @if($show('services'))       <td class="px-3 py-2 text-gray-600 max-w-[200px] truncate" title="{{ $o->ticket_summary ?: $o->services_purchased }}">{{ $o->ticket_summary ?: $o->services_purchased ?: '—' }}</td> @endif
                                @if($show('qty'))            <td class="px-3 py-2 text-center text-gray-600 font-mono">{{ $o->order_quantity ?: '—' }}</td> @endif
                                @if($show('total'))          <td class="px-3 py-2 text-right font-medium text-gray-800">${{ number_format($o->order_total, 2) }}</td> @endif
                                @if($show('discount'))       <td class="px-3 py-2 text-right text-{{ (float)$o->discount_amount > 0 ? 'amber-600' : 'gray-400' }}">{{ (float)$o->discount_amount > 0 ? '-$' . number_format($o->discount_amount, 2) : '—' }}</td> @endif
                                @if($show('cog_reader'))     <td class="px-3 py-2 text-right text-gray-600">${{ number_format($o->cog_reader, 2) }}</td> @endif
                                @if($show('cog_processing')) <td class="px-3 py-2 text-right text-gray-600">${{ number_format($o->cog_processing, 2) }}</td> @endif
                                @if($show('cog_precommission'))<td class="px-3 py-2 text-right text-gray-600">${{ number_format($o->cog_precommission, 2) }}</td> @endif
                                @if($show('cog_commission')) <td class="px-3 py-2 text-right text-gray-600">${{ number_format($o->cog_commission, 2) }}</td> @endif
                                @if($show('editor'))         <td class="px-3 py-2 text-gray-600">{{ $o->editor?->editorProfile?->displayName() ?? $o->editor?->name ?? '—' }}</td> @endif
                                @if($show('cog_total'))      <td class="px-3 py-2 text-right text-gray-700 font-medium">${{ number_format($o->cog_total, 2) }}</td> @endif
                                @if($show('net_revenue'))    <td class="px-3 py-2 text-right font-semibold {{ (float)$o->net_revenue >= 0 ? 'text-green-700' : 'text-red-600' }}">${{ number_format($o->net_revenue, 2) }}</td> @endif
                                @if($show('payment_method')) <td class="px-3 py-2 text-gray-500 capitalize">{{ $o->payment_method ?: '—' }}</td> @endif
                                @if($show('coupon'))         <td class="px-3 py-2 text-gray-500 font-mono">{{ $o->coupon_code ?: '—' }}</td> @endif
                            </tr>
                            @empty
                            <tr>
                                <td colspan="{{ count($visibleColumns) + ($isAdmin ? 1 : 0) }}" class="px-5 py-10 text-center text-sm text-gray-400">
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
