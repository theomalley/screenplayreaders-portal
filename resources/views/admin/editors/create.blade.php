<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-4">
            <a href="{{ route('admin.editors.index') }}" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Add Editor</h2>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-lg mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <form method="POST" action="{{ route('admin.editors.store') }}" class="p-6 space-y-5">
                    @csrf

                    @if ($errors->any())
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-sm text-red-700">
                            <ul class="list-disc list-inside space-y-0.5">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Login credentials --}}
                    <div class="pb-4 border-b border-gray-100 space-y-4">
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Login</p>

                        <div>
                            <x-input-label for="name" value="Display Name" />
                            <x-text-input id="name" name="name" type="text"
                                class="mt-1 block w-full"
                                value="{{ old('name') }}"
                                required />
                            <x-input-error :messages="$errors->get('name')" class="mt-1" />
                        </div>

                        <div>
                            <x-input-label for="email" value="Email" />
                            <x-text-input id="email" name="email" type="email"
                                class="mt-1 block w-full"
                                value="{{ old('email') }}"
                                required />
                            <x-input-error :messages="$errors->get('email')" class="mt-1" />
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <x-input-label for="password" value="Password" />
                                <x-text-input id="password" name="password" type="password"
                                    class="mt-1 block w-full"
                                    required />
                                <x-input-error :messages="$errors->get('password')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label for="password_confirmation" value="Confirm Password" />
                                <x-text-input id="password_confirmation" name="password_confirmation" type="password"
                                    class="mt-1 block w-full"
                                    required />
                            </div>
                        </div>
                    </div>

                    {{-- Editor profile --}}
                    <div class="space-y-4">
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Editor Profile</p>

                        <div>
                            <x-input-label for="initials" value="Initials" />
                            <div class="mt-1 flex items-center gap-3">
                                <x-text-input
                                    id="initials" name="initials" type="text"
                                    class="block w-24 uppercase tracking-widest font-mono text-center text-lg"
                                    value="{{ old('initials') }}"
                                    maxlength="3"
                                    placeholder="AB"
                                    oninput="this.value = this.value.toUpperCase()"
                                    required />
                                <p class="text-xs text-gray-400">1–3 uppercase letters</p>
                            </div>
                            <x-input-error :messages="$errors->get('initials')" class="mt-1" />
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <x-input-label for="first_name" value="First Name" />
                                <x-text-input id="first_name" name="first_name" type="text"
                                    class="mt-1 block w-full"
                                    value="{{ old('first_name') }}"
                                    required />
                                <x-input-error :messages="$errors->get('first_name')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label for="last_name" value="Last Name" />
                                <x-text-input id="last_name" name="last_name" type="text"
                                    class="mt-1 block w-full"
                                    value="{{ old('last_name') }}"
                                    required />
                                <x-input-error :messages="$errors->get('last_name')" class="mt-1" />
                            </div>
                        </div>

                        <div>
                            <x-input-label for="paypal_email" value="PayPal Email" />
                            <x-text-input id="paypal_email" name="paypal_email" type="email"
                                class="mt-1 block w-full"
                                value="{{ old('paypal_email') }}"
                                placeholder="optional" />
                            <x-input-error :messages="$errors->get('paypal_email')" class="mt-1" />
                        </div>
                    </div>

                    {{-- Rates --}}
                    <div class="pt-4 border-t border-gray-100 space-y-4">
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Rates</p>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="editor_commission" value="Commission Rate" />
                                <div class="mt-1 flex items-center gap-1">
                                    <x-text-input id="editor_commission" name="editor_commission" type="number"
                                        class="block w-24 text-right"
                                        value="{{ old('editor_commission') }}"
                                        min="0" max="100" step="0.01"
                                        placeholder="0.00" />
                                    <span class="text-gray-400 text-sm">%</span>
                                </div>
                                <x-input-error :messages="$errors->get('editor_commission')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label for="editor_weekly_flat" value="Weekly Flat Pay" />
                                <div class="mt-1 flex items-center gap-1">
                                    <span class="text-gray-400 text-sm">$</span>
                                    <x-text-input id="editor_weekly_flat" name="editor_weekly_flat" type="number"
                                        class="block w-28 text-right"
                                        value="{{ old('editor_weekly_flat') }}"
                                        min="0" max="9999.99" step="0.01"
                                        placeholder="0.00" />
                                </div>
                                <x-input-error :messages="$errors->get('editor_weekly_flat')" class="mt-1" />
                            </div>
                        </div>
                    </div>

                    {{-- Availability --}}
                    <div class="pt-4 border-t border-gray-100 space-y-4">
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Availability</p>

                        <div>
                            <x-input-label value="Status" />
                            <div class="mt-2 flex gap-6">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="availability" value="available"
                                           {{ old('availability', 'available') === 'available' ? 'checked' : '' }}
                                           class="text-green-600 focus:ring-green-500" />
                                    <span class="text-sm font-medium text-green-700">Available</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="availability" value="unavailable"
                                           {{ old('availability', 'available') === 'unavailable' ? 'checked' : '' }}
                                           class="text-red-600 focus:ring-red-500" />
                                    <span class="text-sm font-medium text-red-700">Unavailable</span>
                                </label>
                            </div>
                            <x-input-error :messages="$errors->get('availability')" class="mt-1" />
                        </div>

                        <div>
                            <x-input-label for="availability_message" value="Availability Note" />
                            <textarea id="availability_message" name="availability_message"
                                      rows="2"
                                      class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                      placeholder="Optional — internal note">{{ old('availability_message') }}</textarea>
                            <x-input-error :messages="$errors->get('availability_message')" class="mt-1" />
                        </div>

                        <div>
                            <x-input-label for="upload_warning" value="Upload Form Warning" />
                            <textarea id="upload_warning" name="upload_warning"
                                      rows="3"
                                      class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                      placeholder="Optional — shown to customers on the upload form when they select this editor">{{ old('upload_warning') }}</textarea>
                            <p class="mt-1 text-xs text-gray-400">Leave blank for no warning.</p>
                            <x-input-error :messages="$errors->get('upload_warning')" class="mt-1" />
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2 border-t border-gray-100">
                        <a href="{{ route('admin.editors.index') }}"
                           class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
                        <x-primary-button>Create Editor</x-primary-button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</x-app-layout>
