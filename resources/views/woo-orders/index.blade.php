<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">WooCommerce Orders</h2>
            <div class="flex flex-col sm:flex-row gap-2">
                {{-- Search --}}
                <form method="GET" action="{{ route('woo-orders.index') }}" class="flex gap-2">
                    <input type="hidden" name="status" value="{{ $status }}">
                    <x-text-input
                        name="q"
                        value="{{ $q }}"
                        placeholder="Order #, name, email…"
                        class="text-sm h-9 py-1.5 w-56"
                    />
                    <x-primary-button class="h-9 py-1.5 text-sm">Search</x-primary-button>
                    @if($q)
                        <a href="{{ route('woo-orders.index', ['status' => $status]) }}"
                           class="inline-flex items-center h-9 px-3 text-sm text-gray-500 hover:text-gray-700">
                            Clear
                        </a>
                    @endif
                </form>

                {{-- Status filter --}}
                <form method="GET" action="{{ route('woo-orders.index') }}" id="status-form">
                    <input type="hidden" name="q" value="{{ $q }}">
                    <select name="status" onchange="document.getElementById('status-form').submit()"
                        class="h-9 rounded-md border-gray-300 shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500">
                        @foreach($statuses as $key => $label)
                            <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-4 px-4 py-3 rounded bg-green-50 border border-green-200 text-green-800 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if($error)
                <div class="mb-4 px-4 py-3 rounded bg-red-50 border border-red-200 text-red-800 text-sm">
                    <strong>WooCommerce API error:</strong> {{ $error }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="overflow-x-auto">

                    {{-- Summary row --}}
                    @if($total > 0)
                        <div class="px-4 py-2 border-b border-gray-100 text-xs text-gray-500">
                            {{ number_format($total) }} order{{ $total === 1 ? '' : 's' }}
                            @if($totalPages > 1)
                                &mdash; page {{ $page }} of {{ $totalPages }}
                            @endif
                        </div>
                    @endif

                    <table class="min-w-full text-sm divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-24">Order</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-28">Date</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Customer</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Email</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider w-24">Total</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-28">Status</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Payment</th>
                                <th class="px-4 py-2 w-16"></th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            @forelse($orders as $o)
                                @php
                                    $statusColors = [
                                        'pending'    => 'bg-yellow-100 text-yellow-800',
                                        'processing' => 'bg-blue-100 text-blue-800',
                                        'on-hold'    => 'bg-orange-100 text-orange-800',
                                        'completed'  => 'bg-green-100 text-green-800',
                                        'cancelled'  => 'bg-gray-100 text-gray-600',
                                        'refunded'   => 'bg-red-100 text-red-700',
                                        'failed'     => 'bg-red-100 text-red-700',
                                    ];
                                    $statusColor = $statusColors[$o['status']] ?? 'bg-gray-100 text-gray-600';
                                    $customerName = trim(($o['billing']['first_name'] ?? '') . ' ' . ($o['billing']['last_name'] ?? ''));
                                    $date = \Carbon\Carbon::parse($o['date_created'])->format('Y-m-d');
                                @endphp
                                <tr class="hover:bg-gray-50 transition-colors cursor-pointer"
                                    onclick="window.location='{{ route('woo-orders.show', $o['id']) }}'">
                                    <td class="px-4 py-2 font-mono text-xs text-indigo-600 font-semibold whitespace-nowrap">
                                        #{{ $o['number'] }}
                                    </td>
                                    <td class="px-4 py-2 text-xs text-gray-600 whitespace-nowrap">{{ $date }}</td>
                                    <td class="px-4 py-2 text-xs text-gray-800">{{ $customerName ?: '—' }}</td>
                                    <td class="px-4 py-2 text-xs text-gray-600">{{ $o['billing']['email'] ?? '—' }}</td>
                                    <td class="px-4 py-2 text-xs text-right font-mono text-gray-800 whitespace-nowrap">
                                        {{ $o['currency_symbol'] ?? '$' }}{{ number_format((float) $o['total'], 2) }}
                                    </td>
                                    <td class="px-4 py-2">
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $statusColor }}">
                                            {{ ucfirst($o['status']) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-xs text-gray-600">{{ $o['payment_method_title'] ?? '—' }}</td>
                                    <td class="px-4 py-2 text-right" onclick="event.stopPropagation()">
                                        <a href="{{ route('woo-orders.show', $o['id']) }}"
                                           class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">View</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-8 text-center text-sm text-gray-400">
                                        @if($error)
                                            Could not load orders.
                                        @elseif($q)
                                            No orders match "{{ $q }}".
                                        @else
                                            No orders found.
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                @if($totalPages > 1)
                    <div class="px-4 py-3 border-t border-gray-100 flex items-center justify-between text-xs text-gray-500">
                        <span>Page {{ $page }} of {{ $totalPages }}</span>
                        <div class="flex gap-2">
                            @if($page > 1)
                                <a href="{{ route('woo-orders.index', ['page' => $page - 1, 'q' => $q, 'status' => $status]) }}"
                                   class="px-3 py-1 rounded border border-gray-200 hover:bg-gray-50">← Prev</a>
                            @endif
                            @if($page < $totalPages)
                                <a href="{{ route('woo-orders.index', ['page' => $page + 1, 'q' => $q, 'status' => $status]) }}"
                                   class="px-3 py-1 rounded border border-gray-200 hover:bg-gray-50">Next →</a>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
