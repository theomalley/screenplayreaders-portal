{{-- Shown in assignment line items when the order has blocked readers. Expects: $assignment --}}
@php $blockedInitials = $assignment->blockedReaderInitials(); @endphp
@if (!empty($blockedInitials))
    <div class="flex items-center gap-1 mt-1" title="These readers are blocked from this order">
        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-mono font-semibold bg-red-100 text-red-700">
            🚫 Blocked: {{ implode(', ', $blockedInitials) }}
        </span>
    </div>
@endif
