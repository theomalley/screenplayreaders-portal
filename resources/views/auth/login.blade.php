<x-guest-layout>
    <x-auth-session-status class="mb-4" :status="session('status')" />

    @if (session('magic_link_status'))
        <div class="mb-6 px-4 py-3 bg-green-50 border border-green-300 rounded-lg text-sm text-green-800">
            {{ session('magic_link_status') }}
        </div>
    @endif

    {{-- Password login --}}
    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="remember">
                <span class="ms-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="flex items-center justify-end mt-4">
            @if (Route::has('password.request'))
                <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @endif

            <x-primary-button class="ms-3">
                {{ __('Log in') }}
            </x-primary-button>
        </div>
    </form>

    {{-- Divider --}}
    <div class="relative my-6">
        <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-gray-200"></div></div>
        <div class="relative flex justify-center text-xs uppercase"><span class="bg-white px-3 text-gray-400 tracking-wider">or</span></div>
    </div>

    {{-- Magic link --}}
    <form method="POST" action="{{ route('magic-link.send') }}">
        @csrf
        <p class="text-sm text-gray-600 mb-3">Enter your email and we'll send you a one-click login link — no password needed.</p>
        <div class="flex gap-2">
            <x-text-input name="email" type="email" class="flex-1" placeholder="your@email.com"
                          :value="old('email')" autocomplete="email" />
            <x-primary-button type="submit">Email me a link</x-primary-button>
        </div>
        <x-input-error :messages="$errors->get('email')" class="mt-2" />
    </form>
</x-guest-layout>
