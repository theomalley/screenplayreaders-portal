<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-4">
            <a href="{{ route('order-log.index', ['period' => 'all']) }}" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $order ? 'Edit Order — ' . $order->order_number : 'Add Order' }}
            </h2>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            @if ($errors->any())
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-sm text-red-700">
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form id="order-form"
                  method="POST"
                  action="{{ $order ? route('order-log.update', $order) : route('order-log.store') }}">
                @csrf
                @if($order) @method('PATCH') @endif

                {{-- ORDER INFO --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Order Info</p>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="ordered_at" value="Date" />
                            <x-text-input id="ordered_at" name="ordered_at" type="date"
                                class="mt-1 block w-full"
                                value="{{ old('ordered_at', $order?->ordered_at?->format('Y-m-d')) }}"
                                required />
                            <x-input-error :messages="$errors->get('ordered_at')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="order_number" value="Order #" />
                            <x-text-input id="order_number" name="order_number" type="text"
                                class="mt-1 block w-full font-mono"
                                value="{{ old('order_number', $order?->order_number) }}"
                                required />
                            <x-input-error :messages="$errors->get('order_number')" class="mt-1" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="invoice_number" value="Invoice #" />
                            <x-text-input id="invoice_number" name="invoice_number" type="text"
                                class="mt-1 block w-full font-mono"
                                value="{{ old('invoice_number', $order?->invoice_number) }}" />
                            <x-input-error :messages="$errors->get('invoice_number')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="order_quantity" value="Quantity" />
                            <x-text-input id="order_quantity" name="order_quantity" type="number" min="0"
                                class="mt-1 block w-full"
                                value="{{ old('order_quantity', $order?->order_quantity) }}" />
                            <x-input-error :messages="$errors->get('order_quantity')" class="mt-1" />
                        </div>
                    </div>
                </div>

                {{-- CUSTOMER --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4 mt-4">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Customer</p>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="customer_name" value="Name" />
                            <x-text-input id="customer_name" name="customer_name" type="text"
                                class="mt-1 block w-full"
                                value="{{ old('customer_name', $order?->customer_name) }}" />
                            <x-input-error :messages="$errors->get('customer_name')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="customer_email" value="Email" />
                            <x-text-input id="customer_email" name="customer_email" type="email"
                                class="mt-1 block w-full"
                                value="{{ old('customer_email', $order?->customer_email) }}" />
                            <x-input-error :messages="$errors->get('customer_email')" class="mt-1" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="customer_phone" value="Phone" />
                            <x-text-input id="customer_phone" name="customer_phone" type="text"
                                class="mt-1 block w-full"
                                value="{{ old('customer_phone', $order?->customer_phone) }}" />
                            <x-input-error :messages="$errors->get('customer_phone')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="customer_address" value="Address" />
                            <x-text-input id="customer_address" name="customer_address" type="text"
                                class="mt-1 block w-full"
                                value="{{ old('customer_address', $order?->customer_address) }}" />
                            <x-input-error :messages="$errors->get('customer_address')" class="mt-1" />
                        </div>
                    </div>
                </div>

                {{-- SCRIPT / SERVICES --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4 mt-4">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Script / Services</p>

                    <div>
                        <x-input-label for="script_title" value="Script Title" />
                        <x-text-input id="script_title" name="script_title" type="text"
                            class="mt-1 block w-full"
                            value="{{ old('script_title', $order?->script_title) }}" />
                        <x-input-error :messages="$errors->get('script_title')" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="services_purchased" value="Services Purchased" />
                            <x-text-input id="services_purchased" name="services_purchased" type="text"
                                class="mt-1 block w-full"
                                value="{{ old('services_purchased', $order?->services_purchased) }}" />
                            <x-input-error :messages="$errors->get('services_purchased')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="ticket_summary" value="Ticket Summary" />
                            <x-text-input id="ticket_summary" name="ticket_summary" type="text"
                                class="mt-1 block w-full"
                                value="{{ old('ticket_summary', $order?->ticket_summary) }}" />
                            <x-input-error :messages="$errors->get('ticket_summary')" class="mt-1" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="sku" value="SKU" />
                        <x-text-input id="sku" name="sku" type="text"
                            class="mt-1 block w-full font-mono"
                            value="{{ old('sku', $order?->sku) }}" />
                        <x-input-error :messages="$errors->get('sku')" class="mt-1" />
                    </div>
                </div>

                {{-- FINANCIALS --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4 mt-4">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Financials</p>

                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <x-input-label for="order_total" value="Order Total" />
                            <div class="mt-1 relative">
                                <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 text-sm">$</span>
                                <x-text-input id="order_total" name="order_total" type="number" step="0.01"
                                    class="block w-full pl-7"
                                    value="{{ old('order_total', $order?->order_total) }}"
                                    required />
                            </div>
                            <x-input-error :messages="$errors->get('order_total')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="discount_amount" value="Discount" />
                            <div class="mt-1 relative">
                                <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 text-sm">$</span>
                                <x-text-input id="discount_amount" name="discount_amount" type="number" step="0.01"
                                    class="block w-full pl-7"
                                    value="{{ old('discount_amount', $order?->discount_amount ?? 0) }}" />
                            </div>
                            <x-input-error :messages="$errors->get('discount_amount')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="net_revenue" value="Net Revenue" />
                            <div class="mt-1 relative">
                                <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 text-sm">$</span>
                                <x-text-input id="net_revenue" name="net_revenue" type="number" step="0.01"
                                    class="block w-full pl-7"
                                    value="{{ old('net_revenue', $order?->net_revenue ?? 0) }}" />
                            </div>
                            <x-input-error :messages="$errors->get('net_revenue')" class="mt-1" />
                        </div>
                    </div>

                    <div class="pt-2 border-t border-gray-100">
                        <p class="text-xs text-gray-400 mb-3">Cost of Goods</p>
                        <div class="grid grid-cols-2 gap-4 sm:grid-cols-5">
                            @foreach([
                                'cog_reader'        => 'Reader',
                                'cog_processing'    => 'Processing',
                                'cog_precommission' => 'Pre-Comm.',
                                'cog_commission'    => 'Commission',
                                'cog_total'         => 'COG Total',
                            ] as $field => $label)
                            <div>
                                <x-input-label :for="$field" :value="$label" />
                                <div class="mt-1 relative">
                                    <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 text-sm">$</span>
                                    <x-text-input :id="$field" :name="$field" type="number" step="0.01"
                                        class="block w-full pl-7"
                                        :value="old($field, $order?->{$field} ?? 0)" />
                                </div>
                                <x-input-error :messages="$errors->get($field)" class="mt-1" />
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- PAYMENT --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4 mt-4">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Payment</p>

                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <x-input-label for="payment_method" value="Payment Method" />
                            <x-text-input id="payment_method" name="payment_method" type="text"
                                class="mt-1 block w-full"
                                value="{{ old('payment_method', $order?->payment_method) }}" />
                            <x-input-error :messages="$errors->get('payment_method')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="coupon_code" value="Coupon Code" />
                            <x-text-input id="coupon_code" name="coupon_code" type="text"
                                class="mt-1 block w-full font-mono"
                                value="{{ old('coupon_code', $order?->coupon_code) }}" />
                            <x-input-error :messages="$errors->get('coupon_code')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="staff_member" value="Staff Member" />
                            <x-text-input id="staff_member" name="staff_member" type="text"
                                class="mt-1 block w-full"
                                value="{{ old('staff_member', $order?->staff_member) }}" />
                            <x-input-error :messages="$errors->get('staff_member')" class="mt-1" />
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="hidden" name="skip_commission" value="0">
                        <input type="checkbox" id="skip_commission" name="skip_commission" value="1"
                               {{ old('skip_commission', $order?->skip_commission) ? 'checked' : '' }}
                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                        <x-input-label for="skip_commission" value="Skip commission (no staff pay)" class="mb-0 cursor-pointer" />
                    </div>
                </div>

            </form>

            {{-- ACTIONS — outside the form to prevent nesting the delete form inside order-form --}}
            <div class="flex items-center justify-between mt-4">
                @if($order)
                    <form method="POST" action="{{ route('order-log.destroy', $order) }}"
                          onsubmit="return confirm('Delete this order record? This cannot be undone.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="inline-flex items-center px-3 py-1.5 bg-white border border-red-300 rounded text-xs font-medium text-red-600 hover:bg-red-50 transition">
                            Delete Order
                        </button>
                    </form>
                @else
                    <div></div>
                @endif
                <div class="flex items-center gap-3">
                    <a href="{{ route('order-log.index', ['period' => 'all']) }}"
                       class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
                    <x-primary-button form="order-form">
                        {{ $order ? 'Save Changes' : 'Create Order' }}
                    </x-primary-button>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
