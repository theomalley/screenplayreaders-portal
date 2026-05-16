@props(['name', 'show' => false, 'maxWidth' => '2xl', 'focusable' => false])

@php
$maxWidth = match ($maxWidth) {
    'sm'  => 'sm:max-w-sm',
    'md'  => 'sm:max-w-md',
    'lg'  => 'sm:max-w-lg',
    'xl'  => 'sm:max-w-xl',
    '2xl' => 'sm:max-w-2xl',
    default => 'sm:max-w-2xl',
};
@endphp

<div
    x-data="{
        show: @js($show),
        focusables() {
            let query = '[tabindex], a, button, input, textarea, select, details, [contenteditable=\'true\']';
            return [...$el.querySelectorAll(query)].filter(el => ! el.closest('[x-show]')?.style?.display !== 'none');
        },
        firstFocusable() { return this.focusables().at(0); },
        lastFocusable()  { return this.focusables().at(-1); },
        nextFocusable()  { return this.focusables().indexOf(document.activeElement) === this.focusables().length - 1 ? this.firstFocusable() : this.focusables()[this.focusables().indexOf(document.activeElement) + 1]; },
        prevFocusable()  { return this.focusables().indexOf(document.activeElement) === 0 ? this.lastFocusable() : this.focusables()[this.focusables().indexOf(document.activeElement) - 1]; },
        handleTab(e)  { e.preventDefault(); this.nextFocusable().focus(); },
        handleShiftTab(e) { e.preventDefault(); this.prevFocusable().focus(); },
    }"
    x-on:open-modal.window="$event.detail == '{{ $name }}' ? show = true : null"
    x-on:close.window="show = false"
    x-on:keydown.escape.window="show = false"
    x-show="show"
    class="fixed inset-0 overflow-y-auto px-4 py-6 sm:px-0 z-50"
    style="display: none;"
>
    <div x-show="show" class="fixed inset-0 transform transition-all" x-on:click="show = false" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
    </div>

    <div x-show="show" class="mb-6 bg-white rounded-lg overflow-hidden shadow-xl transform transition-all sm:w-full {{ $maxWidth }} sm:mx-auto" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
        {{ $slot }}
    </div>
</div>
