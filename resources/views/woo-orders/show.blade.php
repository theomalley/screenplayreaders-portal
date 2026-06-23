<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('order-log.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← Orders</a>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Order #{{ $order['number'] }}
                </h2>
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
                    $statusColor = $statusColors[$order['status']] ?? 'bg-gray-100 text-gray-600';
                    $billingEmail = $order['billing']['email'] ?? 'the customer';
                @endphp
                <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold {{ $statusColor }}">
                    {{ ucfirst($order['status']) }}
                </span>
            </div>
            <span class="text-sm text-gray-500">
                {{ \Carbon\Carbon::parse($order['date_created'])->format('F j, Y g:i A') }}
            </span>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="px-4 py-3 rounded bg-green-50 border border-green-200 text-green-800 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if($errors->has('refund'))
                <div class="px-4 py-3 rounded bg-red-50 border border-red-200 text-red-800 text-sm">
                    <strong>Refund failed:</strong> {{ $errors->first('refund') }}
                </div>
            @endif

            @if($errors->has('email'))
                <div class="px-4 py-3 rounded bg-red-50 border border-red-200 text-red-800 text-sm">
                    <strong>Email error:</strong> {{ $errors->first('email') }}
                </div>
            @endif

            @if($errors->has('api'))
                <div class="px-4 py-3 rounded bg-red-50 border border-red-200 text-red-800 text-sm">
                    <strong>API error:</strong> {{ $errors->first('api') }}
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                {{-- Left: Customer + Order details --}}
                <div class="lg:col-span-2 space-y-6">

                    {{-- Customer --}}
                    <div class="bg-white shadow-sm sm:rounded-lg">
                        <div class="px-4 py-3 border-b border-gray-100">
                            <h3 class="text-sm font-semibold text-gray-700">Customer</h3>
                        </div>
                        <div class="px-4 py-4 grid grid-cols-2 gap-4 text-sm">
                            @php $b = $order['billing'] ?? []; @endphp
                            <div>
                                <div class="text-xs text-gray-400 uppercase tracking-wide mb-1">Name</div>
                                <div class="text-gray-800">{{ trim(($b['first_name'] ?? '') . ' ' . ($b['last_name'] ?? '')) ?: '—' }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-gray-400 uppercase tracking-wide mb-1">Email</div>
                                <div class="text-gray-800">{{ $b['email'] ?? '—' }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-gray-400 uppercase tracking-wide mb-1">Phone</div>
                                <div class="text-gray-800">{{ $b['phone'] ?? '—' }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-gray-400 uppercase tracking-wide mb-1">Billing Address</div>
                                <div class="text-gray-800 text-xs leading-relaxed">
                                    @if(!empty($b['address_1']))
                                        {{ $b['address_1'] }}<br>
                                        @if(!empty($b['address_2'])) {{ $b['address_2'] }}<br> @endif
                                        {{ $b['city'] ?? '' }}@if(!empty($b['state'])), {{ $b['state'] }}@endif {{ $b['postcode'] ?? '' }}<br>
                                        {{ $b['country'] ?? '' }}
                                    @else
                                        —
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Line items --}}
                    <div class="bg-white shadow-sm sm:rounded-lg">
                        <div class="px-4 py-3 border-b border-gray-100">
                            <h3 class="text-sm font-semibold text-gray-700">Line Items</h3>
                        </div>
                        <table class="min-w-full text-sm divide-y divide-gray-100">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Product</th>
                                    <th class="px-4 py-2 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider w-16">Qty</th>
                                    <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider w-24">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach($order['line_items'] ?? [] as $item)
                                    <tr>
                                        <td class="px-4 py-2 text-gray-800">
                                            {{ $item['name'] }}
                                            @if(!empty($item['sku']))
                                                <span class="ml-1 text-xs text-gray-400">{{ $item['sku'] }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-center text-gray-600">{{ $item['quantity'] }}</td>
                                        <td class="px-4 py-2 text-right font-mono text-gray-800">
                                            {{ $order['currency_symbol'] ?? '$' }}{{ number_format((float) $item['total'], 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        {{-- Totals --}}
                        <div class="px-4 py-3 border-t border-gray-100 space-y-1 text-sm">
                            @if((float)($order['discount_total'] ?? 0) > 0)
                                <div class="flex justify-between text-gray-600">
                                    <span>Discount
                                        @if(!empty($order['coupon_lines']))
                                            <span class="text-xs text-gray-400">({{ collect($order['coupon_lines'])->pluck('code')->join(', ') }})</span>
                                        @endif
                                    </span>
                                    <span class="font-mono">−{{ $order['currency_symbol'] ?? '$' }}{{ number_format((float) $order['discount_total'], 2) }}</span>
                                </div>
                            @endif
                            @if((float)($order['shipping_total'] ?? 0) > 0)
                                <div class="flex justify-between text-gray-600">
                                    <span>Shipping</span>
                                    <span class="font-mono">{{ $order['currency_symbol'] ?? '$' }}{{ number_format((float) $order['shipping_total'], 2) }}</span>
                                </div>
                            @endif
                            @if((float)($order['total_tax'] ?? 0) > 0)
                                <div class="flex justify-between text-gray-600">
                                    <span>Tax</span>
                                    <span class="font-mono">{{ $order['currency_symbol'] ?? '$' }}{{ number_format((float) $order['total_tax'], 2) }}</span>
                                </div>
                            @endif
                            <div class="flex justify-between font-semibold text-gray-900 pt-1 border-t border-gray-100">
                                <span>Total</span>
                                <span class="font-mono">{{ $order['currency_symbol'] ?? '$' }}{{ number_format((float) $order['total'], 2) }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- Existing refunds --}}
                    @if(!empty($order['refunds']))
                        <div class="bg-white shadow-sm sm:rounded-lg">
                            <div class="px-4 py-3 border-b border-gray-100">
                                <h3 class="text-sm font-semibold text-gray-700">Refunds</h3>
                            </div>
                            <table class="min-w-full text-sm divide-y divide-gray-100">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Date</th>
                                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Reason</th>
                                        <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider w-24">Amount</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    @foreach($order['refunds'] as $refund)
                                        <tr>
                                            <td class="px-4 py-2 text-xs text-gray-600 whitespace-nowrap">
                                                {{ \Carbon\Carbon::parse($refund['date_created'] ?? '')->format('Y-m-d') }}
                                            </td>
                                            <td class="px-4 py-2 text-xs text-gray-600">{{ $refund['reason'] ?? '—' }}</td>
                                            <td class="px-4 py-2 text-right font-mono text-red-600">
                                                −{{ $order['currency_symbol'] ?? '$' }}{{ number_format(abs((float) $refund['total']), 2) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                </div>

                {{-- Right: Payment info + Actions --}}
                <div class="space-y-6">

                    {{-- Payment info --}}
                    <div class="bg-white shadow-sm sm:rounded-lg">
                        <div class="px-4 py-3 border-b border-gray-100">
                            <h3 class="text-sm font-semibold text-gray-700">Payment</h3>
                        </div>
                        <div class="px-4 py-4 space-y-3 text-sm">
                            <div>
                                <div class="text-xs text-gray-400 uppercase tracking-wide mb-1">Method</div>
                                <div class="text-gray-800">{{ $order['payment_method_title'] ?? '—' }}</div>
                            </div>
                            @if(!empty($order['transaction_id']))
                                <div>
                                    <div class="text-xs text-gray-400 uppercase tracking-wide mb-1">Transaction ID</div>
                                    <div class="text-gray-800 font-mono text-xs break-all">{{ $order['transaction_id'] }}</div>
                                </div>
                            @endif
                            <div>
                                <div class="text-xs text-gray-400 uppercase tracking-wide mb-1">Currency</div>
                                <div class="text-gray-800">{{ $order['currency'] ?? '—' }}</div>
                            </div>
                        </div>
                    </div>

                    {{-- Invoice PDF --}}
                    <div class="bg-white shadow-sm sm:rounded-lg">
                        <div class="px-4 py-3 border-b border-gray-100">
                            <h3 class="text-sm font-semibold text-gray-700">Invoice PDF</h3>
                        </div>
                        <div class="px-4 py-4">
                            @if($errors->has('api'))
                                <p class="text-xs text-red-600 mb-2">{{ $errors->first('api') }}</p>
                            @endif
                            <p class="text-xs text-gray-500 mb-3">
                                Generates a Google Doc invoice from this order and downloads it as a PDF. No data is stored.
                            </p>
                            <a href="{{ route('woo-orders.invoice-pdf', $order['id']) }}"
                               class="inline-flex items-center justify-center w-full px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                Download Invoice PDF
                            </a>
                        </div>
                    </div>

                    {{-- Resend email --}}
                    <div class="bg-white shadow-sm sm:rounded-lg">
                        <div class="px-4 py-3 border-b border-gray-100">
                            <h3 class="text-sm font-semibold text-gray-700">Order Email</h3>
                        </div>
                        <div class="px-4 py-4">
                            <p class="text-xs text-gray-500 mb-3">Customer address: <strong>{{ $billingEmail }}</strong>.</p>
                            <form method="POST" action="{{ route('woo-orders.resend-email', $order['id']) }}"
                                  onsubmit="return confirmResend(this)">
                                @csrf
                                <div class="space-y-3">
                                    <div>
                                        <x-input-label for="test_email" value="Test address (optional)" class="text-xs" />
                                        <x-text-input
                                            id="test_email"
                                            name="test_email"
                                            type="email"
                                            class="mt-1 block w-full text-sm"
                                            placeholder="{{ $billingEmail }}"
                                            value="{{ old('test_email') }}"
                                        />
                                        <p class="mt-1 text-[11px] text-gray-400">Leave blank to send to the customer.</p>
                                    </div>
                                    <x-secondary-button type="submit" class="w-full justify-center text-sm">
                                        Resend Order Email to Customer
                                    </x-secondary-button>
                                </div>
                            </form>
                        </div>
                    </div>

                    {{-- Refund --}}
                    @if($refundableAmount > 0)
                        <div class="bg-white shadow-sm sm:rounded-lg">
                            <div class="px-4 py-3 border-b border-gray-100">
                                <h3 class="text-sm font-semibold text-gray-700">Issue Refund</h3>
                            </div>
                            <div class="px-4 py-4">
                                <p class="text-xs text-gray-500 mb-3">
                                    Refundable: <strong class="text-gray-800">{{ $order['currency_symbol'] ?? '$' }}{{ number_format($refundableAmount, 2) }}</strong>.
                                    The gateway refund will be processed automatically via WooCommerce.
                                </p>
                                <form method="POST" action="{{ route('woo-orders.refund', $order['id']) }}"
                                      onsubmit="return confirm('Issue a refund of $' + document.getElementById('refund-amount').value + '? This cannot be undone.')">
                                    @csrf
                                    <div class="space-y-3">
                                        <div>
                                            <x-input-label for="refund-amount" value="Amount ($)" class="text-xs" />
                                            <x-text-input
                                                id="refund-amount"
                                                name="amount"
                                                type="number"
                                                step="0.01"
                                                min="0.01"
                                                max="{{ $refundableAmount }}"
                                                value="{{ old('amount', number_format($refundableAmount, 2)) }}"
                                                class="mt-1 block w-full text-sm"
                                            />
                                            <x-input-error :messages="$errors->get('amount')" class="mt-1" />
                                        </div>
                                        <div>
                                            <x-input-label for="refund-reason" value="Reason (optional)" class="text-xs" />
                                            <textarea
                                                id="refund-reason"
                                                name="reason"
                                                rows="2"
                                                class="mt-1 block w-full text-sm rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                                placeholder="e.g. Customer requested cancellation"
                                            >{{ old('reason') }}</textarea>
                                        </div>
                                        <x-danger-button type="submit" class="w-full justify-center text-sm">
                                            Issue Refund
                                        </x-danger-button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @else
                        <div class="bg-white shadow-sm sm:rounded-lg">
                            <div class="px-4 py-3 border-b border-gray-100">
                                <h3 class="text-sm font-semibold text-gray-700">Issue Refund</h3>
                            </div>
                            <div class="px-4 py-4 text-xs text-gray-400">
                                This order has been fully refunded or is not eligible for a refund.
                            </div>
                        </div>
                    @endif

                </div>
            </div>
        </div>
    </div>
<script>
function confirmResend(form) {
    const testEmail = form.querySelector('#test_email').value.trim();
    const dest = testEmail ? 'test address: ' + testEmail : '{{ $billingEmail }} (customer)';
    return confirm('Send receipt email to ' + dest + '?');
}
</script>
</x-app-layout>
