<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Clients</h2>
            <a href="{{ route('clients.create') }}"
               class="inline-flex items-center px-3 py-1.5 bg-indigo-600 border border-transparent rounded text-xs font-medium text-white hover:bg-indigo-700 transition">
                + New Client
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if(session('success'))
                <div class="px-4 py-3 rounded bg-green-50 border border-green-200 text-green-800 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                <table class="min-w-full text-sm divide-y divide-gray-100">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Client</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Code</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Email</th>
                            <th class="px-4 py-2 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Invoice Type</th>
                            <th class="px-4 py-2 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Last Invoice #</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse($clients as $client)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-medium text-gray-800">
                                    <a href="{{ route('clients.show', $client) }}" class="hover:underline">{{ $client->name }}</a>
                                </td>
                                <td class="px-4 py-3 font-mono text-gray-600 text-xs">{{ $client->code }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $client->email ?? '—' }}</td>
                                <td class="px-4 py-3 text-center">
                                    @if($client->invoice_type === 'stripe')
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-700">Stripe</span>
                                    @else
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">PDF</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center font-mono text-gray-600">{{ $client->last_invoice_number }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('clients.edit', $client) }}" class="text-xs text-indigo-600 hover:underline">Edit</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-400">No clients yet. <a href="{{ route('clients.create') }}" class="text-indigo-600 hover:underline">Create the first one.</a></td>
                            </tr>
                        @endforelse
                    </tbody>
                </table></div>
            </div>

        </div>
    </div>
</x-app-layout>
