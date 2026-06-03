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
            'ocean' => [
                'body_bg'     => '#eef3f8',
                'nav_bg'      => '#1a3a5c',
                'nav_border'  => '#0f2840',
                'dark_nav'    => true,
                'accent'      => '#0ea5e9',
                'accent_dark' => '#0284c7',
                'accent_mid'  => '#38bdf8',
                'accent_bg'   => 'rgba(14,165,233,0.1)',
            ],
            'slate' => [
                'body_bg'     => '#f8f7f5',
                'nav_bg'      => '#334155',
                'nav_border'  => '#1e293b',
                'dark_nav'    => true,
                'accent'      => '#f59e0b',
                'accent_dark' => '#d97706',
                'accent_mid'  => '#fbbf24',
                'accent_bg'   => 'rgba(245,158,11,0.1)',
            ],
            'rose' => [
                'body_bg'     => '#fff5f7',
                'nav_bg'      => '#6b2040',
                'nav_border'  => '#4a1530',
                'dark_nav'    => true,
                'accent'      => '#e11d48',
                'accent_dark' => '#be123c',
                'accent_mid'  => '#fb7185',
                'accent_bg'   => 'rgba(225,29,72,0.1)',
            ],
            'dusk' => [
                'body_bg'     => '#f3f0ff',
                'nav_bg'      => '#2d1f5e',
                'nav_border'  => '#1f1342',
                'dark_nav'    => true,
                'accent'      => '#8b5cf6',
                'accent_dark' => '#7c3aed',
                'accent_mid'  => '#a78bfa',
                'accent_bg'   => 'rgba(139,92,246,0.1)',
            ],
            'crimson' => [
                'body_bg'     => '#fef9f0',
                'nav_bg'      => '#7f1d1d',
                'nav_border'  => '#5a1212',
                'dark_nav'    => true,
                'accent'      => '#b45309',
                'accent_dark' => '#92400e',
                'accent_mid'  => '#d97706',
                'accent_bg'   => 'rgba(180,83,9,0.1)',
            ],
            'steel' => [
                'body_bg'     => '#f9fafb',
                'nav_bg'      => '#374151',
                'nav_border'  => '#1f2937',
                'dark_nav'    => true,
                'accent'      => '#3b82f6',
                'accent_dark' => '#2563eb',
                'accent_mid'  => '#60a5fa',
                'accent_bg'   => 'rgba(59,130,246,0.1)',
            ],
            'teal' => [
                'body_bg'     => '#f0fdfb',
                'nav_bg'      => '#134e4a',
                'nav_border'  => '#0f3d3a',
                'dark_nav'    => true,
                'accent'      => '#0d9488',
                'accent_dark' => '#0f766e',
                'accent_mid'  => '#2dd4bf',
                'accent_bg'   => 'rgba(13,148,136,0.1)',
            ],
            'mocha' => [
                'body_bg'     => '#fdf8f3',
                'nav_bg'      => '#4a2c1a',
                'nav_border'  => '#3a1f0e',
                'dark_nav'    => true,
                'accent'      => '#c2853a',
                'accent_dark' => '#a36c28',
                'accent_mid'  => '#d4a05a',
                'accent_bg'   => 'rgba(194,133,58,0.1)',
            ],
            'arctic' => [
                'body_bg'     => '#ffffff',
                'nav_bg'      => '#f0f4f8',
                'nav_border'  => '#dde3ea',
                'dark_nav'    => false,
                'accent'      => '#4f46e5',
                'accent_dark' => '#4338ca',
                'accent_mid'  => '#6366f1',
                'accent_bg'   => 'rgba(79,70,229,0.1)',
            ],
            'noir' => [
                'body_bg'     => '#121212',
                'nav_bg'      => '#0a0a0a',
                'nav_border'  => '#1a1a1a',
                'dark_nav'    => true,
                'accent'      => '#fbbf24',
                'accent_dark' => '#f59e0b',
                'accent_mid'  => '#fcd34d',
                'accent_bg'   => 'rgba(251,191,36,0.1)',
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
                    ->active()
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
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-amber-900">{{ $_ann->body }}</p>
                        <p class="text-xs text-amber-600 mt-0.5">Posted by {{ $_creator?->name ?? 'Staff' }} · {{ $_ann->created_at->format('M j, Y \a\t g:i A') }}</p>
                    </div>
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
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-amber-700 opacity-75">{{ $_ann->body }}</p>
                                <p class="text-xs text-amber-500 mt-0.5">Posted by {{ $_creator?->name ?? 'Staff' }} · {{ $_ann->created_at->format('M j, Y \a\t g:i A') }}</p>
                            </div>
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
        <script>
        window.srStaffCard = {
            _popup: null,
            _userId: null,
            _cache: {},
            _outsideHandler: null,

            toggle(event, userId, url, btn) {
                event.stopPropagation();
                if (this._userId === userId) { this.close(); return; }
                this.close();
                this._open(userId, url, btn);
            },

            _open(userId, url, btn) {
                const popup = document.createElement('div');
                popup.style.cssText = 'position:fixed;z-index:9999;width:320px;min-width:200px;min-height:60px;background:#fff;border:1px solid #e5e7eb;border-radius:8px;box-shadow:0 10px 25px -5px rgba(0,0,0,.15);padding:16px;resize:both;overflow:auto';
                popup.innerHTML = '<p style="font-size:11px;color:#9ca3af;text-align:center;padding:4px 0">Loading…</p>';

                const rect = btn.getBoundingClientRect();
                popup.style.top  = (rect.bottom + 6) + 'px';
                popup.style.left = Math.max(8, Math.min(rect.left, window.innerWidth - 328)) + 'px';

                document.body.appendChild(popup);
                this._popup  = popup;
                this._userId = userId;

                if (this._cache[userId]) {
                    popup.innerHTML = this._cache[userId];
                } else {
                    fetch(url, { headers: { 'Accept': 'text/html', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '' } })
                        .then(r => r.text())
                        .then(html => { this._cache[userId] = html; if (this._popup === popup) popup.innerHTML = html; })
                        .catch(() => { if (this._popup === popup) popup.innerHTML = '<p style="font-size:11px;color:#ef4444">Could not load.</p>'; });
                }

                setTimeout(() => {
                    this._outsideHandler = (e) => { if (!popup.contains(e.target)) this.close(); };
                    document.addEventListener('click', this._outsideHandler);
                    document.addEventListener('keydown', this._keyHandler = (e) => { if (e.key === 'Escape') this.close(); });
                }, 0);
            },

            close() {
                if (this._popup) { this._popup.remove(); this._popup = null; }
                this._userId = null;
                if (this._outsideHandler) { document.removeEventListener('click', this._outsideHandler); this._outsideHandler = null; }
                if (this._keyHandler)     { document.removeEventListener('keydown', this._keyHandler);   this._keyHandler = null; }
            }
        };
        </script>
        @stack('scripts')
    </body>
</html>
