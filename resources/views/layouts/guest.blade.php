<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="robots" content="noindex, nofollow">

        <title>{{ config('app.name', 'Screenplay Readers Portal') }}</title>

        @php $faviconMeta = storage_path('app/portal-favicon-path.txt'); @endphp
        @if(is_readable($faviconMeta))
            <link rel="icon" href="{{ asset('storage/' . trim(file_get_contents($faviconMeta))) }}">
        @endif

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100">
            <div>
                <a href="/">
                    @php
                        $metaFile = storage_path('app/portal-login-logo-path.txt');
                        $loginLogoUrl = is_readable($metaFile) ? asset('storage/' . trim(file_get_contents($metaFile))) : null;
                    @endphp
                    @if($loginLogoUrl)
                        <img src="{{ $loginLogoUrl }}" alt="" class="h-28 w-auto object-contain">
                    @else
                        <x-application-logo class="w-28 h-28 fill-current text-gray-500" />
                    @endif
                </a>
            </div>

            <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white shadow-md overflow-hidden sm:rounded-lg">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
