{{--
    Reusable staff icon with hover tooltip and click popup card.
    Props:
      $user       — User model (must have readerProfile or editorProfile loaded)
      $size       — 'sm' (w-7 h-7) | 'md' (w-8 h-8, default) | 'lg' (w-10 h-10)
      $showCount  — bool, show active assignment count badge (default false)
--}}
@props(['user', 'size' => 'md', 'showCount' => false])

@php
    $isReader  = $user->isReader();
    $profile   = $isReader ? $user->readerProfile : $user->editorProfile;
    $initials  = $profile?->initials ?? strtoupper(substr($user->name, 0, 2));
    $photoRaw  = $profile?->photo;
    $photoUrl  = $photoRaw ? asset('storage/' . $photoRaw) : null;
    $online    = $user->isOnline();
    $active    = $user->relationLoaded('assignments') ? $user->assignments->count() : null;
    $max       = $profile?->max_concurrent_assignments ?? 0;
    $bgClass   = $isReader ? 'bg-gray-200 text-gray-700' : 'bg-indigo-100 text-indigo-700';
    $sizeClass = match ($size) {
        'sm'    => 'w-7 h-7 text-xs',
        'lg'    => 'w-10 h-10 text-sm',
        default => 'w-8 h-8 text-xs',
    };
    $titleAttr = ($profile?->displayName() ?? $user->name)
               . ($profile?->title ? ' · ' . $profile->title : '')
               . ($online ? ' · Online' : '')
               . ($active !== null ? ' — ' . $active . ($max ? '/' . $max : '') . ' active' : '');
    $cardUrl   = route('staff.card', $user);
@endphp

<button type="button"
        onclick="srStaffCard.toggle(event, {{ $user->id }}, '{{ addslashes($cardUrl) }}', this)"
        title="{{ $titleAttr }}"
        class="relative inline-flex items-center justify-center {{ $sizeClass }} rounded-full {{ $bgClass }} font-mono font-semibold cursor-pointer focus:outline-none">
    @if ($photoUrl)
        <span class="absolute inset-0 rounded-full overflow-hidden">
            <img src="{{ $photoUrl }}" alt="{{ $initials }}" class="w-full h-full object-cover" />
        </span>
    @else
        {{ $initials }}
    @endif
    @if ($showCount && $active > 0)
        <span class="absolute -top-0.5 -right-0.5 w-4 h-4 rounded-full text-[9px] leading-none flex items-center justify-center font-bold z-10 bg-green-500 text-white">
            {{ $active }}
        </span>
    @endif
    @if ($online)
        <span class="absolute bottom-0 right-0 w-2 h-2 rounded-full bg-green-400 ring-2 ring-white z-10"></span>
    @endif
</button>
