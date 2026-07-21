<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-4">
            <a href="{{ route('readers.index') }}" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Add Reader</h2>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-lg mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <form method="POST" action="{{ route('readers.store') }}" enctype="multipart/form-data" class="p-6 space-y-5">
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

                    {{-- Reader profile --}}
                    <div class="space-y-4">
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Reader Profile</p>

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

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <x-input-label for="max_concurrent_assignments" value="Max Concurrent Assignments" />
                                <x-text-input id="max_concurrent_assignments" name="max_concurrent_assignments" type="number"
                                    class="mt-1 block w-full"
                                    value="{{ old('max_concurrent_assignments', 3) }}"
                                    min="0" max="20" required />
                                <x-input-error :messages="$errors->get('max_concurrent_assignments')" class="mt-1" />
                                <label class="flex items-center gap-2 mt-2 cursor-pointer select-none">
                                    <input type="checkbox" name="requests_bypass_capacity" value="1"
                                        {{ old('requests_bypass_capacity') ? 'checked' : '' }}
                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                    <span class="text-sm text-gray-600">Requests don't count toward capacity</span>
                                </label>
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
                    </div>

                    {{-- Photos --}}
                    <div class="pt-4 border-t border-gray-100 space-y-4">
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Photos</p>

                        <div class="grid grid-cols-2 gap-3">
                            <div x-data="{ previewUrl: '', hasFile: false }">
                                <x-input-label value="Reader Icon" />
                                <p class="text-xs text-gray-500 mb-2">Portal avatar and public website icon.</p>
                                <div class="relative border-2 rounded-lg overflow-hidden cursor-pointer transition-colors border-dashed border-gray-300 hover:border-gray-400 mb-2"
                                     style="width:120px;height:120px"
                                     @click="$refs.photoInput.click()">
                                    <template x-if="previewUrl">
                                        <img :src="previewUrl" class="absolute inset-0 w-full h-full object-cover" alt="Photo preview" />
                                    </template>
                                    <div class="absolute inset-0 flex flex-col items-center justify-center gap-1 pointer-events-none"
                                         :class="previewUrl ? 'bg-black/30 opacity-0 hover:opacity-100 transition-opacity' : ''">
                                        <svg class="w-6 h-6" :class="previewUrl ? 'text-white' : 'text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                                        <span class="text-xs font-medium" :class="previewUrl ? 'text-white' : 'text-gray-500'">Click to browse</span>
                                    </div>
                                </div>
                                <input x-ref="photoInput" name="photo" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only"
                                       @change="if ($event.target.files[0]) { const r=new FileReader(); r.onload=e=>{previewUrl=e.target.result;hasFile=true}; r.readAsDataURL($event.target.files[0]); }" />
                                <p class="text-xs text-gray-400">Min 600×600 &nbsp;·&nbsp; max 8 MB</p>
                                <x-input-error :messages="$errors->get('photo')" class="mt-1" />
                            </div>

                            <div x-data="{ previewUrl: '', hasFile: false }">
                                <x-input-label value="About Page Photo" />
                                <p class="text-xs text-gray-500 mb-2">Shown on the public About page.</p>
                                <div class="relative border-2 rounded-lg overflow-hidden cursor-pointer transition-colors border-dashed border-gray-300 hover:border-gray-400 mb-2"
                                     style="width:120px;height:120px"
                                     @click="$refs.aboutInput.click()">
                                    <template x-if="previewUrl">
                                        <img :src="previewUrl" class="absolute inset-0 w-full h-full object-cover" alt="About photo preview" />
                                    </template>
                                    <div class="absolute inset-0 flex flex-col items-center justify-center gap-1 pointer-events-none"
                                         :class="previewUrl ? 'bg-black/30 opacity-0 hover:opacity-100 transition-opacity' : ''">
                                        <svg class="w-6 h-6" :class="previewUrl ? 'text-white' : 'text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                                        <span class="text-xs font-medium" :class="previewUrl ? 'text-white' : 'text-gray-500'">Click to browse</span>
                                    </div>
                                </div>
                                <input x-ref="aboutInput" name="about_photo" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only"
                                       @change="if ($event.target.files[0]) { const r=new FileReader(); r.onload=e=>{previewUrl=e.target.result;hasFile=true}; r.readAsDataURL($event.target.files[0]); }" />
                                <p class="text-xs text-gray-400">Min 600×600 &nbsp;·&nbsp; max 8 MB</p>
                                <x-input-error :messages="$errors->get('about_photo')" class="mt-1" />
                            </div>
                        </div>
                    </div>

                    {{-- Tier Membership --}}
                    <div class="pt-4 border-t border-gray-100 space-y-2">
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Tier Membership</p>
                        <div class="flex flex-wrap items-center gap-x-5 gap-y-2">
                            @foreach (\App\Models\Tier::ordered()->get() as $tierOption)
                                <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer select-none">
                                    <input type="checkbox" name="tiers[]" value="{{ $tierOption->id }}"
                                           {{ collect(old('tiers', []))->contains($tierOption->id) ? 'checked' : '' }}
                                           class="rounded border-gray-300 {{ $tierOption->is_onboarding ? 'text-amber-600 focus:ring-amber-500' : 'text-indigo-600 focus:ring-indigo-500' }} shadow-sm" />
                                    {{ $tierOption->name }}{{ $tierOption->is_onboarding ? ' (Onboarding)' : '' }}
                                </label>
                            @endforeach
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
                                      placeholder="Optional — internal note, not shown to customers">{{ old('availability_message') }}</textarea>
                            <x-input-error :messages="$errors->get('availability_message')" class="mt-1" />
                        </div>

                        <div>
                            <x-input-label for="upload_warning" value="Upload Form Warning" />
                            <textarea id="upload_warning" name="upload_warning"
                                      rows="3"
                                      class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                      placeholder="Optional — shown to customers on the upload form when they select this reader">{{ old('upload_warning') }}</textarea>
                            <p class="mt-1 text-xs text-gray-400">Leave blank for no warning.</p>
                            <x-input-error :messages="$errors->get('upload_warning')" class="mt-1" />
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2 border-t border-gray-100">
                        <a href="{{ route('readers.index') }}"
                           class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
                        <x-primary-button>Create Reader</x-primary-button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</x-app-layout>
