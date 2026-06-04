<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Revenue by Customer</h2>
                <a href="{{ route('revenue.index', ['period' => $period]) }}"
                   class="text-sm text-indigo-600 hover:text-indigo-800">← All Orders</a>
            </div>
            <form method="GET" action="{{ route('revenue.by-customer') }}">
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
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

            {{-- ── BY WOO CUSTOMER ── --}}
            <div x-data="customerSort()"
                 x-init="rows = @js($byCustomer->map(fn($r) => [
                     'name'        => $r->customer_name ?? '',
                     'email'       => $r->customer_email ?? '',
                     'order_count' => (int) $r->order_count,
                     'gross'       => (float) $r->gross,
                     'discount'    => (float) $r->discount,
                     'net'         => (float) $r->net,
                 ])->values()); sort('net', false)"
                 class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">

                <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">
                        By WooCommerce Customer
                        <span class="ml-2 text-xs font-normal text-gray-400 normal-case">{{ $byCustomer->count() }} customer{{ $byCustomer->count() === 1 ? '' : 's' }}</span>
                    </h3>
                </div>

                @if($byCustomer->isEmpty())
                    <div class="px-5 py-10 text-center text-sm text-gray-400">No orders in this period.</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-xs font-medium text-gray-500 uppercase tracking-wide">
                                <tr>
                                    <th class="px-4 py-3 text-left">#</th>
                                    <th class="px-4 py-3 text-left cursor-pointer hover:text-gray-700 select-none"
                                        @click="sort('name')">
                                        Customer
                                        <span x-show="sortCol === 'name'" x-text="sortAsc ? '↑' : '↓'" class="ml-1"></span>
                                    </th>
                                    <th class="px-4 py-3 text-left">Email</th>
                                    <th class="px-4 py-3 text-right cursor-pointer hover:text-gray-700 select-none"
                                        @click="sort('order_count')">
                                        Orders
                                        <span x-show="sortCol === 'order_count'" x-text="sortAsc ? '↑' : '↓'" class="ml-1"></span>
                                    </th>
                                    <th class="px-4 py-3 text-right cursor-pointer hover:text-gray-700 select-none"
                                        @click="sort('gross')">
                                        Gross
                                        <span x-show="sortCol === 'gross'" x-text="sortAsc ? '↑' : '↓'" class="ml-1"></span>
                                    </th>
                                    <th class="px-4 py-3 text-right cursor-pointer hover:text-gray-700 select-none"
                                        @click="sort('discount')">
                                        Discount
                                        <span x-show="sortCol === 'discount'" x-text="sortAsc ? '↑' : '↓'" class="ml-1"></span>
                                    </th>
                                    <th class="px-4 py-3 text-right cursor-pointer hover:text-gray-700 select-none"
                                        @click="sort('net')">
                                        Net
                                        <span x-show="sortCol === 'net'" x-text="sortAsc ? '↑' : '↓'" class="ml-1"></span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <template x-for="(row, idx) in sorted" :key="row.email">
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-2.5 text-gray-400 tabular-nums text-xs" x-text="idx + 1"></td>
                                        <td class="px-4 py-2.5 font-medium text-gray-800" x-text="row.name || '—'"></td>
                                        <td class="px-4 py-2.5 text-gray-500 text-xs font-mono" x-text="row.email"></td>
                                        <td class="px-4 py-2.5 text-right tabular-nums text-gray-600" x-text="row.order_count"></td>
                                        <td class="px-4 py-2.5 text-right tabular-nums text-gray-700"
                                            x-text="'$' + row.gross.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2})"></td>
                                        <td class="px-4 py-2.5 text-right tabular-nums text-amber-600"
                                            x-text="row.discount > 0 ? '−$' + row.discount.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}) : '—'"></td>
                                        <td class="px-4 py-2.5 text-right tabular-nums font-semibold text-green-700"
                                            x-text="'$' + row.net.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2})"></td>
                                    </tr>
                                </template>
                            </tbody>
                            <tfoot class="bg-gray-50 border-t-2 border-gray-300 text-sm font-semibold">
                                <tr>
                                    <td colspan="3" class="px-4 py-3 text-gray-600">Total ({{ $byCustomer->count() }} customers)</td>
                                    <td class="px-4 py-3 text-right tabular-nums text-gray-700">{{ $byCustomer->sum('order_count') }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums text-gray-700">${{ number_format($byCustomer->sum('gross'), 2) }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums text-amber-600">
                                        @if($byCustomer->sum('discount') > 0)−${{ number_format($byCustomer->sum('discount'), 2) }}@else—@endif
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums text-green-700">${{ number_format($byCustomer->sum('net'), 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif
            </div>

            {{-- ── BY CLIENT ── --}}
            <div x-data="clientSort()"
                 x-init="rows = @js($byClient->map(fn($r) => [
                     'name'        => $r->client_name,
                     'order_count' => (int) $r->order_count,
                     'gross'       => (float) $r->gross,
                     'discount'    => (float) $r->discount,
                     'net'         => (float) $r->net,
                 ])->values()); sort('net', false)"
                 class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">

                <div class="px-5 py-3 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">
                        By Client
                        <span class="ml-2 text-xs font-normal text-gray-400 normal-case">{{ $byClient->count() }} client{{ $byClient->count() === 1 ? '' : 's' }}</span>
                    </h3>
                </div>

                @if($byClient->isEmpty())
                    <div class="px-5 py-10 text-center text-sm text-gray-400">No client-linked orders in this period.</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-xs font-medium text-gray-500 uppercase tracking-wide">
                                <tr>
                                    <th class="px-4 py-3 text-left">#</th>
                                    <th class="px-4 py-3 text-left cursor-pointer hover:text-gray-700 select-none"
                                        @click="sort('name')">
                                        Client
                                        <span x-show="sortCol === 'name'" x-text="sortAsc ? '↑' : '↓'" class="ml-1"></span>
                                    </th>
                                    <th class="px-4 py-3 text-right cursor-pointer hover:text-gray-700 select-none"
                                        @click="sort('order_count')">
                                        Orders
                                        <span x-show="sortCol === 'order_count'" x-text="sortAsc ? '↑' : '↓'" class="ml-1"></span>
                                    </th>
                                    <th class="px-4 py-3 text-right cursor-pointer hover:text-gray-700 select-none"
                                        @click="sort('gross')">
                                        Gross
                                        <span x-show="sortCol === 'gross'" x-text="sortAsc ? '↑' : '↓'" class="ml-1"></span>
                                    </th>
                                    <th class="px-4 py-3 text-right cursor-pointer hover:text-gray-700 select-none"
                                        @click="sort('discount')">
                                        Discount
                                        <span x-show="sortCol === 'discount'" x-text="sortAsc ? '↑' : '↓'" class="ml-1"></span>
                                    </th>
                                    <th class="px-4 py-3 text-right cursor-pointer hover:text-gray-700 select-none"
                                        @click="sort('net')">
                                        Net
                                        <span x-show="sortCol === 'net'" x-text="sortAsc ? '↑' : '↓'" class="ml-1"></span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <template x-for="(row, idx) in sorted" :key="row.name">
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-2.5 text-gray-400 tabular-nums text-xs" x-text="idx + 1"></td>
                                        <td class="px-4 py-2.5 font-medium text-gray-800" x-text="row.name"></td>
                                        <td class="px-4 py-2.5 text-right tabular-nums text-gray-600" x-text="row.order_count"></td>
                                        <td class="px-4 py-2.5 text-right tabular-nums text-gray-700"
                                            x-text="'$' + row.gross.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2})"></td>
                                        <td class="px-4 py-2.5 text-right tabular-nums text-amber-600"
                                            x-text="row.discount > 0 ? '−$' + row.discount.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}) : '—'"></td>
                                        <td class="px-4 py-2.5 text-right tabular-nums font-semibold text-green-700"
                                            x-text="'$' + row.net.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2})"></td>
                                    </tr>
                                </template>
                            </tbody>
                            <tfoot class="bg-gray-50 border-t-2 border-gray-300 text-sm font-semibold">
                                <tr>
                                    <td colspan="2" class="px-4 py-3 text-gray-600">Total ({{ $byClient->count() }} clients)</td>
                                    <td class="px-4 py-3 text-right tabular-nums text-gray-700">{{ $byClient->sum('order_count') }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums text-gray-700">${{ number_format($byClient->sum('gross'), 2) }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums text-amber-600">
                                        @if($byClient->sum('discount') > 0)−${{ number_format($byClient->sum('discount'), 2) }}@else—@endif
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums text-green-700">${{ number_format($byClient->sum('net'), 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif
            </div>

        </div>
    </div>

    @push('scripts')
    <script>
    function makeSorter() {
        return {
            rows: [],
            sortCol: 'net',
            sortAsc: false,
            get sorted() {
                return [...this.rows].sort((a, b) => {
                    const av = a[this.sortCol], bv = b[this.sortCol];
                    const cmp = typeof av === 'string' ? av.localeCompare(bv) : av - bv;
                    return this.sortAsc ? cmp : -cmp;
                });
            },
            sort(col, asc = null) {
                if (this.sortCol === col) {
                    this.sortAsc = !this.sortAsc;
                } else {
                    this.sortCol = col;
                    this.sortAsc = asc !== null ? asc : (col === 'name');
                }
            },
        };
    }
    document.addEventListener('alpine:init', () => {
        Alpine.data('customerSort', makeSorter);
        Alpine.data('clientSort',   makeSorter);
    });
    </script>
    @endpush
</x-app-layout>
