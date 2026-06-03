{{-- Reader-facing staff card — photo, name, role, bio, online only. No assignments, pay, or admin links. --}}
@php
    $isReader = $user->isReader();
    $isAdmin  = $user->isAdmin();
    $initials = $profile?->initials ?? strtoupper(substr($user->name, 0, 2));
    $photoUrl = $profile?->photo ? asset('storage/' . $profile->photo) : null;
    $online   = $user->isOnline();
    $bgClass  = $isReader ? 'bg-gray-200 text-gray-700' : 'bg-indigo-100 text-indigo-700';
    $bio      = $profile?->bio;
@endphp

<div class="flex items-start gap-3">
    <div class="relative w-12 h-12 rounded-full {{ $bgClass }} flex items-center justify-center text-base font-mono font-semibold shrink-0 overflow-hidden">
        @if ($photoUrl)
            <img src="{{ $photoUrl }}" alt="{{ $initials }}" class="absolute inset-0 w-full h-full object-cover" />
        @else
            {{ $initials }}
        @endif
        @if ($online)
            <span class="absolute bottom-0 right-0 w-3 h-3 rounded-full bg-green-400 ring-2 ring-white z-10"></span>
        @endif
    </div>

    <div class="min-w-0 flex-1">
        <div class="flex items-center gap-2 flex-wrap">
            <span class="font-semibold text-gray-900 text-sm">{{ $profile?->displayName() ?? $user->name }}</span>
            @if ($isAdmin)
                <span class="text-xs text-purple-500 font-medium">Admin</span>
            @elseif (!$isReader)
                <span class="text-xs text-indigo-500 font-medium">Editor</span>
            @endif
            @if ($online)
                <span class="text-xs text-green-600 font-medium">● Online</span>
            @endif
        </div>

        @if ($profile?->title)
            <div class="text-xs text-gray-400 mt-0.5">{{ $profile->title }}</div>
        @endif

        @if ($bio)
            <p class="mt-2 text-xs text-gray-600 leading-relaxed">{{ $bio }}</p>
        @else
            <p class="mt-2 text-xs text-gray-400 italic">No bio yet.</p>
        @endif
    </div>
</div>
