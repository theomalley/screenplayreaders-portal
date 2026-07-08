@php
$statusColors = [
    'draft' => 'bg-gray-100 text-gray-600',
    'sent'  => 'bg-blue-100 text-blue-700',
    'paid'  => 'bg-green-100 text-green-700',
    'void'  => 'bg-red-100 text-red-600',
];
@endphp
<table class="min-w-full text-sm divide-y divide-gray-100">
    <thead class="bg-gray-50">
        <tr>
            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Client</th>
            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Invoice #</th>
            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Description</th>
            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Issued</th>
            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Due</th>
            <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Amount</th>
            <th class="px-4 py-2 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
            <th class="px-4 py-2"></th>
        </tr>
    </thead>
    <tbody class="divide-y divide-gray-50">
        @foreach($invoices as $invoice)
            <tr>
                <td class="px-4 py-2 text-gray-700 text-xs">
                    @if($invoice->client_id)
                        <a href="{{ route('clients.show', $invoice->client) }}" class="hover:text-indigo-600 hover:underline">
                            {{ $invoice->client->name }}
                        </a>
                    @else
                        <span>{{ $invoice->customer_name }}</span>
                        <div class="text-gray-400">{{ $invoice->customer_email }}</div>
                    @endif
                </td>
                <td class="px-4 py-2 font-mono text-gray-800 text-xs">
                    {{ $invoice->invoice_number }}
                    @if($invoice->invoice_type === 'stripe' && $invoice->stripe_invoice_url)
                        <a href="{{ $invoice->stripe_invoice_url }}" target="_blank"
                           class="ml-1 text-purple-500 hover:underline text-xs">↗</a>
                    @endif
                </td>
                <td class="px-4 py-2 text-gray-700 max-w-xs truncate">{{ $invoice->description }}</td>
                <td class="px-4 py-2 text-gray-500 text-xs whitespace-nowrap">
                    {{ $invoice->issued_at ? $invoice->issued_at->format('M j, Y') : '—' }}
                </td>
                <td class="px-4 py-2 text-gray-500 text-xs whitespace-nowrap">
                    {{ $invoice->due_date ? $invoice->due_date->format('M j, Y') : '—' }}
                </td>
                <td class="px-4 py-2 text-right font-mono text-gray-800">
                    ${{ number_format((float) $invoice->amount, 2) }}
                </td>
                <td class="px-4 py-2 text-center">
                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $statusColors[$invoice->status] ?? 'bg-gray-100 text-gray-600' }}">
                        {{ ucfirst($invoice->status) }}
                    </span>
                </td>
                <td class="px-4 py-2 text-right text-xs whitespace-nowrap">
                    <div class="flex items-center justify-end gap-3">
                    @if($invoice->status === 'paid')
                        @if($invoice->invoice_type === 'pdf' && $invoice->google_doc_id)
                            <div x-data="{ open: false, top: 0, left: 0 }">
                                <button type="button" x-ref="pdfBtn"
                                        @click="const r = $refs.pdfBtn.getBoundingClientRect(); top = r.bottom + 4; left = r.right - 144; open = !open"
                                        class="text-indigo-600 hover:underline flex items-center gap-0.5">
                                    PDF <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                {{-- Teleported to <body> so the menu isn't clipped by the table's overflow-x-auto wrapper --}}
                                <template x-teleport="body">
                                    <div x-show="open" x-cloak
                                         @click.outside="if (!$refs.pdfBtn.contains($event.target)) open = false"
                                         :style="`position: fixed; top: ${top}px; left: ${left}px;`"
                                         class="w-36 bg-white border border-gray-200 rounded shadow-lg z-50 py-1 text-left">
                                        <a href="{{ route('invoices.edit', $invoice) }}"
                                           class="block px-3 py-1.5 text-indigo-600 hover:bg-gray-50">Edit</a>
                                        <a href="{{ route('invoices.pdf', $invoice) }}?view=1" target="_blank"
                                           class="block px-3 py-1.5 text-gray-600 hover:bg-gray-50">View PDF</a>
                                        <a href="{{ route('invoices.pdf', $invoice) }}"
                                           class="block px-3 py-1.5 text-gray-600 hover:bg-gray-50">Download</a>
                                        <form method="POST" action="{{ route('invoices.resend', $invoice) }}"
                                              onsubmit="return confirm('Resend to {{ $invoice->client?->email }}?')">
                                            @csrf
                                            <button type="submit" class="w-full text-left px-3 py-1.5 text-amber-600 hover:bg-gray-50">Resend</button>
                                        </form>
                                    </div>
                                </template>
                            </div>
                        @endif
                        <form method="POST" action="{{ route('invoices.destroy', $invoice) }}"
                              onsubmit="return confirm('Permanently delete invoice #{{ $invoice->invoice_number }} and remove it from the order log?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-500 hover:underline">Delete</button>
                        </form>
                    @elseif($invoice->status === 'draft')
                        <a href="{{ route('invoices.edit', $invoice) }}" class="text-gray-600 hover:underline">Edit</a>
                        <form method="POST" action="{{ route('invoices.send', $invoice) }}"
                              onsubmit="return confirm('Send invoice #{{ $invoice->invoice_number }} to {{ $invoice->client?->name }} now?')">
                            @csrf
                            <button type="submit" class="text-indigo-600 hover:underline">Send</button>
                        </form>
                        <form method="POST" action="{{ route('invoices.void', $invoice) }}"
                              onsubmit="return confirm('Void this invoice?')">
                            @csrf
                            <button type="submit" class="text-red-500 hover:underline">Void</button>
                        </form>
                    @elseif($invoice->status !== 'void')
                        @if($invoice->invoice_type === 'pdf' && $invoice->google_doc_id)
                            {{-- PDF actions in a small dropdown --}}
                            <div x-data="{ open: false, top: 0, left: 0 }">
                                <button type="button" x-ref="pdfBtn"
                                        @click="const r = $refs.pdfBtn.getBoundingClientRect(); top = r.bottom + 4; left = r.right - 144; open = !open"
                                        class="text-indigo-600 hover:underline flex items-center gap-0.5">
                                    PDF <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                {{-- Teleported to <body> so the menu isn't clipped by the table's overflow-x-auto wrapper --}}
                                <template x-teleport="body">
                                    <div x-show="open" x-cloak
                                         @click.outside="if (!$refs.pdfBtn.contains($event.target)) open = false"
                                         :style="`position: fixed; top: ${top}px; left: ${left}px;`"
                                         class="w-36 bg-white border border-gray-200 rounded shadow-lg z-50 py-1 text-left">
                                        <a href="{{ route('invoices.edit', $invoice) }}"
                                           class="block px-3 py-1.5 text-indigo-600 hover:bg-gray-50">Edit</a>
                                        <a href="{{ route('invoices.pdf', $invoice) }}?view=1" target="_blank"
                                           class="block px-3 py-1.5 text-gray-600 hover:bg-gray-50">View PDF</a>
                                        <a href="{{ route('invoices.pdf', $invoice) }}"
                                           class="block px-3 py-1.5 text-gray-600 hover:bg-gray-50">Download</a>
                                        <form method="POST" action="{{ route('invoices.resend', $invoice) }}"
                                              onsubmit="return confirm('Resend to {{ $invoice->client?->email }}?')">
                                            @csrf
                                            <button type="submit" class="w-full text-left px-3 py-1.5 text-amber-600 hover:bg-gray-50">Resend</button>
                                        </form>
                                    </div>
                                </template>
                            </div>
                        @endif
                        <form method="POST" action="{{ route('invoices.mark-paid', $invoice) }}">
                            @csrf
                            <button type="submit" class="text-green-600 hover:underline">Mark Paid</button>
                        </form>
                        <form method="POST" action="{{ route('invoices.void', $invoice) }}"
                              onsubmit="return confirm('Void this invoice?')">
                            @csrf
                            <button type="submit" class="text-red-500 hover:underline">Void</button>
                        </form>
                    @endif
                    </div>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
