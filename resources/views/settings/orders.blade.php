<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Settings</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">

            @include('settings._nav')

            <div class="space-y-6">

            @if(session('success'))
                <div class="bg-green-50 border border-green-200 rounded-lg px-4 py-3 text-sm text-green-700">
                    {{ session('success') }}
                </div>
            @endif

            @if($isAdmin)

            {{-- Pay Period --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-800 mb-1">Pay Period</h3>
                <p class="text-xs text-gray-500 mb-4">Defines when each pay period opens and closes. Used to group reader earnings and payout calculations. All times are in the app timezone ({{ $appTimezone }}).</p>
                <form method="POST" action="{{ route('settings.pay-period') }}">
                    @csrf
                    @method('PATCH')
                    @php $dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']; @endphp
                    <div class="space-y-3">
                        <div class="flex items-center gap-3">
                            <span class="w-12 text-xs font-medium text-gray-500 shrink-0">Start</span>
                            <select name="period_start_day"
                                    class="text-sm border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                @foreach($dayNames as $i => $name)
                                    <option value="{{ $i }}" @selected($payPeriod['start_day'] === $i)>{{ $name }}</option>
                                @endforeach
                            </select>
                            <input type="time" name="period_start_time" value="{{ $payPeriod['start_time'] }}" step="60"
                                   class="text-sm border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="w-12 text-xs font-medium text-gray-500 shrink-0">End</span>
                            <select name="period_end_day"
                                    class="text-sm border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                @foreach($dayNames as $i => $name)
                                    <option value="{{ $i }}" @selected($payPeriod['end_day'] === $i)>{{ $name }}</option>
                                @endforeach
                            </select>
                            <input type="time" name="period_end_time" value="{{ $payPeriod['end_time'] }}" step="60"
                                   class="text-sm border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>
                    <div class="mt-4">
                        <x-primary-button type="submit">Save</x-primary-button>
                    </div>
                </form>
            </div>

            {{-- Payout Schedule --}}
            @php $payoutDays = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday']; @endphp
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-1">
                    <h3 class="text-sm font-semibold text-gray-800">Payout Schedule</h3>
                    <span class="text-sm text-gray-500">
                        Next payout:
                        @if ($payoutSchedule['override'])
                            <span class="font-semibold text-amber-600">{{ $nextPayout->format('D M j') }} at {{ $nextPayout->format('g:i A') }} PT</span>
                            <span class="ml-1 text-xs text-amber-500">(override active)</span>
                        @else
                            <span class="font-semibold text-gray-700">{{ $nextPayout->format('D M j') }} at {{ $nextPayout->format('g:i A') }} PT</span>
                        @endif
                    </span>
                </div>
                <p class="text-xs text-gray-500 mb-4">Controls how often and when reader and editor payouts go out.</p>

                <form method="POST" action="{{ route('payroll.schedule.update') }}" class="flex flex-wrap items-end gap-4">
                    @csrf @method('PATCH')

                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Frequency</label>
                        <div class="flex gap-4 items-center h-9">
                            <label class="flex items-center gap-1.5 text-sm text-gray-700 cursor-pointer">
                                <input type="radio" name="frequency" value="weekly"
                                    {{ $payoutSchedule['frequency'] === 'weekly' ? 'checked' : '' }}
                                    class="text-indigo-600 focus:ring-indigo-500">
                                Weekly
                            </label>
                            <label class="flex items-center gap-1.5 text-sm text-gray-700 cursor-pointer">
                                <input type="radio" name="frequency" value="biweekly"
                                    {{ $payoutSchedule['frequency'] === 'biweekly' ? 'checked' : '' }}
                                    class="text-indigo-600 focus:ring-indigo-500">
                                Biweekly
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Payout Day</label>
                        <select name="day" class="text-sm border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 h-9">
                            @foreach ($payoutDays as $i => $dayName)
                                <option value="{{ $i }}" {{ $payoutSchedule['day'] === $i ? 'selected' : '' }}>{{ $dayName }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Payout Time (PT)</label>
                        <input type="time" name="time" value="{{ $payoutSchedule['time'] }}"
                            class="text-sm border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 h-9 px-2">
                    </div>

                    <button type="submit"
                        class="h-9 px-4 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-md shadow-sm">
                        Save Schedule
                    </button>
                </form>

                <div class="border-t border-gray-100 mt-4 pt-4">
                    <div class="text-xs font-medium text-gray-500 mb-2 uppercase tracking-wide">Override Next Payout Date</div>
                    <form method="POST" action="{{ route('payroll.schedule.override') }}" class="flex flex-wrap items-end gap-3">
                        @csrf @method('PATCH')
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Date</label>
                            <input type="date" name="override_date"
                                value="{{ $payoutSchedule['override'] ?? '' }}"
                                class="text-sm border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 h-9 px-2">
                        </div>
                        <button type="submit"
                            class="h-9 px-4 text-sm font-medium text-white bg-amber-500 hover:bg-amber-600 rounded-md shadow-sm">
                            Set Override
                        </button>
                        @if ($payoutSchedule['override'])
                        <button type="submit" name="override_date" value=""
                            class="h-9 px-4 text-sm font-medium text-gray-600 bg-white border border-gray-300 hover:bg-gray-50 rounded-md shadow-sm">
                            Clear Override
                        </button>
                        @endif
                    </form>
                </div>
            </div>

            {{-- Invoice Settings --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-800 mb-1">Invoice Settings</h3>
                <p class="text-xs text-gray-500 mb-4">Used when generating client invoices. The SR address can be overridden per-client.</p>

                <form method="POST" action="{{ route('settings.invoice') }}" class="space-y-4">
                    @csrf
                    @method('PATCH')

                    <div>
                        <x-input-label for="sr_invoice_address" value="Screenplay Readers Address (default for invoices)" />
                        <textarea id="sr_invoice_address" name="sr_invoice_address" rows="4"
                            class="mt-1 block w-full text-sm rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                            placeholder="e.g. Screenplay Readers&#10;123 Main St&#10;Los Angeles, CA 90001"
                        >{{ old('sr_invoice_address', $srInvoiceAddress) }}</textarea>
                        <p class="mt-1 text-xs text-gray-400">Appears as the sender address on PDF invoices.</p>
                    </div>

                    <div>
                        <x-input-label for="invoice_email_body" value="PDF Invoice Email Body (Help Scout draft)" />
                        <textarea id="invoice_email_body" name="invoice_email_body" rows="6"
                            class="mt-1 block w-full text-sm rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                            placeholder="Hi,&#10;&#10;Please find your invoice attached…"
                        >{{ old('invoice_email_body', $invoiceEmailBody) }}</textarea>
                        <p class="mt-1 text-xs text-gray-400">Used as the body of the Help Scout draft reply when sending PDF invoices. Plain text or HTML.</p>
                    </div>

                    <div class="flex justify-end">
                        <x-primary-button>Save Invoice Settings</x-primary-button>
                    </div>
                </form>
            </div>

            {{-- Post-Coverage Discount Coupon --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-800 mb-1">Post-Coverage Discount Coupon</h3>
                <p class="text-xs text-gray-500 mb-4">
                    Configure the WooCommerce coupon auto-generated when coverage is approved.
                    The code (SRZ + 8 random chars) is inserted into the completion email via
                    <code class="text-xs bg-gray-100 px-1 rounded">@{{woodiscountcode}}</code>.
                </p>

                <form method="POST" action="{{ route('settings.discount-coupon') }}" class="space-y-4">
                    @csrf
                    @method('PATCH')

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Code Prefix</label>
                            <input type="text" name="discount_coupon_prefix" maxlength="10"
                                   value="{{ old('discount_coupon_prefix', $discountCoupon['discount_coupon_prefix'] ?? 'SRZ') }}"
                                   placeholder="SRZ"
                                   class="block w-full border-gray-300 rounded-md shadow-sm text-sm font-mono uppercase focus:border-indigo-500 focus:ring-indigo-500" />
                            <p class="mt-1 text-xs text-gray-400">Prepended to each generated code (e.g. SRZ → SRZK4M9X2PL)</p>
                            <x-input-error :messages="$errors->get('discount_coupon_prefix')" class="mt-1" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Discount Type</label>
                            <select name="discount_coupon_type"
                                    class="block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="fixed_cart" {{ ($discountCoupon['discount_coupon_type'] ?? '') === 'fixed_cart' ? 'selected' : '' }}>Fixed cart discount ($)</option>
                                <option value="percent" {{ ($discountCoupon['discount_coupon_type'] ?? '') === 'percent' ? 'selected' : '' }}>Percentage discount (%)</option>
                            </select>
                            <x-input-error :messages="$errors->get('discount_coupon_type')" class="mt-1" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Amount</label>
                            <input type="number" name="discount_coupon_amount" step="0.01" min="0"
                                   value="{{ old('discount_coupon_amount', $discountCoupon['discount_coupon_amount'] ?? '10.00') }}"
                                   class="block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            <x-input-error :messages="$errors->get('discount_coupon_amount')" class="mt-1" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Valid for (days)</label>
                            <input type="number" name="discount_coupon_duration_days" min="1" max="3650"
                                   value="{{ old('discount_coupon_duration_days', $discountCoupon['discount_coupon_duration_days'] ?? 30) }}"
                                   class="block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            <x-input-error :messages="$errors->get('discount_coupon_duration_days')" class="mt-1" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Description</label>
                            <input type="text" name="discount_coupon_description"
                                   value="{{ old('discount_coupon_description', $discountCoupon['discount_coupon_description'] ?? '') }}"
                                   placeholder="e.g. $10.00 off your next order"
                                   class="block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            <x-input-error :messages="$errors->get('discount_coupon_description')" class="mt-1" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Usage limit (total)</label>
                            <input type="number" name="discount_coupon_usage_limit" min="0" max="9999"
                                   value="{{ old('discount_coupon_usage_limit', $discountCoupon['discount_coupon_usage_limit'] ?? 1) }}"
                                   class="block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            <p class="mt-1 text-xs text-gray-400">0 = unlimited</p>
                            <x-input-error :messages="$errors->get('discount_coupon_usage_limit')" class="mt-1" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Usage limit per customer</label>
                            <input type="number" name="discount_coupon_usage_limit_per_user" min="0" max="9999"
                                   value="{{ old('discount_coupon_usage_limit_per_user', $discountCoupon['discount_coupon_usage_limit_per_user'] ?? 1) }}"
                                   class="block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            <p class="mt-1 text-xs text-gray-400">0 = unlimited</p>
                            <x-input-error :messages="$errors->get('discount_coupon_usage_limit_per_user')" class="mt-1" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Restrict to product IDs (optional)</label>
                        <input type="text" name="discount_coupon_product_ids"
                               value="{{ old('discount_coupon_product_ids', $discountCoupon['discount_coupon_product_ids'] ?? '') }}"
                               placeholder="e.g. 55560, 55562"
                               class="block w-full border-gray-300 rounded-md shadow-sm text-sm font-mono focus:border-indigo-500 focus:ring-indigo-500" />
                        <p class="mt-1 text-xs text-gray-400">Comma-separated WooCommerce product IDs. Leave blank for sitewide.</p>
                        <x-input-error :messages="$errors->get('discount_coupon_product_ids')" class="mt-1" />
                    </div>

                    <div class="flex flex-wrap gap-6">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="discount_coupon_individual_use" value="1"
                                   {{ ($discountCoupon['discount_coupon_individual_use'] ?? false) ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                            <span class="text-sm text-gray-700">Individual use only</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="discount_coupon_free_shipping" value="1"
                                   {{ ($discountCoupon['discount_coupon_free_shipping'] ?? false) ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                            <span class="text-sm text-gray-700">Grant free shipping</span>
                        </label>
                    </div>

                    <div class="flex justify-end">
                        <x-primary-button>Save</x-primary-button>
                    </div>
                </form>
            </div>

            {{-- Permissions --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-800 mb-1">Permissions</h3>
                <p class="text-xs text-gray-500 mb-4">Controls which roles can access each feature. Admin is always granted all permissions.</p>

                <form method="POST" action="{{ route('admin.permissions.update') }}">
                    @csrf
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm border border-gray-200 rounded-lg overflow-hidden">
                            <thead class="bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Feature</th>
                                    @foreach(\App\Support\Permission::ROLES as $role)
                                        <th class="px-4 py-2.5 text-center text-xs font-semibold uppercase tracking-wide whitespace-nowrap
                                            {{ $role === 'admin' ? 'text-indigo-400' : 'text-gray-500' }}">
                                            {{ ucfirst($role) }}
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach(\App\Support\Permission::FEATURES as $feature => $label)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-2.5 text-gray-700">{{ $label }}</td>
                                        @foreach(\App\Support\Permission::ROLES as $role)
                                            @php
                                                $checked     = $permissionsGrid[$feature][$role] ?? false;
                                                $isAdminRole = $role === 'admin';
                                                $inputName   = 'perm_' . $role . '_' . str_replace('.', '_', $feature);
                                            @endphp
                                            <td class="px-4 py-2.5 text-center">
                                                @if($isAdminRole)
                                                    <span title="Admin always has access"
                                                          class="inline-flex items-center justify-center w-5 h-5 rounded bg-indigo-100 text-indigo-500">
                                                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                        </svg>
                                                    </span>
                                                @else
                                                    <input type="checkbox"
                                                           name="{{ $inputName }}"
                                                           {{ $checked ? 'checked' : '' }}
                                                           class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 cursor-pointer">
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="flex justify-end pt-3">
                        <x-primary-button>Save Permissions</x-primary-button>
                    </div>
                </form>
            </div>

            {{-- Default Editor for New Orders --}}
            @if($editors)
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-800 mb-1">Default Editor for New Orders</h3>
                <p class="text-xs text-gray-500 mb-4">
                    New WooCommerce orders are attributed to this editor (their commission rates apply) unless
                    reassigned in the Order Log. Leave unset to require manual assignment for every new order —
                    recommended once more than one editor is active.
                </p>

                <form method="POST" action="{{ route('settings.default-editor') }}" class="flex items-end gap-3">
                    @csrf
                    @method('PATCH')
                    <div class="flex-1 max-w-xs">
                        <x-input-label for="default_editor_id" value="Default Editor" />
                        <select id="default_editor_id" name="editor_id"
                            class="mt-1 block w-full text-sm border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">Not set (manual assignment required)</option>
                            @foreach($editors as $ed)
                                <option value="{{ $ed->id }}" @selected((string) $defaultEditorId === (string) $ed->id)>
                                    {{ $ed->editorProfile?->displayName() ?? $ed->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <x-primary-button>Save</x-primary-button>
                </form>
            </div>
            @endif

            {{-- Order Log — Editor Visibility --}}
            @if($orderLogEditorSettings)
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-800 mb-1">Order Log — Editor Visibility</h3>
                <p class="text-xs text-gray-500 mb-4">Control which orders and columns editors can see in the Order Log. Admins always see everything.</p>

                <form method="POST" action="{{ route('settings.order-log-editor') }}" class="space-y-5">
                    @csrf
                    @method('PATCH')

                    <fieldset class="space-y-2">
                        <legend class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Hide from editors</legend>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="hidden" name="hide_zero_dollar" value="0">
                            <input type="checkbox" name="hide_zero_dollar" value="1"
                                   {{ $orderLogEditorSettings['hide_zero_dollar'] ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                            $0 orders
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="hidden" name="hide_woo_orders" value="0">
                            <input type="checkbox" name="hide_woo_orders" value="1"
                                   {{ $orderLogEditorSettings['hide_woo_orders'] ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                            WooCommerce orders
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="hidden" name="hide_invoice_orders" value="0">
                            <input type="checkbox" name="hide_invoice_orders" value="1"
                                   {{ $orderLogEditorSettings['hide_invoice_orders'] ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                            Invoice orders (INV-*)
                        </label>
                    </fieldset>

                    <div>
                        <x-input-label for="blocked_product_ids" value="Block orders by product ID" />
                        <x-text-input id="blocked_product_ids" name="blocked_product_ids" type="text"
                            class="mt-1 block w-full text-sm"
                            value="{{ implode(', ', $orderLogEditorSettings['blocked_product_ids']) }}"
                            placeholder="e.g. 123, 456, 789" />
                        <p class="mt-1 text-xs text-gray-400">Comma-separated WooCommerce product IDs. Orders containing these products are hidden from editors.</p>
                    </div>

                    <fieldset class="space-y-2">
                        <legend class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Columns hidden from editors</legend>
                        <p class="text-xs text-gray-400 -mt-1">Checked columns are <em>hidden</em> from editors. Admins always see all columns.</p>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-1.5 mt-2">
                            @foreach($orderLogColumns as $key => $label)
                                <label class="flex items-center gap-2 text-sm text-gray-700">
                                    <input type="checkbox" name="hidden_columns[]" value="{{ $key }}"
                                           {{ in_array($key, $orderLogEditorSettings['hidden_columns']) ? 'checked' : '' }}
                                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>

                    <div class="flex justify-end">
                        <x-primary-button>Save</x-primary-button>
                    </div>
                </form>
            </div>
            @endif

            {{-- HelpScout Webhook Logs --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-gray-800 mb-1">HelpScout Webhook Logs</h3>
                    <p class="text-xs text-gray-500">Inspect incoming HelpScout webhook deliveries (signature status + raw payloads).</p>
                </div>
                <a href="{{ route('admin.helpscout-webhook-logs') }}"
                   class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 rounded-md transition-colors whitespace-nowrap">
                    View Logs
                </a>
            </div>

            @endif
            </div>
        </div>
    </div>
</x-app-layout>
