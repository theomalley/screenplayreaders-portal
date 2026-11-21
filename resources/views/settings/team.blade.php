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

            @if($errors->any())
                <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm text-red-700 space-y-1">
                    @foreach($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <div class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-3 text-xs text-blue-800">
                Readers can never see what tier they (or anyone else) are in — tier assignment and all settings below are admin/editor-only.
            </div>

            {{-- ===== PER-TIER CARDS ===== --}}
            @foreach ($tiers as $tier)
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-semibold text-gray-800">
                            {{ $tier->name }}
                            @if($tier->is_onboarding)
                                <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-amber-100 text-amber-800 uppercase tracking-wide">Onboarding</span>
                            @endif
                        </h3>
                        @unless($tier->is_onboarding)
                            <form method="POST" action="{{ route('tiers.destroy', $tier) }}"
                                  onsubmit="return confirm('Delete tier &quot;{{ $tier->name }}&quot;? This only works if no readers or assignments are currently in it.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs text-red-500 hover:text-red-700 hover:underline">Delete</button>
                            </form>
                        @endunless
                    </div>

                    <form method="POST" action="{{ route('tiers.update', $tier) }}" class="space-y-4">
                        @csrf
                        @method('PATCH')

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="name_{{ $tier->id }}" value="Name" />
                                <x-text-input id="name_{{ $tier->id }}" name="name" type="text"
                                    value="{{ $tier->name }}" class="mt-1 block w-full text-sm" required />
                            </div>
                            <div>
                                <x-input-label for="position_{{ $tier->id }}" value="Display order" />
                                <input type="number" id="position_{{ $tier->id }}" name="position" min="0" step="1"
                                    value="{{ $tier->position }}"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="timeout_hours_{{ $tier->id }}" value="Timeout (hours)" />
                                <p class="text-xs text-gray-400 mb-1">If nobody in this tier accepts within this many hours, the assignment transfers to the tier below. Leave blank to disable.</p>
                                <input type="number" id="timeout_hours_{{ $tier->id }}" name="timeout_hours" min="1" max="8760"
                                    value="{{ $tier->timeout_hours }}"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                            </div>
                            <div>
                                <x-input-label for="escalates_to_tier_id_{{ $tier->id }}" value="Move to what tier after timeout" />
                                <select id="escalates_to_tier_id_{{ $tier->id }}" name="escalates_to_tier_id"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                    <option value="">— None —</option>
                                    @foreach ($tiers as $target)
                                        @continue($target->id === $tier->id || $target->is_onboarding)
                                        <option value="{{ $target->id }}" @selected($tier->escalates_to_tier_id === $target->id)>{{ $target->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div>
                            <x-input-label value="Assignment types this tier can see and accept" />
                            <p class="text-xs text-gray-400 mb-1">Leave all unchecked for no restriction (this tier sees every type).</p>
                            <div class="flex flex-wrap gap-x-4 gap-y-1.5 mt-1">
                                @foreach ($assignmentTypes as $value => $label)
                                    <label class="flex items-center gap-1.5 text-xs text-gray-700">
                                        <input type="checkbox" name="allowed_assignment_types[]" value="{{ $value }}"
                                            @checked(in_array($value, $tier->allowed_assignment_types ?? []))
                                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                        {{ $label }}
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <x-primary-button>Save</x-primary-button>
                    </form>
                </div>
            @endforeach

            {{-- ===== ADD TIER ===== --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-800 mb-3">Add a Tier</h3>
                <form method="POST" action="{{ route('tiers.store') }}" class="flex items-end gap-3 flex-wrap">
                    @csrf
                    <div>
                        <x-input-label for="new_tier_name" value="Name" />
                        <x-text-input id="new_tier_name" name="name" type="text" placeholder="e.g. Tier 3"
                            class="mt-1 block w-56 text-sm" required />
                    </div>
                    <x-primary-button>Create Tier</x-primary-button>
                </form>
            </div>

            {{-- ===== CROSS-TIER VISIBILITY MATRIX ===== --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-800 mb-1">Cross-Tier Visibility</h3>
                <p class="text-xs text-gray-500 mb-4">
                    For each row (a reader's own tier), check the columns whose assignment pool that reader can also see and accept from.
                    A row for the onboarding tier only grants <strong>viewing</strong> — onboarding readers can never accept outside their sandbox assignment, no matter what's checked here.
                </p>

                <form method="POST" action="{{ route('tiers.visibility.update') }}">
                    @csrf
                    @method('PATCH')
                    <div class="overflow-x-auto">
                        <table class="text-sm border-collapse">
                            <thead>
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">From \ To</th>
                                    @foreach ($tiers as $toTier)
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">{{ $toTier->name }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($tiers as $fromTier)
                                    <tr class="border-t border-gray-100">
                                        <td class="px-3 py-2 font-medium text-gray-700 whitespace-nowrap">
                                            {{ $fromTier->name }}
                                            @if($fromTier->is_onboarding)
                                                <span class="block text-[10px] font-normal text-gray-400">(view only)</span>
                                            @endif
                                        </td>
                                        @foreach ($tiers as $toTier)
                                            <td class="px-3 py-2 text-center">
                                                @if($fromTier->id === $toTier->id)
                                                    <span class="text-gray-300">—</span>
                                                @else
                                                    @php
                                                        $existing = optional($crossVisibility->get($fromTier->id))
                                                            ->firstWhere('to_tier_id', $toTier->id);
                                                    @endphp
                                                    <input type="checkbox"
                                                           name="visibility[{{ $fromTier->id }}][{{ $toTier->id }}]"
                                                           value="1"
                                                           @checked($existing?->can_view)
                                                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <x-primary-button class="mt-4">Save Visibility Matrix</x-primary-button>
                </form>
            </div>

            @if($isAdmin)

            {{-- ===== READER SETTINGS ===== --}}
            <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-widest pt-2">Reader Settings</h3>

            {{-- Reader Download Watermark --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6"
                 x-data="{
                     showName: {{ $watermarkSettings['watermark_show_name'] ? 'true' : 'false' }},
                     showOrder: {{ $watermarkSettings['watermark_show_order'] ? 'true' : 'false' }},
                     showDatetime: {{ $watermarkSettings['watermark_show_datetime'] ? 'true' : 'false' }},
                     showRef: {{ $watermarkSettings['watermark_show_ref'] ? 'true' : 'false' }},
                     customText: @js($watermarkSettings['watermark_custom_text']),
                     get preview() {
                         const parts = [];
                         if (this.customText.trim()) parts.push(this.customText.trim());
                         if (this.showName) parts.push('Jane Reader');
                         if (this.showOrder) parts.push('Order #SR-12345');
                         if (this.showDatetime) parts.push('Jun 10, 2026 3:45pm');
                         if (this.showRef) parts.push('Ref DL-42');
                         return parts.length ? parts.join(' · ') : 'Screenplay Readers';
                     }
                 }">
                <h3 class="text-sm font-semibold text-gray-800 mb-1">Reader Download Watermark</h3>
                <p class="text-xs text-gray-500 mb-4">Choose which fields appear in the diagonal watermark tiled across scripts readers download.</p>

                <form method="POST" action="{{ route('settings.watermark') }}" class="space-y-4">
                    @csrf
                    @method('PATCH')

                    <div class="space-y-2">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" name="watermark_show_name" value="1" x-model="showName"
                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                            <span class="text-sm text-gray-700">Reader name</span>
                        </label>
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" name="watermark_show_order" value="1" x-model="showOrder"
                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                            <span class="text-sm text-gray-700">Order number</span>
                        </label>
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" name="watermark_show_datetime" value="1" x-model="showDatetime"
                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                            <span class="text-sm text-gray-700">Download date &amp; time</span>
                        </label>
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" name="watermark_show_ref" value="1" x-model="showRef"
                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                            <span class="text-sm text-gray-700">Download reference ID</span>
                        </label>
                    </div>

                    <div>
                        <x-input-label for="watermark_custom_text" value="Custom text (optional)" />
                        <input type="text" id="watermark_custom_text" name="watermark_custom_text" maxlength="200"
                               x-model="customText"
                               placeholder="e.g. Confidential — Property of Screenplay Readers"
                               class="mt-1 block w-full text-sm rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" />
                        <p class="mt-1 text-xs text-gray-400">Prepended to the watermark, e.g. a confidentiality notice or company name.</p>
                        <x-input-error class="mt-1" :messages="$errors->get('watermark_custom_text')" />
                    </div>

                    <div>
                        <p class="text-xs text-gray-500 mb-1">Preview:</p>
                        <p class="text-sm font-mono bg-gray-50 border border-gray-200 rounded px-3 py-2 text-gray-600" x-text="preview"></p>
                    </div>

                    <div class="flex justify-end">
                        <x-primary-button>Save Watermark Settings</x-primary-button>
                    </div>
                </form>
            </div>

            {{-- Block-Reader Limits --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-800 mb-1">Block-Reader Limits</h3>
                <p class="text-xs text-gray-500 mb-4">
                    Sets how many readers a customer can block on the script upload form. Applies to coverage orders only.
                </p>

                <form method="POST" action="{{ route('settings.blocked-reader-limits') }}" class="space-y-4">
                    @csrf
                    @method('PATCH')
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                        <div>
                            <x-input-label value="1-reader orders" />
                            <input type="number" name="max_blockable_1r" min="0" max="10"
                                value="{{ old('max_blockable_1r', $blockedReaderLimits['max_blockable_1r']) }}"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                            <p class="mt-0.5 text-xs text-gray-400">Max readers a customer can block</p>
                        </div>
                        <div>
                            <x-input-label value="2-reader / 3-reader orders" />
                            <input type="number" name="max_blockable_multi" min="0" max="10"
                                value="{{ old('max_blockable_multi', $blockedReaderLimits['max_blockable_multi']) }}"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                            <p class="mt-0.5 text-xs text-gray-400">Max readers a customer can block</p>
                        </div>
                    </div>
                    <div class="flex justify-end border-t border-gray-100 pt-4">
                        <x-primary-button>Save block-reader limits</x-primary-button>
                    </div>
                </form>
            </div>

            {{-- ===== EDITOR SETTINGS ===== --}}
            <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-widest pt-2">Editor Settings</h3>

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

            {{-- QC Commission Penalty --}}
            @if($qcCommissionPenaltyEnabled !== null)
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-800 mb-1">QC Commission Penalty</h3>
                <p class="text-xs text-gray-500 mb-4">
                    When enabled, an order's assigned editor only earns full commission if they personally
                    approve QC on every reader assignment for that order. If they approve some but not all
                    (e.g. 2R/3R orders), commission is pro-rated by how many they personally did. If they
                    approve none of them, commission is reduced to 50%.
                </p>

                <form method="POST" action="{{ route('settings.qc-commission-penalty') }}">
                    @csrf
                    @method('PATCH')
                    <label class="flex items-center gap-2 mb-4">
                        <input type="checkbox" name="qc_commission_penalty_enabled" value="1"
                            @checked($qcCommissionPenaltyEnabled)
                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        <span class="text-sm text-gray-700">Reduce editor commission when they skip QC</span>
                    </label>
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

            @endif
            </div>
        </div>
    </div>
</x-app-layout>
