<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('code', 'Error') — Screenplay Readers Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-50 flex flex-col items-center justify-center px-6 py-12">
    @php
        try {
            $metaFile = storage_path('app/portal-logo-path.txt');
            $logoUrl  = is_readable($metaFile) ? asset('storage/' . trim(file_get_contents($metaFile))) : null;
        } catch (\Throwable $e) {
            $logoUrl = null;
        }
    @endphp

    <div class="text-center max-w-sm w-full">

        {{-- Logo --}}
        <div class="mb-10">
            <a href="{{ url('/') }}">
                @if ($logoUrl)
                    <img src="{{ $logoUrl }}" alt="Screenplay Readers" class="h-14 w-auto object-contain mx-auto">
                @else
                    <span class="text-xl font-bold tracking-tight text-gray-800">Screenplay Readers</span>
                @endif
            </a>
        </div>

        {{-- Error code --}}
        <div class="text-[7rem] font-black leading-none text-gray-200 select-none mb-1">
            @yield('code', '500')
        </div>

        {{-- Heading + message --}}
        <h1 class="text-xl font-bold text-gray-800 mt-2 mb-2">@yield('heading', 'Something went wrong')</h1>
        <p class="text-sm text-gray-500 leading-relaxed mb-8">@yield('message', 'An unexpected error occurred. Please try again.')</p>

        {{-- Home link --}}
        <a href="{{ url('/') }}"
           class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold rounded-lg transition-colors">
            ← Back to home
        </a>

    </div>
</body>
</html>
