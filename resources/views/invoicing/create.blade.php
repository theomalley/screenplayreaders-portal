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
                    $c->id => ['invoice_type' => $c->invoice_type, 'batch' => (bool) $c->batch_invoicing]
                ])->toJson();
            @endphp
            <div class="bg-white shadow-sm sm:rounded-lg"
                 x-data="{
                     recipientType: '{{ old('recipient_type', 'client') }}',
                     clientId: '{{ old('client_id', request('client')) }}',
                     clientMeta: {{ $clientMeta }},
                     get selectedClient() { return this.clientId ? this.clientMeta[this.clientId] : null; }
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
                    <p class="text-xs text-gray-400 mt-2"
                       x-show="recipientType === 'client'">
                        Sends via Stripe or PDF depending on the client's invoice type.
                    </p>
                    <p class="text-xs text-gray-400 mt-2"
                       x-show="recipientType === 'customer'">
                        Sends via Stripe. Optionally opens a draft on a HelpScout ticket.
                    </p>
                </div>

                <div class="px-4 py-5">
                    <form method="POST" action="{{ route('invoicing.store') }}" class="space-y-4"
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

                                    {{-- Dynamic invoice type hint --}}
                                    <div class="mt-1.5 min-h-[1.25rem]">
                                        <template x-if="selectedClient">
                                            <p class="text-xs font-medium"
                                               :class="selectedClient.invoice_type === 'stripe' ? 'text-indigo-600' : 'text-gray-500'">
                                                <template x-if="selectedClient.invoice_type === 'stripe'">
                                                    <span>&#x2192; Stripe invoice<span x-show="selectedClient.batch"> (batched)</span></span>
                                                </template>
                                                <template x-if="selectedClient.invoice_type !== 'stripe'">
                                                    <span>&#x2192; PDF invoice</span>
                                                </template>
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
                                <p class="mt-1 text-xs text-gray-400">If provided, a draft reply will be opened on that ticket with a saved-reply placeholder.</p>
                                <x-input-error :messages="$errors->get('helpscout_ticket')" class="mt-1" />
                            </div>
                        </div>

                        {{-- ---- Shared fields ---- --}}
                        <div>
                            <x-input-label for="description" value="Description / Line Item" />
                            <x-text-input id="description" name="description" type="text"
                                class="mt-1 block w-full"
                                placeholder="e.g. Script Coverage — The Dark Knight"
                                value="{{ old('description') }}" required />
                            <x-input-error :messages="$errors->get('description')" class="mt-1" />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="amount" value="Amount ($)" />
                                <div class="mt-1 relative">
                                    <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 text-sm">$</span>
                                    <x-text-input id="amount" name="amount" type="number" step="0.01" min="0.01"
                                        class="block w-full pl-7"
                                        value="{{ old('amount') }}" required />
                                </div>
                                <x-input-error :messages="$errors->get('amount')" class="mt-1" />
                            </div>
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
