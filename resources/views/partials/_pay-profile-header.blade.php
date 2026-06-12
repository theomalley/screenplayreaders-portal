{{-- v1.1 — 2026-06-11 | Payment ID date reflects the last day of the pay period, not today --}}
{{-- v1.0 — 2026-06-11 | Read-only profile header for "My Earnings" — photo, name, PayPal payment ID --}}
@php
    $borderClass = ($color ?? 'amber') === 'blue' ? 'border-blue-200 bg-blue-50' : 'border-amber-200 bg-amber-50';
    $paymentId = strtoupper($initials) . $periodEnd->format('Ymd');
@endphp
<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
    <div class="flex flex-wrap items-center gap-3 px-5 py-4 border-b {{ $borderClass }}">
        @if($photoUrl)
            <img src="{{ $photoUrl }}" alt="{{ $initials }}"
                 class="w-9 h-9 rounded-full object-cover ring-1 ring-gray-300">
        @else
            <div class="w-9 h-9 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-semibold ring-1 ring-gray-300">{{ $initials }}</div>
        @endif
        <span class="font-semibold text-gray-800">{{ $name }}</span>
        <span x-data="{ copied: false }"
              class="inline-flex items-center gap-1 cursor-pointer select-all"
              @click="navigator.clipboard.writeText('{{ $paymentId }}'); copied = true; setTimeout(() => copied = false, 1500)"
              title="Copy payment ID for PayPal note">
            <span class="font-mono text-xs px-1.5 py-0.5 rounded bg-gray-100 border border-gray-300 text-gray-600 hover:border-indigo-400 hover:text-indigo-700 transition-colors">{{ $paymentId }}</span>
            <span x-show="!copied" class="text-[10px] text-gray-400">copy</span>
            <span x-show="copied" x-cloak class="text-[10px] text-green-600 font-medium">✓ copied</span>
        </span>
        @if($paypalEmail)
            <span class="text-sm text-gray-500">· PayPal: <span class="font-mono text-xs">{{ $paypalEmail }}</span></span>
        @endif
    </div>
</div>
