<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-4">
            <a href="{{ route('invoicing.index') }}" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Create Invoice</h2>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if($errors->has('invoice'))
                <div class="px-4 py-3 rounded bg-red-50 border border-red-200 text-red-800 text-sm">
                    {{ $errors->first('invoice') }}
                </div>
            @endif

            @php
                $clientMeta = $clients->mapWithKeys(fn($c) => [
                    $c->id => ['invoice_type' => $c->invoice_type]
                ])->toJson();
            @endphp

            <div class="bg-white shadow-sm sm:rounded-lg"
                 x-data="{
                     recipientType: '{{ old('recipient_type', 'client') }}',
                     clientId: '{{ old('client_id', request('client')) }}',
                     clientMeta: {{ $clientMeta }},
                     get selectedClient() { return this.clientId ? this.clientMeta[this.clientId] : null; },
                     items: @json(old('items', [['description' => '', 'amount' => '']])),
                     get total() {
                         return this.items.reduce((s, i) => s + (parseFloat(i.amount) || 0), 0);
                     },
                     addItem() {
                         if (this.items.length < 8) this.items.push({ description: '', amount: '' });
                     },
                     removeItem(idx) {
                         if (this.items.length > 1) this.items.splice(idx, 1);
                     }
                 }">

                {{-- Recipient type toggle --}}
                <div class="px-4 pt-5 pb-3 border-b border-gray-100">
                    <p class="text-xs text-gray-500 mb-3">Who is this invoice for?</p>
                    <div class="inline-flex rounded-md shadow-sm" role="group">
                        <button type="button"
                                @click="recipientType = 'client'"
                                :class="recipientType === 'client'
                                    ? 'bg-indigo-600 text-white border-indigo-600'
                                    : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'"
                                class="px-4 py-2 text-sm font-medium border rounded-l-md transition focus:outline-none">
                            Client
                        </button>
                        <button type="button"
                                @click="recipientType = 'customer'"
                                :class="recipientType === 'customer'
                                    ? 'bg-indigo-600 text-white border-indigo-600'
                                    : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'"
                                class="px-4 py-2 text-sm font-medium border-t border-b border-r rounded-r-md transition focus:outline-none">
                            Customer
                        </button>
                    </div>
                    <p class="text-xs text-gray-400 mt-2" x-show="recipientType === 'client'">
                        Sends via Stripe or PDF depending on the client's invoice type.
                    </p>
                    <p class="text-xs text-gray-400 mt-2" x-show="recipientType === 'customer'">
                        Sends via Stripe. Optionally opens a draft on a HelpScout ticket.
                    </p>
                </div>

                <div class="px-4 py-5">
                    <form method="POST" action="{{ route('invoicing.store') }}" class="space-y-5"
                          onsubmit="return confirm('Generate and send this invoice now?')">
                        @csrf
                        <input type="hidden" name="recipient_type" :value="recipientType">

                        {{-- ---- Client fields ---- --}}
                        <div x-show="recipientType === 'client'" x-cloak>
                            @if($clients->isEmpty())
                                <p class="text-sm text-gray-400">
                                    No clients yet. <a href="{{ route('clients.create') }}" class="text-indigo-600 hover:underline">Create a client first.</a>
                                </p>
                            @else
                                <div>
                                    <x-input-label for="client_id" value="Client" />
                                    <select id="client_id" name="client_id"
                                        x-model="clientId"
                                        x-bind:required="recipientType === 'client'"
                                        class="mt-1 block w-full text-sm rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                        <option value="">— Select client —</option>
                                        @foreach($clients as $client)
                                            <option value="{{ $client->id }}">
                                                {{ $client->name }} ({{ $client->code }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="mt-1.5 min-h-[1.25rem]">
                                        <template x-if="selectedClient">
                                            <p class="text-xs font-medium"
                                               :class="selectedClient.invoice_type === 'stripe' ? 'text-indigo-600' : 'text-gray-500'">
                                                <span x-text="selectedClient.invoice_type === 'stripe' ? '→ Stripe invoice' : '→ PDF invoice'"></span>
                                            </p>
                                        </template>
                                    </div>
                                    <x-input-error :messages="$errors->get('client_id')" class="mt-1" />
                                </div>
                            @endif
                        </div>

                        {{-- ---- Customer fields ---- --}}
                        <div x-show="recipientType === 'customer'" x-cloak class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="customer_first_name" value="First Name" />
                                    <x-text-input id="customer_first_name" name="customer_first_name" type="text"
                                        class="mt-1 block w-full"
                                        value="{{ old('customer_first_name') }}"
                                        x-bind:required="recipientType === 'customer'" />
                                    <x-input-error :messages="$errors->get('customer_first_name')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label for="customer_last_name" value="Last Name" />
                                    <x-text-input id="customer_last_name" name="customer_last_name" type="text"
                                        class="mt-1 block w-full"
                                        value="{{ old('customer_last_name') }}"
                                        x-bind:required="recipientType === 'customer'" />
                                    <x-input-error :messages="$errors->get('customer_last_name')" class="mt-1" />
                                </div>
                            </div>
                            <div>
                                <x-input-label for="customer_email" value="Email" />
                                <x-text-input id="customer_email" name="customer_email" type="email"
                                    class="mt-1 block w-full"
                                    placeholder="customer@example.com"
                                    value="{{ old('customer_email') }}"
                                    x-bind:required="recipientType === 'customer'" />
                                <x-input-error :messages="$errors->get('customer_email')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label for="helpscout_ticket" value="HelpScout Ticket # (optional)" />
                                <x-text-input id="helpscout_ticket" name="helpscout_ticket" type="text"
                                    class="mt-1 block w-full"
                                    placeholder="e.g. 12345"
                                    value="{{ old('helpscout_ticket') }}" />
                                <p class="mt-1 text-xs text-gray-400">If provided, a draft reply will be opened on that ticket.</p>
                                <x-input-error :messages="$errors->get('helpscout_ticket')" class="mt-1" />
                            </div>
                        </div>

                        {{-- ---- Line items ---- --}}
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
                                                class="mt-1 text-gray-300 hover:text-red-400 transition text-lg leading-none shrink-0"
                                                title="Remove line item">&times;</button>
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
                                <span x-show="items.length >= 8" class="text-xs text-gray-400">Maximum 8 line items</span>

                                <div class="text-sm font-semibold text-gray-700 ml-auto">
                                    Total: <span class="text-green-700" x-text="'$' + total.toFixed(2)"></span>
                                </div>
                            </div>

                            @if($errors->has('items') || $errors->has('items.*.description') || $errors->has('items.*.amount'))
                                <p class="mt-1 text-xs text-red-600">{{ $errors->first('items') ?: $errors->first('items.*.description') ?: $errors->first('items.*.amount') }}</p>
                            @endif
                        </div>

                        {{-- ---- Shared fields ---- --}}
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="due_date" value="Due Date (optional)" />
                                <x-text-input id="due_date" name="due_date" type="date"
                                    class="mt-1 block w-full"
                                    value="{{ old('due_date') }}" />
                                <x-input-error :messages="$errors->get('due_date')" class="mt-1" />
                            </div>
                        </div>

                        <div x-show="recipientType === 'client'" x-cloak>
                            <x-input-label for="notes" value="Notes (internal, optional)" />
                            <textarea id="notes" name="notes" rows="2"
                                class="mt-1 block w-full text-sm rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                            >{{ old('notes') }}</textarea>
                        </div>

                        <div class="pt-2">
                            <x-primary-button>Generate &amp; Send Invoice</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
