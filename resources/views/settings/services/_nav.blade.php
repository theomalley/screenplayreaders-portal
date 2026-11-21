@php
    $serviceTabs = [
        ['route' => 'settings.services.coverage',         'label' => 'Coverage',            'active' => ['settings.services.coverage']],
        ['route' => 'settings.services.video-consulting', 'label' => 'Video Consulting',    'active' => ['settings.services.video-consulting']],
        ['route' => 'budget-admin.index',                 'label' => 'Budget',              'active' => ['budget-admin.*']],
        ['route' => 'script-registrations.test',          'label' => 'Script Registration', 'active' => ['script-registrations.test*']],
    ];
@endphp
<div class="flex gap-2 mb-5 flex-wrap">
    @foreach ($serviceTabs as $tab)
        @php $active = collect($tab['active'])->contains(fn($pattern) => request()->routeIs($pattern)); @endphp
        <a href="{{ route($tab['route']) }}"
           class="px-3 py-1.5 rounded-full text-xs font-medium transition-colors
                  {{ $active ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
            {{ $tab['label'] }}
        </a>
    @endforeach
</div>
