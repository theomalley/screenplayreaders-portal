<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Budgets</h2>
            <div class="flex flex-col sm:flex-row gap-2">
                <form method="GET" action="{{ route('budget-orders.index') }}" class="flex gap-2">
                    <input type="hidden" name="status" value="{{ $status }}">
                    <x-text-input
                        name="q"
                        value="{{ $q }}"
                        placeholder="Email, name, order ID…"
                        class="text-sm h-9 py-1.5 w-64"
                    />
                    <x-primary-button class="h-9 py-1.5 text-sm">Search</x-primary-button>
                    @if($q)
                        <a href="{{ route('budget-orders.index', ['status' => $status]) }}"
                           class="inline-flex items-center h-9 px-3 text-sm text-gray-500 hover:text-gray-700">
                            Clear
                        </a>
                    @endif
                </form>

                <form method="GET" action="{{ route('budget-orders.index') }}" id="budget-status-form">
                    <input type="hidden" name="q" value="{{ $q }}">
                    <select name="status" onchange="document.getElementById('budget-status-form').submit()"
                        class="h-9 rounded-md border-gray-300 shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="all" @selected($status === 'all')>All Statuses</option>
                        <option value="pending" @selected($status === 'pending')>Pending</option>
                        <option value="processing" @selected($status === 'processing')>Processing</option>
                        <option value="completed" @selected($status === 'completed')>Completed</option>
                        <option value="failed" @selected($status === 'failed')>Failed</option>
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

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="overflow-x-auto">

                    @if($orders->total() > 0)
                        <div class="px-4 py-2 border-b border-gray-100 text-xs text-gray-500">
                            {{ number_format($orders->total()) }} budget{{ $orders->total() === 1 ? '' : 's' }}
                        </div>
                    @endif

                    <table class="min-w-full text-sm divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-36">Order ID</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Customer</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Email</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider w-28">Budget</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-28">State</th>
                                <th class="px-4 py-2 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider w-24">Type</th>
                                <th class="px-4 py-2 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider w-24">Status</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-28">Created</th>
                                <th class="px-4 py-2 w-16"></th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            @forelse($orders as $order)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2.5 font-mono text-xs text-gray-900">{{ $order->woo_order_id }}</td>
                                    <td class="px-4 py-2.5 text-gray-900">{{ $order->customer_name }}</td>
                                    <td class="px-4 py-2.5 text-gray-500">{{ $order->customer_email }}</td>
                                    <td class="px-4 py-2.5 text-right font-mono text-gray-900">${{ number_format($order->budget_amount, 0) }}</td>
                                    <td class="px-4 py-2.5 text-gray-700">{{ $order->state }}</td>
                                    <td class="px-4 py-2.5 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $order->topsheet_only ? 'bg-gray-100 text-gray-700' : 'bg-blue-100 text-blue-800' }}">
                                            {{ $order->topsheet_only ? 'Topsheet' : 'Full' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5 text-center">
                                        @php
                                            $statusBadge = match($order->status) {
                                                'completed'  => 'bg-green-100 text-green-800',
                                                'processing' => 'bg-blue-100 text-blue-800',
                                                'pending'    => 'bg-amber-100 text-amber-800',
                                                'failed'     => 'bg-red-100 text-red-800',
                                                default      => 'bg-gray-100 text-gray-600',
                                            };
                                        @endphp
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $statusBadge }}">
                                            {{ ucfirst($order->status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5 text-xs text-gray-500">{{ $order->created_at->format('M j, Y') }}</td>
                                    <td class="px-4 py-2.5 text-right">
                                        <a href="{{ route('budget-orders.show', $order) }}"
                                           class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">View</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-4 py-8 text-center text-gray-400">No budget orders found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($orders->hasPages())
                    <div class="px-4 py-3 border-t border-gray-100">
                        {{ $orders->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
