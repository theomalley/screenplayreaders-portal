{{-- Staff popup card — rendered server-side, injected via x-html --}}
@php
    $isReader  = $user->isReader();
    $initials  = $profile?->initials ?? strtoupper(substr($user->name, 0, 2));
    $photoRaw  = $profile?->photo;
    $photoUrl  = $photoRaw ? asset('storage/' . $photoRaw) : null;
    $online    = $user->isOnline();
    $active    = $user->assignments->count();
    $max       = $profile?->max_concurrent_assignments ?? 0;
    $bgClass   = $isReader ? 'bg-gray-200 text-gray-700' : 'bg-indigo-100 text-indigo-700';
@endphp

<div class="flex items-start gap-3">
    <div class="relative w-10 h-10 rounded-full {{ $bgClass }} flex items-center justify-center text-sm font-mono font-semibold shrink-0 overflow-hidden">
        @if ($photoUrl)
            <img src="{{ $photoUrl }}" alt="{{ $initials }}" class="absolute inset-0 w-full h-full object-cover" />
        @else
            {{ $initials }}
        @endif
        @if ($online)
            <span class="absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full bg-green-400 ring-2 ring-white z-10"></span>
        @endif
    </div>

    <div class="min-w-0 flex-1">
        <div class="flex items-baseline gap-2 flex-wrap">
            <span class="font-semibold text-gray-900 text-sm">{{ $profile?->displayName() ?? $user->name }}</span>
            @if ($user->isAdmin())
                <span class="text-xs text-purple-500 font-medium">Admin</span>
            @elseif (! $isReader)
                <span class="text-xs text-indigo-500 font-medium">Editor</span>
            @endif
            @if ($online)
                <span class="text-xs text-green-600 font-medium">● Online</span>
            @endif
            <div class="ml-auto flex items-center gap-3">
                @if (! $user->isAdmin() && $user->email)
                    <a href="{{ route('staff.draft-email', $user) }}" target="_blank"
                       class="text-xs text-gray-400 hover:text-indigo-600 transition"
                       title="Create HelpScout draft to {{ $user->email }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="inline w-3.5 h-3.5 mr-0.5 -mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>Email
                    </a>
                @endif
                @if ($editUrl)
                    <a href="{{ $editUrl }}" class="text-xs text-indigo-500 hover:text-indigo-700 underline">Edit Profile</a>
                @endif
            </div>
        </div>

        @if ($profile?->title)
            <div class="text-xs text-gray-400 mt-0.5">{{ $profile->title }}</div>
        @endif

        <div class="text-xs text-gray-500 mt-1">
            {{ $active }}{{ $max ? '/' . $max : '' }} active assignment{{ $active !== 1 ? 's' : '' }}
        </div>

        @if ($weekStats)
            <div class="mt-2 space-y-0.5">
                <div class="text-xs flex items-center gap-1.5">
                    <span class="text-gray-400">This week</span>
                    <span class="text-gray-400 text-[10px]">({{ $weekStats['this_label'] }})</span>
                    <span class="font-semibold text-green-700">${{ number_format($weekStats['this_pay'], 2) }}</span>
                    <span class="text-gray-400">· {{ $weekStats['this_count'] }} done</span>
                </div>
                <div class="text-xs flex items-center gap-1.5">
                    <span class="text-gray-400">Last week</span>
                    <span class="text-gray-400 text-[10px]">({{ $weekStats['last_label'] }})</span>
                    <span class="font-medium text-gray-600">${{ number_format($weekStats['last_pay'], 2) }}</span>
                    <span class="text-gray-400">· {{ $weekStats['last_count'] }} done</span>
                </div>
            </div>
        @endif

        @if ($user->assignments->isNotEmpty())
            <ul class="mt-2 space-y-1 border-t border-gray-100 pt-2">
                @foreach ($user->assignments as $ra)
                    <li class="text-xs text-gray-700 flex items-start gap-1.5">
                        <span class="font-mono text-gray-400 shrink-0">{{ $ra->order_number }}</span>
                        <span class="font-medium truncate">{{ $ra->script_title }}</span>
                        @if ($ra->rush)
                            <span class="inline-flex px-1 py-0.5 rounded text-[9px] font-bold bg-amber-400 text-amber-900 uppercase leading-none shrink-0">Rush</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @else
            <p class="mt-2 text-xs text-gray-400">No active assignments.</p>
        @endif
    </div>
</div>
