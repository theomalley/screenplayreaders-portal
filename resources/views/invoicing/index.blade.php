<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Invoicing</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="px-4 py-3 rounded bg-green-50 border border-green-200 text-green-800 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if($errors->has('invoice'))
                <div class="px-4 py-3 rounded bg-red-50 border border-red-200 text-red-800 text-sm">
                    {{ $errors->first('invoice') }}
                </div>
            @endif

            @if($clients->isEmpty())
                <div class="bg-white shadow-sm sm:rounded-lg px-6 py-10 text-center text-sm text-gray-400">
                    No clients yet. <a href="{{ route('clients.create') }}" class="text-indigo-600 hover:underline">Create a client first.</a>
                </div>
            @else
                <div class="bg-white shadow-sm sm:rounded-lg">
                    <div class="px-4 py-3 border-b border-gray-100">
                        <h3 class="text-sm font-semibold text-gray-700">Create Invoice</h3>
                        <p class="text-xs text-gray-400 mt-0.5">Select a client and enter the invoice details. The invoice will be generated and sent immediately.</p>
                    </div>
                    <div class="px-4 py-5">
                        <form method="POST" action="{{ route('invoicing.store') }}" class="space-y-4"
                              onsubmit="return confirm('Generate and send this invoice now?')">
                            @csrf

                            <div>
                                <x-input-label for="client_id" value="Client" />
                                <select id="client_id" name="client_id" required
                                    class="mt-1 block w-full text-sm rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="">— Select client —</option>
                                    @foreach($clients as $client)
                                        <option value="{{ $client->id }}"
                                            {{ (old('client_id', request('client')) == $client->id) ? 'selected' : '' }}>
                                            {{ $client->name }} ({{ $client->code }})
                                            — {{ $client->invoice_type === 'stripe' ? 'Stripe' : 'PDF' }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('client_id')" class="mt-1" />
                            </div>

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

                            <div>
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
            @endif

        </div>
    </div>
</x-app-layout>
