<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(auth()->user()->isReader())
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-notifications-form')
                </div>
            </div>
            @endif

            @if(auth()->user()->isAdminOrEditor() || auth()->user()->isReader())
            @php
                $meProfile               = auth()->user()->isAdminOrEditor() ? auth()->user()->editorProfile : auth()->user()->readerProfile;
                $currentPhoto            = $meProfile?->photo ? asset('storage/' . $meProfile->photo) : null;
                $pendingPhoto            = $meProfile?->photo_pending ? asset('storage/' . $meProfile->photo_pending) : null;
                $currentAboutPhoto       = $meProfile?->about_photo ? asset('storage/' . $meProfile->about_photo) : null;
                $pendingAboutPhoto       = $meProfile?->about_photo_pending ? asset('storage/' . $meProfile->about_photo_pending) : null;
                $pendingBio              = $meProfile?->bio_pending;
                $photoRejectionNote      = $meProfile?->photo_rejection_note;
                $aboutPhotoRejectionNote = $meProfile?->about_photo_rejection_note;
                $bioRejectionNote        = $meProfile?->bio_rejection_note;
            @endphp
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl" x-data="{ preview: null }">
                    <h2 class="text-lg font-medium text-gray-900 mb-1">Profile Photo</h2>
                    <p class="text-sm text-gray-600 mb-4">Used as your avatar throughout the portal and on the public website. Minimum 600×600 px.</p>

                    <div class="flex items-center gap-5 mb-4">
                        <div class="relative w-16 h-16 rounded-full bg-gray-200 overflow-hidden shrink-0">
                            <img :src="preview || '{{ $currentPhoto }}'"
                                 x-show="preview || {{ $currentPhoto ? 'true' : 'false' }}"
                                 alt="Profile photo" class="absolute inset-0 w-full h-full object-cover" />
                            @if(!$currentPhoto)
                            <span x-show="!preview" class="absolute inset-0 flex items-center justify-center text-gray-400 text-xs font-mono font-semibold">
                                {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                            </span>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('profile.photo') }}" enctype="multipart/form-data" class="flex-1">
                            @csrf
                            <x-input-label for="profile_photo" value="Choose photo" />
                            <input id="profile_photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp"
                                   @change="const f = $event.target.files[0]; if (f) { const r = new FileReader(); r.onload = e => preview = e.target.result; r.readAsDataURL(f) }"
                                   class="mt-1 block w-full text-sm text-gray-500
                                          file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0
                                          file:text-sm file:font-medium file:bg-gray-100 file:text-gray-700
                                          hover:file:bg-gray-200 cursor-pointer" />
                            <p class="mt-1 text-xs text-gray-400">JPG, PNG, or WebP · min 600×600 px · max 8 MB</p>
                            <x-input-error :messages="$errors->get('photo')" class="mt-1" />
                            <div class="mt-3">
                                <x-primary-button>Upload Photo</x-primary-button>
                                @if (session('status') === 'photo-updated')
                                    <span class="ml-3 text-sm text-green-600">Saved.</span>
                                @elseif (session('status') === 'photo-pending')
                                    <span class="ml-3 text-sm text-amber-600">Submitted for admin approval.</span>
                                @endif
                            </div>
                            @if ($photoRejectionNote)
                                <div class="mt-3 px-3 py-2 bg-red-50 border border-red-200 rounded text-xs text-red-700">
                                    <p class="font-medium">Photo rejected by admin:</p>
                                    <p class="mt-0.5">{{ $photoRejectionNote }}</p>
                                </div>
                            @elseif ($pendingPhoto)
                                <div class="mt-3 flex items-center gap-3 px-3 py-2 bg-amber-50 border border-amber-200 rounded text-xs text-amber-700">
                                    <img src="{{ $pendingPhoto }}" class="w-10 h-10 rounded-full object-cover shrink-0" alt="Pending photo" />
                                    <span>Photo pending admin approval.</span>
                                </div>
                            @endif
                        </form>
                    </div>
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl" x-data="{ preview: null }">
                    <h2 class="text-lg font-medium text-gray-900 mb-1">About Page Photo</h2>
                    <p class="text-sm text-gray-600 mb-4">Displayed on the public website's About page. Minimum 600×600 px.</p>

                    <div class="flex items-center gap-5 mb-4">
                        <div class="relative w-16 h-16 rounded-full bg-gray-200 overflow-hidden shrink-0">
                            <img :src="preview || '{{ $currentAboutPhoto }}'"
                                 x-show="preview || {{ $currentAboutPhoto ? 'true' : 'false' }}"
                                 alt="About page photo" class="absolute inset-0 w-full h-full object-cover" />
                            @if(!$currentAboutPhoto)
                            <span x-show="!preview" class="absolute inset-0 flex items-center justify-center text-gray-400 text-xs">None</span>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('profile.about-photo') }}" enctype="multipart/form-data" class="flex-1">
                            @csrf
                            <x-input-label for="about_photo_input" value="Choose photo" />
                            <input id="about_photo_input" name="about_photo" type="file" accept="image/jpeg,image/png,image/webp"
                                   @change="const f = $event.target.files[0]; if (f) { const r = new FileReader(); r.onload = e => preview = e.target.result; r.readAsDataURL(f) }"
                                   class="mt-1 block w-full text-sm text-gray-500
                                          file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0
                                          file:text-sm file:font-medium file:bg-gray-100 file:text-gray-700
                                          hover:file:bg-gray-200 cursor-pointer" />
                            <p class="mt-1 text-xs text-gray-400">JPG, PNG, or WebP · min 600×600 px · max 8 MB</p>
                            <x-input-error :messages="$errors->get('about_photo')" class="mt-1" />
                            <div class="mt-3">
                                <x-primary-button>Upload Photo</x-primary-button>
                                @if (session('status') === 'about-photo-updated')
                                    <span class="ml-3 text-sm text-green-600">Saved.</span>
                                @elseif (session('status') === 'about-photo-pending')
                                    <span class="ml-3 text-sm text-amber-600">Submitted for admin approval.</span>
                                @endif
                            </div>
                            @if ($aboutPhotoRejectionNote)
                                <div class="mt-3 px-3 py-2 bg-red-50 border border-red-200 rounded text-xs text-red-700">
                                    <p class="font-medium">About photo rejected by admin:</p>
                                    <p class="mt-0.5">{{ $aboutPhotoRejectionNote }}</p>
                                </div>
                            @elseif ($pendingAboutPhoto)
                                <div class="mt-3 flex items-center gap-3 px-3 py-2 bg-amber-50 border border-amber-200 rounded text-xs text-amber-700">
                                    <img src="{{ $pendingAboutPhoto }}" class="w-10 h-10 rounded-full object-cover shrink-0" alt="Pending about photo" />
                                    <span>About photo pending admin approval.</span>
                                </div>
                            @endif
                        </form>
                    </div>
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @php $currentBio = $meProfile?->bio; @endphp
                    <h2 class="text-lg font-medium text-gray-900 mb-1">Bio</h2>
                    <p class="text-sm text-gray-600 mb-4">Displayed on the public website. HTML is supported — you can use <code>&lt;b&gt;</code>, <code>&lt;i&gt;</code>, <code>&lt;a href=""&gt;</code>, etc.</p>

                    <form method="POST" action="{{ route('profile.bio') }}" class="space-y-4">
                        @csrf
                        @method('PATCH')
                        <div x-data="{
                            wrap(b,a){const t=this.$refs.ta,s=t.selectionStart,e=t.selectionEnd,v=t.value.slice(s,e);t.value=t.value.slice(0,s)+b+v+a+t.value.slice(e);t.selectionStart=s+b.length;t.selectionEnd=s+b.length+v.length;t.focus()},
                            link(){const u=prompt('URL (include https://)');if(u)this.wrap('<a href=\''+u+'\'>', '</a>')}
                        }">
                            <div class="flex items-center gap-1 mb-1">
                                <button type="button" @click="wrap('<b>','</b>')" class="px-2 py-0.5 text-xs font-bold border border-gray-300 rounded bg-white hover:bg-gray-100 leading-5">B</button>
                                <button type="button" @click="wrap('<i>','</i>')" class="px-2 py-0.5 text-xs italic border border-gray-300 rounded bg-white hover:bg-gray-100 leading-5">I</button>
                                <button type="button" @click="link()" class="px-2 py-0.5 text-xs border border-gray-300 rounded bg-white hover:bg-gray-100 leading-5">Link</button>
                            </div>
                            <textarea x-ref="ta" id="bio" name="bio" rows="6"
                                      class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400 resize-y"
                                      maxlength="5000"
                                      placeholder="Write your bio here. HTML is allowed.">{{ old('bio', $currentBio) }}</textarea>
                        </div>
                        <x-input-error :messages="$errors->get('bio')" class="mt-1" />
                        <div>
                            <x-primary-button>Save Bio</x-primary-button>
                            @if (session('status') === 'bio-updated')
                                <span class="ml-3 text-sm text-green-600">Saved.</span>
                            @elseif (session('status') === 'bio-pending')
                                <span class="ml-3 text-sm text-amber-600">Submitted for admin approval.</span>
                            @endif
                        </div>
                        @if ($bioRejectionNote)
                            <div class="mt-2 px-3 py-2 bg-red-50 border border-red-200 rounded text-xs text-red-700">
                                <p class="font-medium">Bio rejected by admin:</p>
                                <p class="mt-0.5">{{ $bioRejectionNote }}</p>
                            </div>
                        @elseif ($pendingBio)
                            <div class="mt-2 px-3 py-2 bg-amber-50 border border-amber-200 rounded text-xs text-amber-700">
                                Bio change pending admin approval.
                            </div>
                        @endif
                    </form>
                </div>
            </div>
            @endif

            @if(auth()->user()->isAdminOrEditor() || auth()->user()->isReader())
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    <h2 class="text-lg font-medium text-gray-900 mb-1">Staff Card Message</h2>
                    <p class="text-sm text-gray-600 mb-4">A short message shown when teammates click your staff icon. If set, this replaces your bio in the popup. Leave blank to show your bio instead.</p>

                    <form method="POST" action="{{ route('profile.custom-message') }}" class="space-y-4">
                        @csrf
                        @method('PATCH')
                        <textarea name="custom_message" rows="2"
                                  class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400 resize-none"
                                  maxlength="200"
                                  placeholder="e.g. On holiday until Jan 15. Feel free to reach out!">{{ old('custom_message', $meProfile?->custom_message) }}</textarea>
                        <p class="text-xs text-gray-400 -mt-2">Plain text only · Max 200 characters</p>
                        <x-input-error :messages="$errors->get('custom_message')" class="mt-1" />
                        <div>
                            <x-primary-button>Save Message</x-primary-button>
                            @if (session('status') === 'custom-message-updated')
                                <span class="ml-3 text-sm text-green-600">Saved.</span>
                            @endif
                        </div>
                    </form>
                </div>
            </div>
            @endif

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            @php
                $availProfile = auth()->user()->isReader()
                    ? auth()->user()->readerProfile
                    : auth()->user()->editorProfile;
            @endphp
            @if ($availProfile)
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    <h2 class="text-lg font-medium text-gray-900 mb-1">My Availability</h2>
                    <p class="text-sm text-gray-600 mb-4">Visible to the assignments team — not shown to customers.</p>

                    @if (session('availability_success'))
                        <div class="mb-4 bg-green-50 border border-green-200 rounded-lg p-3 text-sm text-green-700">
                            {{ session('availability_success') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('availability.update') }}" class="space-y-4">
                        @csrf
                        @method('PATCH')

                        <div>
                            <x-input-label value="Status" />
                            <div class="mt-2 flex gap-6">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="availability" value="available"
                                           {{ old('availability', $availProfile->availability ?? 'available') === 'available' ? 'checked' : '' }}
                                           class="text-green-600 focus:ring-green-500" />
                                    <span class="text-sm font-medium text-green-700">Available</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="availability" value="unavailable"
                                           {{ old('availability', $availProfile->availability ?? 'available') === 'unavailable' ? 'checked' : '' }}
                                           class="text-red-600 focus:ring-red-500" />
                                    <span class="text-sm font-medium text-red-700">Unavailable</span>
                                </label>
                            </div>
                            <x-input-error :messages="$errors->get('availability')" class="mt-1" />
                        </div>

                        <div>
                            <x-input-label for="avail_message" value="Note (optional)" />
                            <textarea id="avail_message" name="availability_message" rows="2"
                                      class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                      placeholder="e.g. Back Jan 15, on vacation through end of month…">{{ old('availability_message', $availProfile->availability_message) }}</textarea>
                            <x-input-error :messages="$errors->get('availability_message')" class="mt-1" />
                        </div>

                        <div class="flex justify-end">
                            <x-primary-button>Save Availability</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
            @endif

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            @unless(auth()->user()->isReader())
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
            @endunless
        </div>
    </div>
</x-app-layout>
