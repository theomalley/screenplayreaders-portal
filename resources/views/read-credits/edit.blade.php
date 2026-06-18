<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-4">
            <a href="{{ route('read-credits.index') }}" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Edit Credit Package</h2>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-lg mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">

                {{-- Read-only info --}}
                <div class="p-6 border-b border-gray-100 space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Customer</span>
                        <span class="text-gray-900 font-medium">{{ $package->customer_name }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Email</span>
                        <span class="text-gray-900">{{ $package->customer_email }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Order #</span>
                        <span class="text-gray-900 font-mono text-xs">{{ $package->woo_order_number }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Package</span>
                        <span class="text-gray-900">{{ $package->packageLabel() }} ({{ $package->credits_purchased }} credits)</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Created</span>
                        <span class="text-gray-900">{{ $package->created_at->setTimezone($appTimezone)->format('M j, Y g:ia') }}</span>
                    </div>
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-500">Upload URL</span>
                        <span x-data="{ copied: false }">
                            <button type="button"
                                @click="navigator.clipboard.writeText(@js($package->uploadUrl())).then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
                                class="text-xs text-indigo-600 hover:text-indigo-800 underline">
                                <span x-show="!copied">Copy URL</span>
                                <span x-show="copied" x-cloak class="text-green-600">Copied!</span>
                            </button>
                        </span>
                    </div>
                </div>

                {{-- Editable fields --}}
                <form method="POST" action="{{ route('read-credits.update', $package) }}" class="p-6 space-y-5">
                    @csrf
                    @method('PATCH')

                    <div>
                        <x-input-label for="credits_remaining" value="Credits Remaining" />
                        <x-text-input id="credits_remaining" name="credits_remaining" type="number"
                            value="{{ old('credits_remaining', $package->credits_remaining) }}" min="0" max="999" required
                            class="mt-1 block w-32" />
                        <p class="mt-1 text-xs text-gray-400">Originally purchased: {{ $package->credits_purchased }}</p>
                        <x-input-error :messages="$errors->get('credits_remaining')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="expires_at" value="Expires At" />
                        <input type="date" id="expires_at" name="expires_at"
                            value="{{ old('expires_at', $package->expires_at->format('Y-m-d')) }}" required
                            class="mt-1 block w-48 border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                        <x-input-error :messages="$errors->get('expires_at')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="status" value="Status" />
                        <select id="status" name="status"
                            class="mt-1 block w-48 border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                            <option value="active" @selected(old('status', $package->status) === 'active')>Active</option>
                            <option value="expired" @selected(old('status', $package->status) === 'expired')>Expired</option>
                            <option value="exhausted" @selected(old('status', $package->status) === 'exhausted')>Exhausted</option>
                        </select>
                        <x-input-error :messages="$errors->get('status')" class="mt-1" />
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                        <a href="{{ route('read-credits.index') }}"
                           class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
                        <x-primary-button>Save Changes</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
