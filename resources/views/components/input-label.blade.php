@props(['value', 'for' => null])

<label {{ $attributes->merge(['class' => 'block font-medium text-sm text-gray-700']) }} @if($for) for="{{ $for }}" @endif>
    {{ $value ?? $slot }}
</label>
