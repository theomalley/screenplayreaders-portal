<table class="min-w-full text-sm">
    <thead class="bg-gray-50 text-xs font-medium text-gray-500 uppercase tracking-wide">
        <tr>
            <th class="px-5 py-2 text-left">Type</th>
            <th class="px-4 py-2 text-left">Detail</th>
            <th class="px-4 py-2 text-left">Completed</th>
            <th class="px-4 py-2 text-right">Amount</th>
        </tr>
    </thead>
    <tbody class="divide-y divide-gray-100">
        @foreach($period['assignments'] as $a)
        <tr class="hover:bg-gray-50">
            <td class="px-5 py-2 text-gray-500 text-xs uppercase">Coverage</td>
            <td class="px-4 py-2 text-gray-800">{{ $a->script_title }}</td>
            <td class="px-4 py-2 text-gray-500 text-xs">{{ $a->completed_at?->format('M j, Y') }}</td>
            <td class="px-4 py-2 text-right font-medium text-gray-700">${{ number_format($a->pay_rate, 2) }}</td>
        </tr>
        @endforeach
        @foreach($period['adjustments'] as $adj)
        <tr class="hover:bg-indigo-50">
            <td class="px-5 py-2 text-indigo-600 text-xs uppercase font-medium">Adjustment</td>
            <td class="px-4 py-2 text-gray-700" colspan="2">{{ $adj->description }}</td>
            <td class="px-4 py-2 text-right font-medium {{ (float)$adj->amount >= 0 ? 'text-green-700' : 'text-red-600' }}">
                {{ (float)$adj->amount >= 0 ? '+' : '' }}${{ number_format($adj->amount, 2) }}
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
