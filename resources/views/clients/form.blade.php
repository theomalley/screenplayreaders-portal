<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-4">
            <a href="{{ $client ? route('clients.show', $client) : route('clients.index') }}"
               class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $client ? 'Edit Client — ' . $client->name : 'New Client' }}
            </h2>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            @if($errors->any())
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-sm text-red-700">
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form id="client-form"
                  method="POST"
                  action="{{ $client ? route('clients.update', $client) : route('clients.store') }}">
                @csrf
                @if($client) @method('PATCH') @endif

                {{-- IDENTITY --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Identity</p>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="name" value="Client Company / Name" />
                            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                                value="{{ old('name', $client?->name) }}" required />
                            <x-input-error :messages="$errors->get('name')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="code" value="Client Code (alphanumeric)" />
                            <x-text-input id="code" name="code" type="text" class="mt-1 block w-full font-mono"
                                placeholder="e.g. ACME01"
                                value="{{ old('code', $client?->code) }}" required />
                            <x-input-error :messages="$errors->get('code')" class="mt-1" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="email" value="Client Email Address" />
                        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full"
                            value="{{ old('email', $client?->email) }}" />
                        <x-input-error :messages="$errors->get('email')" class="mt-1" />
                    </div>
                </div>

                {{-- SR ADDRESS --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4 mt-4">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Screenplay Readers Address</p>
                            <p class="text-xs text-gray-400 mt-0.5">Appears as the sender address on invoices. Pre-filled from the default in Settings.</p>
                        </div>
                        @if($defaultSrAddress)
                            <button type="button"
                                    onclick="document.getElementById('sr_address').value = {{ json_encode($defaultSrAddress) }}"
                                    class="text-xs text-indigo-600 hover:underline whitespace-nowrap ml-4">
                                Reset to default
                            </button>
                        @endif
                    </div>
                    <div>
                        <textarea id="sr_address" name="sr_address" rows="4"
                            class="mt-1 block w-full text-sm rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                            placeholder="{{ $defaultSrAddress ?: 'Enter the SR mailing address for invoices…' }}"
                        >{{ old('sr_address', $client?->sr_address ?? $defaultSrAddress) }}</textarea>
                        <x-input-error :messages="$errors->get('sr_address')" class="mt-1" />
                    </div>
                </div>

                {{-- CLIENT ADDRESS --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4 mt-4">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Client Address</p>

                    <div>
                        <x-input-label for="address_line1" value="Address Line 1" />
                        <x-text-input id="address_line1" name="address_line1" type="text" class="mt-1 block w-full"
                            value="{{ old('address_line1', $client?->address_line1) }}" />
                    </div>
                    <div>
                        <x-input-label for="address_line2" value="Address Line 2" />
                        <x-text-input id="address_line2" name="address_line2" type="text" class="mt-1 block w-full"
                            value="{{ old('address_line2', $client?->address_line2) }}" />
                    </div>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <x-input-label for="city" value="City" />
                            <x-text-input id="city" name="city" type="text" class="mt-1 block w-full"
                                value="{{ old('city', $client?->city) }}" />
                        </div>
                        <div>
                            <x-input-label for="state" value="State / Province" />
                            <x-text-input id="state" name="state" type="text" class="mt-1 block w-full"
                                value="{{ old('state', $client?->state) }}" />
                        </div>
                        <div>
                            <x-input-label for="postcode" value="Postcode / ZIP" />
                            <x-text-input id="postcode" name="postcode" type="text" class="mt-1 block w-full"
                                value="{{ old('postcode', $client?->postcode) }}" />
                        </div>
                    </div>
                    <div>
                        <x-input-label for="country" value="Country" />
                        <x-text-input id="country" name="country" type="text" class="mt-1 block w-full"
                            value="{{ old('country', $client?->country ?? 'USA') }}" />
                    </div>
                </div>

                {{-- INVOICING SETTINGS --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4 mt-4">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Invoicing Settings</p>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="invoice_type" value="Invoice Method" />
                            <select id="invoice_type" name="invoice_type"
                                class="mt-1 block w-full text-sm rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="stripe" {{ old('invoice_type', $client?->invoice_type ?? 'stripe') === 'stripe' ? 'selected' : '' }}>Stripe (email invoice)</option>
                                <option value="pdf"    {{ old('invoice_type', $client?->invoice_type) === 'pdf'    ? 'selected' : '' }}>PDF (Google Docs → Help Scout draft)</option>
                            </select>
                            <x-input-error :messages="$errors->get('invoice_type')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="last_invoice_number" value="Last Invoice Number" />
                            <x-text-input id="last_invoice_number" name="last_invoice_number" type="number" min="0"
                                class="mt-1 block w-full font-mono"
                                value="{{ old('last_invoice_number', $client?->last_invoice_number ?? 0) }}" />
                            <p class="mt-1 text-xs text-gray-400">Next invoice will be this + 1.</p>
                            <x-input-error :messages="$errors->get('last_invoice_number')" class="mt-1" />
                        </div>
                    </div>

                </div>

                {{-- NOTES --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4 mt-4">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Notes</p>
                    <textarea id="notes" name="notes" rows="3"
                        class="block w-full text-sm rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                        placeholder="Internal notes about this client…"
                    >{{ old('notes', $client?->notes) }}</textarea>
                </div>

            </form>

            {{-- ACTIONS --}}
            <div class="flex items-center justify-between mt-4">
                @if($client && auth()->user()?->isAdmin())
                    <form method="POST" action="{{ route('clients.destroy', $client) }}"
                          onsubmit="return confirm('Delete this client? This cannot be undone.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="inline-flex items-center px-3 py-1.5 bg-white border border-red-300 rounded text-xs font-medium text-red-600 hover:bg-red-50 transition">
                            Delete Client
                        </button>
                    </form>
                @else
                    <div></div>
                @endif
                <div class="flex items-center gap-3">
                    <a href="{{ $client ? route('clients.show', $client) : route('clients.index') }}"
                       class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
                    <x-primary-button form="client-form">
                        {{ $client ? 'Save Changes' : 'Create Client' }}
                    </x-primary-button>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
