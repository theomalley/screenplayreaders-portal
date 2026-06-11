{{-- Reader Pay card — unpaid coverage/adjustments for one reader --}}
@php $readerId = $rd['reader_id']; @endphp
<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden" x-data="{ adjOpen: false }">

    {{-- Reader header --}}
    <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-b border-amber-200 bg-amber-50">
        <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-3">
            <div class="flex items-center gap-2">
                <a href="{{ route('readers.edit', $rd['reader_id']) }}" title="Edit {{ $rd['reader_name'] }}">
                    @if($rd['photo_url'])
                        <img src="{{ $rd['photo_url'] }}" alt="{{ $rd['initials'] }}"
                             class="w-7 h-7 rounded-full object-cover ring-1 ring-gray-300 hover:ring-indigo-400 transition-shadow">
                    @else
                        <div class="w-7 h-7 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-[10px] font-semibold ring-1 ring-gray-300 hover:ring-indigo-400 transition-shadow">{{ $rd['initials'] }}</div>
                    @endif
                </a>
                <span class="font-semibold text-gray-800">{{ $rd['reader_name'] }}</span>
                @php $paymentId = strtoupper($rd['initials']) . now()->format('Ymd'); @endphp
                <span x-data="{ copied: false }"
                      class="inline-flex items-center gap-1 cursor-pointer select-all"
                      @click="navigator.clipboard.writeText('{{ $paymentId }}'); copied = true; setTimeout(() => copied = false, 1500)"
                      title="Copy payment ID for PayPal note">
                    <span class="font-mono text-xs px-1.5 py-0.5 rounded bg-gray-100 border border-gray-300 text-gray-600 hover:border-indigo-400 hover:text-indigo-700 transition-colors">{{ $paymentId }}</span>
                    <span x-show="!copied" class="text-[10px] text-gray-400">copy</span>
                    <span x-show="copied" x-cloak class="text-[10px] text-green-600 font-medium">✓ copied</span>
                </span>
            </div>
            @if($rd['paypal_email'])
                <span class="text-sm text-gray-500">· PayPal: <span class="font-mono text-xs">{{ $rd['paypal_email'] }}</span></span>
            @endif
            <span class="text-sm font-semibold {{ $rd['total_owed'] >= 0 ? 'text-amber-700' : 'text-red-600' }}">
                · {{ $rd['assignments']->count() + $rd['adjustments']->count() }} item(s)
                &nbsp;·&nbsp; ${{ number_format($rd['total_owed'], 2) }} owed
            </span>
        </div>
        <div class="flex items-center gap-2">
            @if(auth()->user()->isAdmin())
            <form method="POST" action="{{ route('reader-pay.clear-unpaid', $readerId) }}"
                onsubmit="return confirm('Remove all test assignments and pending adjustments for {{ $rd['reader_name'] }}?')">
                @csrf
                <button type="submit"
                    class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-red-600 bg-white border border-red-300 hover:bg-red-50 rounded-md transition-colors">
                    Remove
                </button>
            </form>
            @endif
            <button type="button" @click="adjOpen = !adjOpen"
                class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-indigo-700 bg-white border border-indigo-300 hover:bg-indigo-50 rounded-md transition-colors">
                + Adjustment
            </button>
            <form method="POST" action="{{ route('reader-pay.mark-paid', $readerId) }}"
                onsubmit="return confirm('Mark all items for {{ $rd['reader_name'] }} as paid (${{ number_format($rd['total_owed'], 2) }})?')">
                @csrf
                <button type="submit"
                    class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-md transition-colors">
                    Mark All Paid
                </button>
            </form>
        </div>
    </div>

    {{-- Adjustment form (collapsed by default) --}}
    <div x-show="adjOpen" x-cloak class="px-5 py-4 bg-indigo-50 border-b border-indigo-100">
        <form method="POST" action="{{ route('reader-pay.add-adjustment', $readerId) }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Amount (negative to deduct)</label>
                <input type="number" name="amount" step="0.01" placeholder="e.g. -15.00"
                    class="w-32 text-sm border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                    required>
            </div>
            <div class="flex-1 min-w-48">
                <label class="block text-xs font-medium text-gray-600 mb-1">Description</label>
                <input type="text" name="description" placeholder="e.g. Overpay correction"
                    class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                    required maxlength="255">
            </div>
            <button type="submit"
                class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-md transition-colors">
                Add
            </button>
            <button type="button" @click="adjOpen = false"
                class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 rounded-md transition-colors">
                Cancel
            </button>
        </form>
    </div>

    {{-- Line items table --}}
    <div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-100 text-sm">
        <thead class="bg-gray-50 text-xs font-medium text-gray-500 uppercase tracking-wide">
            <tr>
                <th class="px-4 py-2 text-left">Type</th>
                <th class="px-4 py-2 text-left">Detail</th>
                <th class="px-4 py-2 text-left">Date</th>
                <th class="px-4 py-2 text-right">Amount</th>
                <th class="px-4 py-2"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @foreach($rd['assignments'] as $a)
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-2 text-gray-500 text-xs uppercase">Coverage</td>
                <td class="px-4 py-2">
                    <div class="text-gray-800">{{ $a->script_title }}</div>
                    <div class="text-xs text-gray-400 font-mono">{{ $a->order_number }}</div>
                </td>
                <td class="px-4 py-2 text-gray-500 text-xs">{{ $a->completed_at?->format('M j, Y') }}</td>
                <td class="px-4 py-2 text-right font-medium text-gray-700">${{ number_format($a->pay_rate, 2) }}</td>
                <td class="px-4 py-2"></td>
            </tr>
            @endforeach
            @foreach($rd['adjustments'] as $adj)
            <tr class="hover:bg-indigo-50">
                <td class="px-4 py-2 text-indigo-600 text-xs uppercase font-medium">Adjustment</td>
                <td class="px-4 py-2">
                    <div class="text-gray-700">{{ $adj->description }}</div>
                    <div class="text-xs text-gray-400">by {{ $adj->addedBy?->name }}</div>
                </td>
                <td class="px-4 py-2 text-gray-500 text-xs">{{ $adj->created_at->format('M j, Y') }}</td>
                <td class="px-4 py-2 text-right font-medium {{ (float)$adj->amount >= 0 ? 'text-green-700' : 'text-red-600' }}">
                    {{ (float)$adj->amount >= 0 ? '+' : '' }}${{ number_format($adj->amount, 2) }}
                </td>
                <td class="px-4 py-2 text-right">
                    <form method="POST" action="{{ route('reader-pay.delete-adjustment', $adj->id) }}"
                        onsubmit="return confirm('Remove this adjustment?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-xs text-red-400 hover:text-red-600">Remove</button>
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    </div>{{-- /overflow-x-auto --}}
</div>
