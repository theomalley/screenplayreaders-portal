<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-4">
            <a href="{{ route('readers.index') }}" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Reader Profile — {{ $user->name }}
            </h2>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-lg mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <form method="POST" action="{{ route('readers.update', $user) }}" class="p-6 space-y-5">
                    @csrf
                    @method('PATCH')

                    @if ($errors->any())
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-sm text-red-700">
                            <ul class="list-disc list-inside space-y-0.5">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Initials --}}
                    <div>
                        <x-input-label for="initials" value="Initials" />
                        <div class="mt-1 flex items-center gap-3">
                            <x-text-input
                                id="initials" name="initials" type="text"
                                class="block w-24 uppercase tracking-widest font-mono text-center text-lg"
                                value="{{ old('initials', $profile?->initials) }}"
                                maxlength="3"
                                placeholder="AB"
                                oninput="this.value = this.value.toUpperCase()"
                                required />
                            <p class="text-xs text-gray-400">1–3 uppercase letters — primary reader identifier throughout the app</p>
                        </div>
                        <x-input-error :messages="$errors->get('initials')" class="mt-1" />
                    </div>

                    {{-- Name --}}
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <x-input-label for="first_name" value="First Name" />
                            <x-text-input id="first_name" name="first_name" type="text"
                                class="mt-1 block w-full"
                                value="{{ old('first_name', $profile?->first_name) }}"
                                required />
                            <x-input-error :messages="$errors->get('first_name')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="last_name" value="Last Name" />
                            <x-text-input id="last_name" name="last_name" type="text"
                                class="mt-1 block w-full"
                                value="{{ old('last_name', $profile?->last_name) }}"
                                required />
                            <x-input-error :messages="$errors->get('last_name')" class="mt-1" />
                        </div>
                    </div>

                    {{-- Capacity + PayPal --}}
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <x-input-label for="max_concurrent_assignments" value="Max Concurrent Assignments" />
                            <x-text-input id="max_concurrent_assignments" name="max_concurrent_assignments" type="number"
                                class="mt-1 block w-full"
                                value="{{ old('max_concurrent_assignments', $profile?->max_concurrent_assignments ?? 3) }}"
                                min="0" max="20" required />
                            <x-input-error :messages="$errors->get('max_concurrent_assignments')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="paypal_email" value="PayPal Email" />
                            <x-text-input id="paypal_email" name="paypal_email" type="email"
                                class="mt-1 block w-full"
                                value="{{ old('paypal_email', $profile?->paypal_email) }}"
                                placeholder="optional" />
                            <x-input-error :messages="$errors->get('paypal_email')" class="mt-1" />
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-2 border-t border-gray-100">
                        <p class="text-xs text-gray-400">{{ $user->email }}</p>
                        <div class="flex items-center gap-3">
                            <a href="{{ route('readers.index') }}"
                               class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
                            <x-primary-button>Save Profile</x-primary-button>
                        </div>
                    </div>

                </form>
            </div>
        </div>
    </div>
</x-app-layout>
