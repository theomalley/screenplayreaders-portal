<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Screenplay Readers Portal') }}</title>

        @php $faviconMeta = storage_path('app/portal-favicon-path.txt'); @endphp
        @if(is_readable($faviconMeta))
            <link rel="icon" href="{{ asset('storage/' . trim(file_get_contents($faviconMeta))) }}">
        @endif

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @php
        $portalThemes = [
            'default' => [
                'body_bg'     => '#f7f4e6',
                'nav_bg'      => '#2b4158',
                'nav_border'  => '#1e3047',
                'dark_nav'    => true,
                'accent'      => '#3c9590',
                'accent_dark' => '#2d7470',
                'accent_mid'  => '#3c9590',
                'accent_bg'   => 'rgba(60,149,144,0.1)',
            ],
            'midnight' => [
                'body_bg'     => '#1c1c2e',
                'nav_bg'      => '#16213e',
                'nav_border'  => '#0f3460',
                'dark_nav'    => true,
                'accent'      => '#e94560',
                'accent_dark' => '#c73452',
                'accent_mid'  => '#e94560',
                'accent_bg'   => 'rgba(233,69,96,0.1)',
            ],
            'forest' => [
                'body_bg'     => '#f0f4f0',
                'nav_bg'      => '#1e3a2f',
                'nav_border'  => '#2d5440',
                'dark_nav'    => true,
                'accent'      => '#4caf50',
                'accent_dark' => '#388e3c',
                'accent_mid'  => '#81c784',
                'accent_bg'   => 'rgba(76,175,80,0.1)',
            ],
            'warm' => [
                'body_bg'     => '#faf6f1',
                'nav_bg'      => '#5c3317',
                'nav_border'  => '#7a4520',
                'dark_nav'    => true,
                'accent'      => '#d4793b',
                'accent_dark' => '#b8622d',
                'accent_mid'  => '#e09060',
                'accent_bg'   => 'rgba(212,121,59,0.1)',
            ],
        ];
        $pt = $portalThemes[\App\Models\Setting::getValue('portal_theme', 'default')] ?? $portalThemes['default'];
        @endphp
        @if($pt)
        <style id="sr-portal-theme">
body { background-color: {{ $pt['body_bg'] }} !important; }
#portal-nav { background-color: {{ $pt['nav_bg'] }} !important; border-color: {{ $pt['nav_border'] }} !important; }
@if($pt['dark_nav'])
#portal-nav .text-gray-500 { color: rgba(255,255,255,0.65) !important; }
#portal-nav .text-gray-700 { color: rgba(255,255,255,0.85) !important; }
#portal-nav .text-gray-800 { color: #ffffff !important; }
#portal-nav .text-gray-900 { color: #ffffff !important; }
#portal-nav .text-gray-400 { color: rgba(255,255,255,0.45) !important; }
#portal-nav .hover\:text-gray-700:hover { color: #ffffff !important; }
#portal-nav .hover\:text-gray-500:hover { color: rgba(255,255,255,0.8) !important; }
#portal-nav .hover\:bg-gray-100:hover { background-color: rgba(255,255,255,0.1) !important; }
#portal-nav button.bg-white { background-color: transparent !important; }
#portal-nav .hover\:border-gray-300:hover { border-color: rgba(255,255,255,0.4) !important; }
/* Restore dark text inside white dropdown popups (absolute-positioned) */
#portal-nav .absolute .text-gray-500 { color: #6b7280 !important; }
#portal-nav .absolute .text-gray-600 { color: #4b5563 !important; }
#portal-nav .absolute .text-gray-700 { color: #374151 !important; }
#portal-nav .absolute .text-gray-800 { color: #1f2937 !important; }
#portal-nav .absolute .text-gray-900 { color: #111827 !important; }
#portal-nav .absolute .hover\:text-gray-700:hover { color: #374151 !important; }
@endif
.border-indigo-400 { border-color: {{ $pt['accent_mid'] }} !important; }
.text-indigo-700 { color: {{ $pt['accent'] }} !important; }
.text-indigo-600 { color: {{ $pt['accent'] }} !important; }
.text-indigo-400 { color: {{ $pt['accent_mid'] }} !important; }
.bg-indigo-50 { background-color: {{ $pt['accent_bg'] }} !important; }
.bg-indigo-600 { background-color: {{ $pt['accent'] }} !important; }
.border-indigo-600 { border-color: {{ $pt['accent'] }} !important; }
.hover\:bg-indigo-700:hover { background-color: {{ $pt['accent_dark'] }} !important; }
.focus\:ring-indigo-500:focus { --tw-ring-color: {{ $pt['accent'] }}; }
.focus\:border-indigo-500:focus { border-color: {{ $pt['accent'] }} !important; }
.focus\:border-indigo-700:focus { border-color: {{ $pt['accent_dark'] }} !important; }
        </style>
        @endif
    </head>
    <body class="font-sans antialiased bg-gray-100">
        <div class="min-h-screen">
            @include('layouts.navigation')

            @if(auth()->check())
            @php
                $userId = auth()->id();
                $_announcements = \App\Models\Announcement::query()
                    ->whereDoesntHave('reads', fn($q) => $q->where('user_id', $userId)->whereNotNull('dismissed_at'))
                    ->with(['reads' => fn($q) => $q->where('user_id', $userId), 'createdBy.editorProfile'])
                    ->orderBy('created_at', 'desc')
                    ->get();
                $_unread = $_announcements->filter(fn($a) => $a->reads->isEmpty() || $a->reads->first()?->read_at === null);
                $_read   = $_announcements->filter(fn($a) => $a->reads->isNotEmpty() && $a->reads->first()?->read_at !== null);
            @endphp
            @if($_announcements->isNotEmpty())
            <div x-data="{ showPast: false }" class="border-b border-amber-200 bg-amber-50">
                @foreach($_unread as $_ann)
                @php
                    $_creator = $_ann->createdBy;
                    $_creatorPhoto = $_creator?->editorProfile?->photo ? asset('storage/' . $_creator->editorProfile->photo) : null;
                    $_creatorInitials = $_creator?->editorProfile?->initials
                        ?? ($_creator ? strtoupper(implode('', array_map(fn($w) => $w[0], array_filter(explode(' ', $_creator->name))))) : '?');
                    $_creatorInitials = substr($_creatorInitials, 0, 2);
                @endphp
                <div x-data="{ visible: true }"
                     x-show="visible"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0 -translate-y-1"
                     class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2.5 flex items-start gap-3">
                    <div class="relative w-6 h-6 rounded-full bg-amber-300 flex items-center justify-center text-[10px] font-mono font-semibold text-amber-900 shrink-0 mt-0.5 overflow-hidden"
                         title="{{ $_creator?->name ?? 'Staff' }}">
                        @if($_creatorPhoto)
                            <img src="{{ $_creatorPhoto }}" alt="{{ $_creatorInitials }}" class="absolute inset-0 w-full h-full object-cover" />
                        @else
                            {{ $_creatorInitials }}
                        @endif
                    </div>
                    <p class="flex-1 text-sm text-amber-900">{{ $_ann->body }}</p>
                    <div class="flex items-center gap-3 shrink-0">
                        <button @click="
                            visible = false;
                            fetch('{{ route('announcements.mark-read', $_ann) }}', {
                                method: 'POST',
                                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                            });
                        " class="text-xs text-amber-700 underline hover:text-amber-900 whitespace-nowrap">
                            Mark as read
                        </button>
                        <button @click="
                            visible = false;
                            fetch('{{ route('announcements.dismiss', $_ann) }}', {
                                method: 'POST',
                                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                            });
                        " class="text-amber-500 hover:text-amber-700 text-lg leading-none">&times;</button>
                    </div>
                </div>
                @if(!$loop->last) <div class="border-t border-amber-200 mx-4 sm:mx-6 lg:mx-8"></div> @endif
                @endforeach

                @if($_read->isNotEmpty())
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 {{ $_unread->isNotEmpty() ? 'border-t border-amber-200' : '' }} py-1.5">
                    <button @click="showPast = !showPast"
                            class="text-xs text-amber-600 hover:text-amber-800 flex items-center gap-1">
                        <svg class="w-3 h-3 transition-transform" :class="showPast ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                        <span x-text="showPast ? 'Hide past updates' : 'View past updates ({{ $_read->count() }})'"></span>
                    </button>
                    <div x-show="showPast" x-cloak class="mt-2 space-y-2 pb-2">
                        @foreach($_read as $_ann)
                        @php
                            $_creator = $_ann->createdBy;
                            $_creatorPhoto = $_creator?->editorProfile?->photo ? asset('storage/' . $_creator->editorProfile->photo) : null;
                            $_creatorInitials = $_creator?->editorProfile?->initials
                                ?? ($_creator ? strtoupper(implode('', array_map(fn($w) => $w[0], array_filter(explode(' ', $_creator->name))))) : '?');
                            $_creatorInitials = substr($_creatorInitials, 0, 2);
                        @endphp
                        <div x-data="{ visible: true }" x-show="visible" class="flex items-start gap-3">
                            <div class="relative w-6 h-6 rounded-full bg-amber-200 flex items-center justify-center text-[10px] font-mono font-semibold text-amber-700 shrink-0 mt-0.5 overflow-hidden opacity-75"
                                 title="{{ $_creator?->name ?? 'Staff' }}">
                                @if($_creatorPhoto)
                                    <img src="{{ $_creatorPhoto }}" alt="{{ $_creatorInitials }}" class="absolute inset-0 w-full h-full object-cover" />
                                @else
                                    {{ $_creatorInitials }}
                                @endif
                            </div>
                            <p class="flex-1 text-sm text-amber-700 opacity-75">{{ $_ann->body }}</p>
                            <button @click="
                                visible = false;
                                fetch('{{ route('announcements.dismiss', $_ann) }}', {
                                    method: 'POST',
                                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                                });
                            " class="text-amber-400 hover:text-amber-600 text-lg leading-none shrink-0">&times;</button>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
            @endif
            @endif

            @if (isset($header))
                <header class="bg-white shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endif

            <main>
                {{ $slot }}
            </main>
        </div>
        @stack('scripts')
    </body>
</html>
