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

            {{-- Batch draft invoice panel --}}
            @if($batchDraft)
                <div class="bg-amber-50 border border-amber-200 shadow-sm sm:rounded-lg">
                    <div class="px-4 py-3 border-b border-amber-200 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-semibold text-amber-800">Open Weekly Invoice #{{ $batchDraft->invoice_number }}</h3>
                            <p class="text-xs text-amber-600 mt-0.5">Accumulating — send when ready</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-sm font-semibold text-amber-800">${{ number_format((float) $batchDraft->amount, 2) }} total</span>
                            <form method="POST" action="{{ route('invoices.send', $batchDraft) }}"
                                  onsubmit="return confirm('Send invoice #{{ $batchDraft->invoice_number }} to {{ $client->name }} now?')">
                                @csrf
                                <button type="submit"
                                        class="inline-flex items-center px-3 py-1.5 bg-amber-600 border border-transparent rounded text-xs font-medium text-white hover:bg-amber-700 transition">
                                    Send Invoice
                                </button>
                            </form>
                        </div>
                    </div>

                    @if($batchDraft->lineItems->isEmpty())
                        <div class="px-4 py-4 text-sm text-amber-700 italic">No line items yet.</div>
                    @else
                        <table class="w-full text-sm">
                            <thead class="bg-amber-100">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-amber-700 uppercase tracking-wide">Description</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-amber-700 uppercase tracking-wide">Assignment</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-amber-700 uppercase tracking-wide">Amount</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-amber-100">
                                @foreach($batchDraft->lineItems as $item)
                                    <tr>
                                        <td class="px-4 py-2 text-gray-700">{{ $item->description }}</td>
                                        <td class="px-4 py-2 text-gray-500 text-xs">
                                            @if($item->assignment)
                                                <a href="{{ route('assignments.show', $item->assignment) }}" class="text-indigo-600 hover:underline">
                                                    #{{ $item->assignment->id }}
                                                </a>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-right font-mono text-gray-800">${{ number_format((float) $item->amount, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="border-t border-amber-200 bg-amber-50">
                                <tr>
                                    <td colspan="2" class="px-4 py-2 text-right text-xs font-semibold text-amber-800 uppercase">Total</td>
                                    <td class="px-4 py-2 text-right font-mono font-semibold text-amber-800">${{ number_format((float) $batchDraft->amount, 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    @endif
                </div>
            @endif

            {{-- Outstanding invoices --}}
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-700">Outstanding Invoices</h3>
                    <a href="{{ route('invoicing.index') }}?client={{ $client->id }}"
                       class="text-xs text-indigo-600 hover:underline">+ New Invoice</a>
                </div>
                @if($outstanding->isEmpty())
                    <div class="px-4 py-6 text-center text-sm text-gray-400">No outstanding invoices.</div>
                @else
                    @include('clients._invoice_table', ['invoices' => $outstanding, 'showPaid' => false])
                @endif
            </div>

            {{-- Paid invoices --}}
            @if($paid->isNotEmpty())
                <div class="bg-white shadow-sm sm:rounded-lg">
                    <div class="px-4 py-3 border-b border-gray-100">
                        <h3 class="text-sm font-semibold text-gray-700">Paid Invoices</h3>
                    </div>
                    @include('clients._invoice_table', ['invoices' => $paid, 'showPaid' => true])
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
