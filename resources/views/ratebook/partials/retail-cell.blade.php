{{-- Retail Price cell for a Ratebook row. Expects $key; reads $retailModes, $retailPrices,
     $retailManual, $canEdit from the including view's shared scope. --}}
@php
    $mode = $retailModes[$key] ?? null;
@endphp
@if ($mode === 'multi')
    @php $tiers = $retailPrices[$key] ?? []; @endphp
    <span class="font-mono text-gray-800 text-xs whitespace-nowrap">
        1R {{ isset($tiers['1']) && $tiers['1'] !== null ? '$' . number_format($tiers['1'], 2) : '—' }}
        &middot;
        2R {{ isset($tiers['2']) && $tiers['2'] !== null ? '$' . number_format($tiers['2'], 2) : '—' }}
        &middot;
        3R {{ isset($tiers['3']) && $tiers['3'] !== null ? '$' . number_format($tiers['3'], 2) : '—' }}
    </span>
@elseif ($mode === 'single')
    @php $price = $retailPrices[$key]['single'] ?? null; @endphp
    <span class="font-mono text-gray-800">{{ $price !== null ? '$' . number_format($price, 2) : '—' }}</span>
@elseif ($mode === 'manual')
    @if ($canEdit)
        <div class="flex items-center justify-end gap-1">
            <span class="text-gray-400 text-sm">$</span>
            <input type="number" name="retail_manual_{{ $key }}" value="{{ old('retail_manual_' . $key, $retailManual[$key] ?? '') }}"
                min="0" max="9999.99" step="0.01" placeholder="0.00"
                class="w-24 text-right border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
        </div>
        <x-input-error :messages="$errors->get('retail_manual_' . $key)" class="mt-1 text-right" />
    @else
        @php $price = $retailManual[$key] ?? null; @endphp
        <span class="font-mono text-gray-800">{{ $price !== null ? '$' . number_format($price, 2) : '—' }}</span>
    @endif
@else
    <span class="text-gray-300">—</span>
@endif
