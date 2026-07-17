{{-- Retail Price cell for a Ratebook row (what customers are charged) — admin-editable,
     read-only for editors. Expects $key; reads $retailPrices and $canEdit from the
     including view's shared scope. Hidden from readers entirely (see index.blade.php).
     Free text, not a number — e.g. "159" or "97/149/197" for a row priced by reader count. --}}
@if ($canEdit)
    <input type="text" name="retail_price_{{ $key }}" value="{{ old('retail_price_' . $key, $retailPrices[$key] ?? '') }}"
        maxlength="255" placeholder="e.g. 159 or 97/149/197"
        class="w-32 text-right border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
    <x-input-error :messages="$errors->get('retail_price_' . $key)" class="mt-1 text-right" />
@else
    <span class="font-mono text-gray-800">{{ $retailPrices[$key] ?? '—' }}</span>
@endif
