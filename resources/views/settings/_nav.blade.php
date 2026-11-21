@php
    $tabs = [
        ['route' => 'settings.index',              'label' => 'General',                'active' => ['settings.index']],
        ['route' => 'settings.appearance',         'label' => 'Appearance',              'active' => ['settings.appearance']],
        ['route' => 'settings.services.coverage',  'label' => 'Services',                'active' => ['settings.services.*', 'budget-admin.*', 'script-registrations.test*', 'coverage-attestations.*']],
        ['route' => 'settings.team',               'label' => 'Team',                    'active' => ['settings.team', 'tiers.*']],
        ['route' => 'settings.emails',              'label' => 'Emails & Notifications', 'active' => ['settings.emails']],
        ['route' => 'settings.orders',              'label' => 'Orders & Payments',      'active' => ['settings.orders']],
    ];
@endphp
<div class="border-b border-gray-200 mb-6">
    <nav class="-mb-px flex gap-6 overflow-x-auto" aria-label="Settings tabs">
        @foreach ($tabs as $tab)
            @php $active = collect($tab['active'])->contains(fn($pattern) => request()->routeIs($pattern)); @endphp
            <a href="{{ route($tab['route']) }}"
               class="whitespace-nowrap pb-3 px-1 text-sm font-medium border-b-2 transition-colors
                      {{ $active
                          ? 'border-indigo-500 text-indigo-600'
                          : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                {{ $tab['label'] }}
            </a>
        @endforeach
    </nav>
</div>
