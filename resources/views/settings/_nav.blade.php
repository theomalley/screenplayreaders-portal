@php
    $tabs = [
        'settings.index'       => 'General',
        'settings.assignments' => 'Assignments & Coverage',
        'settings.tiers'       => 'Tiers',
        'settings.emails'      => 'Emails & Notifications',
        'settings.orders'      => 'Orders & Payments',
    ];
@endphp
<div class="border-b border-gray-200 mb-6">
    <nav class="-mb-px flex gap-6 overflow-x-auto" aria-label="Settings tabs">
        @foreach ($tabs as $route => $label)
            @php $active = request()->routeIs($route); @endphp
            <a href="{{ route($route) }}"
               class="whitespace-nowrap pb-3 px-1 text-sm font-medium border-b-2 transition-colors
                      {{ $active
                          ? 'border-indigo-500 text-indigo-600'
                          : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                {{ $label }}
            </a>
        @endforeach
    </nav>
</div>
