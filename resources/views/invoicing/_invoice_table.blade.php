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
                    <a href="{{ route('clients.show', $invoice->client) }}" class="hover:text-indigo-600 hover:underline">
                        {{ $invoice->client->name }}
                    </a>
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
                <td class="px-4 py-2 text-right text-xs space-x-2 whitespace-nowrap">
                    @if(! $showPaid && $invoice->status !== 'void')
                        @if($invoice->status === 'draft')
                            <form method="POST" action="{{ route('invoices.send', $invoice) }}" class="inline"
                                  onsubmit="return confirm('Send invoice #{{ $invoice->invoice_number }} to {{ $invoice->client->name }} now?')">
                                @csrf
                                <button type="submit" class="text-indigo-600 hover:underline">Send</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('invoices.mark-paid', $invoice) }}" class="inline">
                            @csrf
                            <button type="submit" class="text-green-600 hover:underline">Mark Paid</button>
                        </form>
                        <form method="POST" action="{{ route('invoices.void', $invoice) }}" class="inline"
                              onsubmit="return confirm('Void this invoice?')">
                            @csrf
                            <button type="submit" class="text-red-500 hover:underline">Void</button>
                        </form>
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
