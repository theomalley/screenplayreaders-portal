<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-4">
            <a href="{{ route('admin.editors.index') }}" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Editor Profile — {{ $user->name }}
            </h2>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <form id="update-form" method="POST" action="{{ route('admin.editors.update', $user) }}" class="p-6 space-y-5"
                      enctype="multipart/form-data">
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

                    {{-- Photo --}}
                    @php $currentPhotoUrl = $profile?->photo ? asset('storage/' . $profile->photo) : ''; @endphp
                    <div x-data="{ previewUrl: '{{ $currentPhotoUrl }}' }">
                        <x-input-label value="Photo" />
                        <div class="mt-2 flex items-center gap-4">
                            <div class="relative w-16 h-16 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 text-lg font-mono font-semibold shrink-0 overflow-hidden">
                                <img x-show="previewUrl" :src="previewUrl" alt="" class="absolute inset-0 w-full h-full object-cover" />
                                <span x-show="!previewUrl">{{ $profile?->initials ?? '?' }}</span>
                            </div>
                            <div class="flex-1">
                                <input type="file" name="photo" id="photo" accept="image/*"
                                       @change="if ($event.target.files[0]) {
                                           const r = new FileReader();
                                           r.onload = e => previewUrl = e.target.result;
                                           r.readAsDataURL($event.target.files[0]);
                                       }"
                                       class="block w-full text-sm text-gray-500
                                              file:mr-3 file:py-1.5 file:px-3
                                              file:rounded file:border file:border-gray-300
                                              file:text-xs file:font-medium file:text-gray-700
                                              file:bg-white hover:file:bg-gray-50 file:cursor-pointer
                                              cursor-pointer" />
                                <p class="mt-1 text-xs text-gray-400">JPEG or PNG, max 4 MB. Leave blank to keep current photo.</p>
                            </div>
                        </div>
                        <x-input-error :messages="$errors->get('photo')" class="mt-1" />
                    </div>

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
                            <p class="text-xs text-gray-400">1–3 uppercase letters</p>
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

                    {{-- PayPal --}}
                    <div>
                        <x-input-label for="paypal_email" value="PayPal Email" />
                        <x-text-input id="paypal_email" name="paypal_email" type="email"
                            class="mt-1 block w-full"
                            value="{{ old('paypal_email', $profile?->paypal_email) }}"
                            placeholder="optional" />
                        <x-input-error :messages="$errors->get('paypal_email')" class="mt-1" />
                    </div>

                    {{-- Availability --}}
                    <div class="pt-4 border-t border-gray-100 space-y-4">
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Availability</p>

                        <div>
                            <x-input-label value="Status" />
                            <div class="mt-2 flex gap-6">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="availability" value="available"
                                           {{ old('availability', $profile?->availability ?? 'available') === 'available' ? 'checked' : '' }}
                                           class="text-green-600 focus:ring-green-500" />
                                    <span class="text-sm font-medium text-green-700">Available</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="availability" value="unavailable"
                                           {{ old('availability', $profile?->availability ?? 'available') === 'unavailable' ? 'checked' : '' }}
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
                                      placeholder="Optional — e.g. back Jan 15, on vacation, etc.">{{ old('availability_message', $profile?->availability_message) }}</textarea>
                            <p class="mt-1 text-xs text-gray-400">Visible to admin only.</p>
                            <x-input-error :messages="$errors->get('availability_message')" class="mt-1" />
                        </div>

                        <div>
                            <x-input-label for="upload_warning" value="Upload Form Warning" />
                            <textarea id="upload_warning" name="upload_warning"
                                      rows="3"
                                      class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                      placeholder="Optional — shown to customers on the upload form when they select this editor.">{{ old('upload_warning', $profile?->upload_warning) }}</textarea>
                            <p class="mt-1 text-xs text-gray-400">Leave blank for no warning.</p>
                            <x-input-error :messages="$errors->get('upload_warning')" class="mt-1" />
                        </div>
                    </div>

                    {{-- Account --}}
                    <div class="pt-4 border-t border-gray-100 space-y-4">
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Account</p>

                        <div>
                            <x-input-label for="email" value="Email" />
                            <x-text-input id="email" name="email" type="email"
                                class="mt-1 block w-full"
                                value="{{ old('email', $user->email) }}"
                                required />
                            <x-input-error :messages="$errors->get('email')" class="mt-1" />
                        </div>

                        <div>
                            <x-input-label for="password" value="New Password" />
                            <x-text-input id="password" name="password" type="password"
                                class="mt-1 block w-full"
                                placeholder="Leave blank to keep current password" />
                            <x-input-error :messages="$errors->get('password')" class="mt-1" />
                        </div>

                        <div>
                            <x-input-label for="password_confirmation" value="Confirm New Password" />
                            <x-text-input id="password_confirmation" name="password_confirmation" type="password"
                                class="mt-1 block w-full"
                                placeholder="Leave blank to keep current password" />
                        </div>
                    </div>

                </form>

                <div class="flex items-center justify-between px-6 py-4 border-t border-gray-100">
                    <form method="POST" action="{{ route('admin.editors.destroy', $user) }}"
                          onsubmit="return confirm('Permanently delete this editor? This cannot be undone.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="inline-flex items-center px-3 py-1.5 bg-white border border-red-300 rounded text-xs font-medium text-red-600 hover:bg-red-50 transition">
                            Delete Editor
                        </button>
                    </form>
                    <div class="flex items-center gap-3">
                        <a href="{{ route('admin.editors.index') }}"
                           class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
                        <x-primary-button form="update-form">Save Profile</x-primary-button>
                    </div>
                </div>
            </div>
        {{-- ── COMMISSION CONFIG ── --}}
        @php
            $commissionConfig = $profile?->productCommissionsKeyed() ?? collect();
            $globalRate = (float) \App\Models\Setting::getValue('rate_editor_commission', 10.0);
        @endphp
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 bg-gray-50 border-b border-gray-200">
                <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Commission Config</h3>
                <p class="text-xs text-gray-400 mt-0.5">
                    Toggle which products earn commission and set custom flat amounts per occurrence.
                    Leave amount blank to use the global rate ({{ $globalRate }}% of eligible precommission share).
                </p>
            </div>
            <form method="POST" action="{{ route('admin.editors.commissions', $user) }}">
                @csrf
                @method('PATCH')
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50 text-xs font-medium text-gray-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-2 text-left">Product / Service</th>
                            <th class="px-4 py-2 text-center">Earns Commission</th>
                            <th class="px-4 py-2 text-right">Custom Flat Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach(\App\Models\EditorProductCommission::PRODUCTS as $productId => $product)
                        @php
                            $existing = $commissionConfig->get($productId);
                            $enabled  = $existing ? $existing->commission_enabled : $product['commission'];
                            $custom   = $existing?->custom_amount;
                        @endphp
                        <tr class="hover:bg-gray-50 {{ !$enabled ? 'opacity-50' : '' }}">
                            <td class="px-5 py-2 text-gray-700">{{ $product['label'] }}</td>
                            <td class="px-4 py-2 text-center">
                                <input type="checkbox"
                                    name="commissions[{{ $productId }}][enabled]"
                                    value="1"
                                    {{ $enabled ? 'checked' : '' }}
                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            </td>
                            <td class="px-4 py-2 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <span class="text-gray-400 text-xs">$</span>
                                    <input type="number" step="0.01" min="0"
                                        name="commissions[{{ $productId }}][amount]"
                                        value="{{ $custom !== null ? number_format((float)$custom, 2) : '' }}"
                                        placeholder="global %"
                                        class="w-24 text-right text-sm border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="flex justify-end px-5 py-4 border-t border-gray-100">
                    <x-primary-button>Save Commission Config</x-primary-button>
                </div>
            </form>
        </div>

        </div>
    </div>
</x-app-layout>
