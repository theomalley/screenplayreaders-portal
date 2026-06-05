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
            {{-- Profile Photo (reader icon) --}}
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    <h2 class="text-lg font-medium text-gray-900 mb-1">Reader Icon</h2>
                    <p class="text-sm text-gray-600 mb-3">Your avatar in the portal (not public). Min 600×600 px.</p>
                    <form method="POST" action="{{ route('profile.photo') }}" enctype="multipart/form-data"
                          x-data="{
                            preview: '{{ $currentPhoto }}',
                            dragging: false,
                            hasFile: false,
                            readPreview(file) {
                                const r = new FileReader();
                                r.onload = e => { this.preview = e.target.result; this.hasFile = true; };
                                r.readAsDataURL(file);
                            },
                            onDrop(file) {
                                if (!file) return;
                                const dt = new DataTransfer(); dt.items.add(file);
                                this.$refs.photoInput.files = dt.files;
                                this.readPreview(file);
                            }
                          }">
                        @csrf
                        <input x-ref="photoInput" name="photo" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only"
                               @change="if ($event.target.files[0]) readPreview($event.target.files[0])" />
                        <div class="relative border-2 rounded-lg overflow-hidden cursor-pointer transition-colors"
                             :class="dragging ? 'border-indigo-400 bg-indigo-50' : 'border-dashed border-gray-300 hover:border-gray-400'"
                             style="width:200px;height:200px"
                             @click="$refs.photoInput.click()"
                             @dragover.prevent="dragging = true"
                             @dragleave.prevent="dragging = false"
                             @drop.prevent="dragging = false; onDrop($event.dataTransfer.files[0])">
                            <template x-if="preview">
                                <img :src="preview" class="absolute inset-0 w-full h-full object-cover" alt="Photo preview" />
                            </template>
                            <div class="absolute inset-0 flex flex-col items-center justify-center gap-1 pointer-events-none"
                                 :class="preview ? 'bg-black/30 opacity-0 hover:opacity-100 transition-opacity' : ''">
                                <svg class="w-8 h-8" :class="preview ? 'text-white' : 'text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                                <span class="text-sm font-medium" :class="preview ? 'text-white' : 'text-gray-500'">Drop photo here or click to browse</span>
                            </div>
                        </div>
                        <p class="mt-2 text-xs text-gray-500">JPG, PNG or WebP &nbsp;·&nbsp; minimum 600×600 px &nbsp;·&nbsp; max 8 MB</p>
                        <div class="mt-2">
                            <x-primary-button x-bind:disabled="!hasFile" x-bind:class="!hasFile ? 'opacity-40 cursor-not-allowed' : ''">Save Photo</x-primary-button>
                        </div>
                        <x-input-error :messages="$errors->get('photo')" class="mt-2" />
                        @if (session('status') === 'photo-updated')
                            <p class="mt-2 text-sm text-green-600">Saved.</p>
                        @endif
                    </form>
                </div>
            </div>

            {{-- About Page Photo --}}
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    <h2 class="text-lg font-medium text-gray-900 mb-1">About Page Photo</h2>
                    <p class="text-sm text-gray-600 mb-3">Displayed on the public website's About page. Min 600×600 px.</p>
                    <form method="POST" action="{{ route('profile.about-photo') }}" enctype="multipart/form-data"
                          x-data="{
                            preview: '{{ $currentAboutPhoto }}',
                            dragging: false,
                            hasFile: false,
                            readPreview(file) {
                                const r = new FileReader();
                                r.onload = e => { this.preview = e.target.result; this.hasFile = true; };
                                r.readAsDataURL(file);
                            },
                            onDrop(file) {
                                if (!file) return;
                                const dt = new DataTransfer(); dt.items.add(file);
                                this.$refs.aboutInput.files = dt.files;
                                this.readPreview(file);
                            }
                          }">
                        @csrf
                        <input x-ref="aboutInput" name="about_photo" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only"
                               @change="if ($event.target.files[0]) readPreview($event.target.files[0])" />
                        <div class="relative border-2 rounded-lg overflow-hidden cursor-pointer transition-colors"
                             :class="dragging ? 'border-indigo-400 bg-indigo-50' : 'border-dashed border-gray-300 hover:border-gray-400'"
                             style="width:200px;height:200px"
                             @click="$refs.aboutInput.click()"
                             @dragover.prevent="dragging = true"
                             @dragleave.prevent="dragging = false"
                             @drop.prevent="dragging = false; onDrop($event.dataTransfer.files[0])">
                            <template x-if="preview">
                                <img :src="preview" class="absolute inset-0 w-full h-full object-cover" alt="About photo preview" />
                            </template>
                            <div class="absolute inset-0 flex flex-col items-center justify-center gap-1 pointer-events-none"
                                 :class="preview ? 'bg-black/30 opacity-0 hover:opacity-100 transition-opacity' : ''">
                                <svg class="w-8 h-8" :class="preview ? 'text-white' : 'text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                                <span class="text-sm font-medium" :class="preview ? 'text-white' : 'text-gray-500'">Drop photo here or click to browse</span>
                            </div>
                        </div>
                        <p class="mt-2 text-xs text-gray-500">JPG, PNG or WebP &nbsp;·&nbsp; minimum 600×600 px &nbsp;·&nbsp; max 8 MB</p>
                        <div class="mt-2">
                            <x-primary-button x-bind:disabled="!hasFile" x-bind:class="!hasFile ? 'opacity-40 cursor-not-allowed' : ''">Save Photo</x-primary-button>
                        </div>
                        <x-input-error :messages="$errors->get('about_photo')" class="mt-2" />
                        @if (session('status') === 'about-photo-updated')
                            <p class="mt-2 text-sm text-green-600">Saved.</p>
                        @elseif (session('status') === 'about-photo-pending')
                            <p class="mt-2 text-sm text-amber-600">Submitted for admin approval.</p>
                        @endif
                        @if ($aboutPhotoRejectionNote)
                            <div class="mt-2 px-3 py-2 bg-red-50 border border-red-200 rounded text-xs text-red-700">
                                <p class="font-medium">About photo rejected by admin:</p>
                                <p class="mt-0.5">{{ $aboutPhotoRejectionNote }}</p>
                            </div>
                        @elseif ($pendingAboutPhoto)
                            <div class="mt-2 flex items-center gap-3 px-3 py-2 bg-amber-50 border border-amber-200 rounded text-xs text-amber-700">
                                <img src="{{ $pendingAboutPhoto }}" class="w-10 h-10 rounded-full object-cover shrink-0" alt="Pending about photo" />
                                <span>Pending admin approval.</span>
                            </div>
                        @endif
                    </form>
                </div>
            </div>

            @if ($meProfile)
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
                                           {{ old('availability', $meProfile->availability ?? 'available') === 'available' ? 'checked' : '' }}
                                           class="text-green-600 focus:ring-green-500" />
                                    <span class="text-sm font-medium text-green-700">Available</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="availability" value="unavailable"
                                           {{ old('availability', $meProfile->availability ?? 'available') === 'unavailable' ? 'checked' : '' }}
                                           class="text-red-600 focus:ring-red-500" />
                                    <span class="text-sm font-medium text-red-700">Unavailable</span>
                                </label>
                            </div>
                            <x-input-error :messages="$errors->get('availability')" class="mt-1" />
                        </div>

                        <div>
                            <x-input-label for="avail_message" value="Availability Note (internal only, optional)" />
                            <textarea id="avail_message" name="availability_message" rows="2"
                                      class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                      placeholder="e.g. Back Jan 15, on vacation through end of month…">{{ old('availability_message', $meProfile->availability_message) }}</textarea>
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
                    <h2 class="text-lg font-medium text-gray-900 mb-1">My Current Logline</h2>
                    <p class="text-sm text-gray-600 mb-4">A short line shown when teammates click your staff icon — think of it as your current status or a one-liner about what you're working on. Replaces your bio in the popup if set.</p>

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
