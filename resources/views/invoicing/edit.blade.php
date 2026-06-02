<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-4">
            <a href="{{ route('invoicing.index') }}" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Edit Invoice #{{ $invoice->invoice_number }}
                <span class="text-sm font-normal text-gray-400 ml-2">{{ $invoice->client?->name }}</span>
            </h2>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if($errors->has('invoice'))
                <div class="px-4 py-3 rounded bg-red-50 border border-red-200 text-red-800 text-sm">
                    {{ $errors->first('invoice') }}
                </div>
            @endif

            <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('invoiceEdit', () => ({
                    items: @json(old('items', $lineItems->map(fn($i) => ['description' => $i->description, 'amount' => $i->amount])->values()->all())),
                    get total() {
                        return this.items.reduce((s, i) => s + (parseFloat(i.amount) || 0), 0);
                    },
                    addItem() {
                        if (this.items.length < 8) this.items.push({ description: '', amount: '' });
                    },
                    removeItem(idx) {
                        if (this.items.length > 1) this.items.splice(idx, 1);
                    }
                }));
            });
            </script>

            <div class="bg-white shadow-sm sm:rounded-lg" x-data="invoiceEdit">
                <div class="px-4 py-5">
                    <form method="POST" action="{{ route('invoices.update', $invoice) }}" class="space-y-5"
                          onsubmit="return confirm('Save changes and regenerate PDF?')">
                        @csrf
                        @method('PATCH')

                        {{-- Line items --}}
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <x-input-label value="Line Items" />
                                <span class="text-xs text-gray-400" x-text="items.length + '/8 items'"></span>
                            </div>

                            <div class="space-y-2">
                                <template x-for="(item, idx) in items" :key="idx">
                                    <div class="flex gap-2 items-start">
                                        <div class="flex-1">
                                            <input type="text"
                                                   :name="'items[' + idx + '][description]'"
                                                   x-model="item.description"
                                                   placeholder="Description"
                                                   maxlength="1000"
                                                   required
                                                   class="block w-full text-sm rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" />
                                        </div>
                                        <div class="relative w-28 shrink-0">
                                            <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 text-sm pointer-events-none">$</span>
                                            <input type="number"
                                                   :name="'items[' + idx + '][amount]'"
                                                   x-model="item.amount"
                                                   placeholder="0.00"
                                                   step="0.01" min="0.01"
                                                   required
                                                   class="block w-full pl-7 text-sm rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" />
                                        </div>
                                        <button type="button"
                                                @click="removeItem(idx)"
                                                x-show="items.length > 1"
                                                class="mt-1 text-gray-300 hover:text-red-400 transition text-lg leading-none shrink-0">&times;</button>
                                        <span x-show="items.length <= 1" class="w-5 shrink-0"></span>
                                    </div>
                                </template>
                            </div>

                            <div class="flex items-center justify-between mt-3">
                                <button type="button"
                                        @click="addItem()"
                                        x-show="items.length < 8"
                                        class="text-xs text-indigo-600 hover:text-indigo-800 transition">
                                    + Add line item
                                </button>
                                <div class="text-sm font-semibold text-gray-700 ml-auto">
                                    Total: <span class="text-green-700" x-text="'$' + total.toFixed(2)"></span>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="due_date" value="Due Date (optional)" />
                                <x-text-input id="due_date" name="due_date" type="date"
                                    class="mt-1 block w-full"
                                    value="{{ old('due_date', $invoice->due_date?->format('Y-m-d')) }}" />
                            </div>
                        </div>

                        <div>
                            <x-input-label for="notes" value="Notes (internal, optional)" />
                            <textarea id="notes" name="notes" rows="2"
                                class="mt-1 block w-full text-sm rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                            >{{ old('notes', $invoice->notes) }}</textarea>
                        </div>

                        <div class="flex items-center gap-4 pt-2">
                            <x-primary-button>Save &amp; Regenerate PDF</x-primary-button>
                            <a href="{{ route('invoicing.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
