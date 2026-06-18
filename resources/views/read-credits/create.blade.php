<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-4">
            <a href="{{ route('read-credits.index') }}" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Create Credit Package</h2>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-lg mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <form method="POST" action="{{ route('read-credits.store') }}" class="p-6 space-y-5">
                    @csrf

                    <div>
                        <x-input-label for="customer_email" value="Customer Email" />
                        <x-text-input id="customer_email" name="customer_email" type="email"
                            value="{{ old('customer_email') }}" required
                            class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('customer_email')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="customer_name" value="Customer Name" />
                        <x-text-input id="customer_name" name="customer_name" type="text"
                            value="{{ old('customer_name') }}" required
                            class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('customer_name')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="credits" value="Number of Credits" />
                        <x-text-input id="credits" name="credits" type="number"
                            value="{{ old('credits', 5) }}" min="1" max="999" required
                            class="mt-1 block w-32" />
                        <p class="mt-1 text-xs text-gray-400">Standard packages: 5 or 10. Any positive number accepted for manual/comp packages.</p>
                        <x-input-error :messages="$errors->get('credits')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="expires_at" value="Expires At" />
                        <input type="date" id="expires_at" name="expires_at"
                            value="{{ old('expires_at', now()->addYear()->format('Y-m-d')) }}" required
                            class="mt-1 block w-48 border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                        <x-input-error :messages="$errors->get('expires_at')" class="mt-1" />
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                        <a href="{{ route('read-credits.index') }}"
                           class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
                        <x-primary-button>Create Package</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
