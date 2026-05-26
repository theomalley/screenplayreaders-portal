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
