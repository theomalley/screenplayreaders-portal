{{-- Retail Price cell for a Ratebook row (what customers are charged) — admin-editable,
     read-only for editors. Expects $key; reads $retailPrices and $canEdit from the
     including view's shared scope. Hidden from readers entirely (see index.blade.php). --}}
@if ($canEdit)
    <div class="flex items-center justify-end gap-1">
        <span class="text-gray-400 text-sm">$</span>
        <input type="number" name="retail_price_{{ $key }}" value="{{ old('retail_price_' . $key, $retailPrices[$key] ?? '') }}"
            min="0" max="9999.99" step="0.01" placeholder="0.00"
            class="w-24 text-right border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
    </div>
    <x-input-error :messages="$errors->get('retail_price_' . $key)" class="mt-1 text-right" />
@else
    @php $price = $retailPrices[$key] ?? null; @endphp
    <span class="font-mono text-gray-800">{{ $price !== null ? '$' . number_format($price, 2) : '—' }}</span>
@endif
