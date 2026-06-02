<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <a href="{{ route('clients.index') }}" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $client->name }}</h2>
                <span class="font-mono text-xs text-gray-400">{{ $client->code }}</span>
                @if($client->invoice_type === 'stripe')
                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-700">Stripe</span>
                @else
                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">PDF</span>
                @endif
            </div>
            <a href="{{ route('clients.edit', $client) }}"
               class="text-sm text-indigo-600 hover:underline">Edit Client</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

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

            {{-- Client details --}}
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="px-4 py-3 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-700">Details</h3>
                </div>
                <div class="px-4 py-4 grid grid-cols-3 gap-4 text-sm">
                    <div>
                        <div class="text-xs text-gray-400 uppercase tracking-wide mb-1">Email</div>
                        <div class="text-gray-800">{{ $client->email ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-400 uppercase tracking-wide mb-1">Billing Address</div>
                        <div class="text-gray-800 text-xs leading-relaxed">{{ $client->billingAddress() ?: '—' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-400 uppercase tracking-wide mb-1">Last Invoice #</div>
                        <div class="text-gray-800 font-mono">{{ $client->last_invoice_number }}</div>
                    </div>
                    @if($client->notes)
                        <div class="col-span-3">
                            <div class="text-xs text-gray-400 uppercase tracking-wide mb-1">Notes</div>
                            <div class="text-gray-700 text-xs whitespace-pre-line">{{ $client->notes }}</div>
                        </div>
                    @endif
                </div>
            </div>


            {{-- Invoices link --}}
            <div class="bg-white shadow-sm sm:rounded-lg px-4 py-3 flex items-center justify-between text-sm">
                <span class="text-gray-500">All invoices for this client</span>
                <a href="{{ route('invoicing.index') }}" class="text-indigo-600 hover:underline text-xs">View in Invoicing →</a>
            </div>

        </div>
    </div>
</x-app-layout>
