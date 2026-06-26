<x-app-layout>
    <x-slot name="header">
        @if ($isSelf)
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Profile') }}</h2>
        @else
            <div class="flex items-center gap-4">
                <a href="{{ $user->isReader() ? route('readers.index') : route('team.index') }}"
                   class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ $user->isAdmin() ? 'Admin' : ($user->isEditor() ? 'Editor' : 'Reader') }} Profile — {{ $user->name }}
                </h2>
            </div>
        @endif
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        @if ($isSelf)
        {{-- ══════════════════════════════════════════════════
             SELF-EDIT LAYOUT
        ══════════════════════════════════════════════════ --}}

            @if($user->isReader())
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-notifications-form')
                </div>
            </div>
            @endif

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    <h2 class="text-lg font-medium text-gray-900 mb-1">Dashboard Refresh Rate</h2>
                    <p class="text-sm text-gray-600 mb-4">How often the assignments dashboard automatically refreshes itself while you're on the "All" tab.</p>

                    @if (session('status') === 'refresh-interval-updated')
                        <div class="mb-4 bg-green-50 border border-green-200 rounded-lg p-3 text-sm text-green-700">
                            Saved.
                        </div>
                    @endif

                    <form method="POST" action="{{ route('profile.refresh-interval') }}" class="space-y-4">
                        @csrf
                        @method('PATCH')
                        <div>
                            <x-input-label for="refresh_interval_seconds" value="Refresh every (seconds)" />
                            <input type="number" id="refresh_interval_seconds" name="refresh_interval_seconds" min="30" max="3600"
                                   value="{{ old('refresh_interval_seconds', $user->refresh_interval_seconds) }}"
                                   class="mt-1 block w-32 border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                            <x-input-error :messages="$errors->get('refresh_interval_seconds')" class="mt-1" />
                            <p class="mt-1 text-xs text-gray-400">Minimum 30 seconds.</p>
                        </div>
                        <div class="flex justify-end">
                            <x-primary-button>Save</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>

            @if($user->isAdminOrEditor() || $user->isReader())
            @php
                $currentPhoto            = $profile?->photo             ? asset('storage/' . $profile->photo)             : null;
                $aboutPath               = $profile?->about_photo ?? $profile?->about_photo_pending ?? $profile?->photo;
                $currentAboutPhoto       = $aboutPath ? asset('storage/' . $aboutPath) : null;
                $pendingAboutPhotoSelf   = $profile?->about_photo_pending ? asset('storage/' . $profile->about_photo_pending) : null;
                $pendingBioSelf          = $profile?->bio_pending;
                $aboutPhotoRejectionNote = $profile?->about_photo_rejection_note;
                $bioRejectionNote        = $profile?->bio_rejection_note;
            @endphp

            {{-- Reader Icon --}}
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
                            preview: '',
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
                            @if($currentAboutPhoto)
                                <img src="{{ $currentAboutPhoto }}" x-show="!preview" class="absolute inset-0 w-full h-full object-cover" alt="About photo" />
                            @endif
                            <img x-show="preview" :src="preview" x-cloak class="absolute inset-0 w-full h-full object-cover" alt="About photo preview" />
                            <div class="absolute inset-0 flex flex-col items-center justify-center gap-1 pointer-events-none"
                                 :class="(preview || {{ $currentAboutPhoto ? 'true' : 'false' }}) ? 'bg-black/30 opacity-0 hover:opacity-100 transition-opacity' : ''">
                                <svg class="w-8 h-8" :class="(preview || {{ $currentAboutPhoto ? 'true' : 'false' }}) ? 'text-white' : 'text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                                <span class="text-sm font-medium" :class="(preview || {{ $currentAboutPhoto ? 'true' : 'false' }}) ? 'text-white' : 'text-gray-500'">Drop photo here or click to browse</span>
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
                        @elseif ($pendingAboutPhotoSelf)
                            <div class="mt-2 flex items-center gap-3 px-3 py-2 bg-amber-50 border border-amber-200 rounded text-xs text-amber-700">
                                <img src="{{ $pendingAboutPhotoSelf }}" class="w-10 h-10 rounded-full object-cover shrink-0" alt="Pending about photo" />
                                <span>Pending admin approval.</span>
                            </div>
                        @endif
                    </form>
                </div>
            </div>

            @if ($profile)
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
                                           {{ old('availability', $profile->availability ?? 'available') === 'available' ? 'checked' : '' }}
                                           class="text-green-600 focus:ring-green-500" />
                                    <span class="text-sm font-medium text-green-700">Available</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="availability" value="unavailable"
                                           {{ old('availability', $profile->availability ?? 'available') === 'unavailable' ? 'checked' : '' }}
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
                                      placeholder="e.g. Back Jan 15, on vacation through end of month…">{{ old('availability_message', $profile->availability_message) }}</textarea>
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
                                      placeholder="Write your bio here. HTML is allowed.">{{ old('bio', $profile?->bio) }}</textarea>
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
                        @elseif ($pendingBioSelf)
                            <div class="mt-2 px-3 py-2 bg-amber-50 border border-amber-200 rounded text-xs text-amber-700">
                                Bio change pending admin approval.
                            </div>
                        @endif
                    </form>
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    <h2 class="text-lg font-medium text-gray-900 mb-1">My Current Logline</h2>
                    <p class="text-sm text-gray-600 mb-4">A short line shown when teammates click your staff icon — think of it as your current status or a one-liner about what you're working on.</p>
                    <form method="POST" action="{{ route('profile.custom-message') }}" class="space-y-4">
                        @csrf
                        @method('PATCH')
                        <textarea name="custom_message" rows="2"
                                  class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400 resize-none"
                                  maxlength="200"
                                  placeholder="e.g. On holiday until Jan 15. Feel free to reach out!">{{ old('custom_message', $profile?->custom_message) }}</textarea>
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

            @unless($user->isReader())
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
            @endunless

        @else
        {{-- ══════════════════════════════════════════════════
             ADMIN-EDIT LAYOUT  (same card style, one outer form)
        ══════════════════════════════════════════════════ --}}

            @if (session('success'))
                <div class="p-4 sm:p-8 bg-green-50 shadow sm:rounded-lg text-sm text-green-700">
                    {{ session('success') }}
                </div>
            @endif

            @php
                $isEditingReader      = $user->isReader();
                $updateRoute          = $isEditingReader ? route('readers.update', $user) : route('admin.editors.update', $user);
                $currentPhotoUrl      = $profile?->photo       ? asset('storage/' . $profile->photo)       : null;
                $aboutPhotoPath       = $profile?->about_photo ?? $profile?->about_photo_pending ?? $profile?->photo;
                $currentAboutPhotoUrl = $aboutPhotoPath ? asset('storage/' . $aboutPhotoPath) : null;
                $nameParts            = explode(' ', $user->name, 2);
                $fallbackFirst        = $nameParts[0] ?? '';
                $fallbackLast         = $nameParts[1] ?? '';
            @endphp

            {{-- Single outer form — all Save buttons within it submit everything. --}}
            {{-- Each card is a visual section; per-card saves re-submit the whole form --}}
            {{-- (safe: file inputs are ignored when empty, password ignored when blank). --}}
            <form id="admin-form" method="POST" action="{{ $updateRoute }}" enctype="multipart/form-data" class="space-y-6">
                @csrf
                @method('PATCH')

                @if ($errors->any())
                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <div class="max-w-xl">
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-sm text-red-700">
                            <ul class="list-disc list-inside space-y-0.5">
                                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                            </ul>
                        </div>
                    </div>
                </div>
                @endif

                {{-- Reader Icon --}}
                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <div class="max-w-xl" x-data="{ previewUrl: '{{ $currentPhotoUrl }}', hasFile: false }">
                        <h2 class="text-lg font-medium text-gray-900 mb-1">Reader Icon</h2>
                        <p class="text-sm text-gray-600 mb-3">Portal avatar and public website icon. Min 600×600 px. Leave blank to keep current.</p>
                        <div class="relative border-2 rounded-lg overflow-hidden cursor-pointer transition-colors border-dashed border-gray-300 hover:border-gray-400 mb-3"
                             style="width:200px;height:200px"
                             @click="$refs.photoInput.click()">
                            <template x-if="previewUrl">
                                <img :src="previewUrl" class="absolute inset-0 w-full h-full object-cover" alt="Photo preview" />
                            </template>
                            <div class="absolute inset-0 flex flex-col items-center justify-center gap-1 pointer-events-none"
                                 :class="previewUrl ? 'bg-black/30 opacity-0 hover:opacity-100 transition-opacity' : ''">
                                <svg class="w-8 h-8" :class="previewUrl ? 'text-white' : 'text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                                <span class="text-sm font-medium" :class="previewUrl ? 'text-white' : 'text-gray-500'">Click to browse</span>
                            </div>
                        </div>
                        <input x-ref="photoInput" name="photo" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only"
                               @change="if ($event.target.files[0]) { const r=new FileReader(); r.onload=e=>{previewUrl=e.target.result;hasFile=true}; r.readAsDataURL($event.target.files[0]); }" />
                        <p class="text-xs text-gray-500 mb-2">JPG, PNG or WebP &nbsp;·&nbsp; min 600×600 px &nbsp;·&nbsp; max 8 MB</p>
                        <x-input-error :messages="$errors->get('photo')" class="mt-1" />
                        <x-primary-button x-bind:disabled="!hasFile" x-bind:class="!hasFile ? 'opacity-40 cursor-not-allowed' : ''">Save Photo</x-primary-button>
                    </div>
                </div>

                {{-- About Page Photo --}}
                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <div class="max-w-xl" x-data="{ previewUrl: '', hasFile: false }">
                        <h2 class="text-lg font-medium text-gray-900 mb-1">About Page Photo</h2>
                        <p class="text-sm text-gray-600 mb-3">Displayed on the public website's About page. Min 600×600 px. Leave blank to keep current.</p>
                        <div class="relative border-2 rounded-lg overflow-hidden cursor-pointer transition-colors border-dashed border-gray-300 hover:border-gray-400 mb-3"
                             style="width:200px;height:200px"
                             @click="$refs.aboutInput.click()">
                            @if($currentAboutPhotoUrl)
                                <img src="{{ $currentAboutPhotoUrl }}" x-show="!previewUrl" class="absolute inset-0 w-full h-full object-cover" alt="About photo" />
                            @endif
                            <img x-show="previewUrl" :src="previewUrl" x-cloak class="absolute inset-0 w-full h-full object-cover" alt="About photo preview" />
                            <div class="absolute inset-0 flex flex-col items-center justify-center gap-1 pointer-events-none"
                                 :class="(previewUrl || {{ $currentAboutPhotoUrl ? 'true' : 'false' }}) ? 'bg-black/30 opacity-0 hover:opacity-100 transition-opacity' : ''">
                                <svg class="w-8 h-8" :class="(previewUrl || {{ $currentAboutPhotoUrl ? 'true' : 'false' }}) ? 'text-white' : 'text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                                <span class="text-sm font-medium" :class="(previewUrl || {{ $currentAboutPhotoUrl ? 'true' : 'false' }}) ? 'text-white' : 'text-gray-500'">Click to browse</span>
                            </div>
                        </div>
                        <input x-ref="aboutInput" name="about_photo" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only"
                               @change="if ($event.target.files[0]) { const r=new FileReader(); r.onload=e=>{previewUrl=e.target.result;hasFile=true}; r.readAsDataURL($event.target.files[0]); }" />
                        <p class="text-xs text-gray-500 mb-2">JPG, PNG or WebP &nbsp;·&nbsp; min 600×600 px &nbsp;·&nbsp; max 8 MB</p>
                        <x-input-error :messages="$errors->get('about_photo')" class="mt-1" />
                        @if (!empty($pendingAboutPhotoUrl))
                            @include('partials.approval-panel', [
                                'type'       => 'about-photo',
                                'approveUrl' => route('admin.approvals.about-photo.approve', $user),
                                'rejectUrl'  => route('admin.approvals.about-photo.reject', $user),
                                'preview'    => '<img src="'.e($pendingAboutPhotoUrl).'" class="w-12 h-12 rounded-full object-cover shrink-0" alt="Pending about photo" />',
                                'label'      => 'Pending about page photo waiting for approval.',
                            ])
                        @endif
                        <x-primary-button x-bind:disabled="!hasFile" x-bind:class="!hasFile ? 'opacity-40 cursor-not-allowed' : ''">Save Photo</x-primary-button>
                    </div>
                </div>

                {{-- Availability --}}
                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <div class="max-w-xl space-y-4">
                        <h2 class="text-lg font-medium text-gray-900 mb-1">Availability</h2>
                        <p class="text-sm text-gray-600 -mt-2">Visible to the assignments team — not shown to customers.</p>
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
                            <x-input-label for="availability_message" value="Availability Note (optional)" />
                            <textarea id="availability_message" name="availability_message" rows="2"
                                      class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                      placeholder="e.g. Back Jan 15, on vacation, etc.">{{ old('availability_message', $profile?->availability_message) }}</textarea>
                            <x-input-error :messages="$errors->get('availability_message')" class="mt-1" />
                        </div>
                        <div class="flex justify-end">
                            <x-primary-button>Save Availability</x-primary-button>
                        </div>
                    </div>
                </div>

                {{-- Bio --}}
                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <div class="max-w-xl space-y-4">
                        <h2 class="text-lg font-medium text-gray-900 mb-1">Bio</h2>
                        <p class="text-sm text-gray-600 -mt-2">Displayed on the public website. HTML is supported.</p>
                        <div x-data="{
                            wrap(b,a){const t=this.$refs.ta,s=t.selectionStart,e=t.selectionEnd,v=t.value.slice(s,e);t.value=t.value.slice(0,s)+b+v+a+t.value.slice(e);t.selectionStart=s+b.length;t.selectionEnd=s+b.length+v.length;t.focus()},
                            link(){const u=prompt('URL (include https://)');if(u)this.wrap('<a href=\''+u+'\'>', '</a>')}
                        }">
                            <div class="flex items-center gap-1 mb-1">
                                <button type="button" @click="wrap('<b>','</b>')" class="px-2 py-0.5 text-xs font-bold border border-gray-300 rounded bg-white hover:bg-gray-100 leading-5">B</button>
                                <button type="button" @click="wrap('<i>','</i>')" class="px-2 py-0.5 text-xs italic border border-gray-300 rounded bg-white hover:bg-gray-100 leading-5">I</button>
                                <button type="button" @click="link()" class="px-2 py-0.5 text-xs border border-gray-300 rounded bg-white hover:bg-gray-100 leading-5">Link</button>
                            </div>
                            <textarea x-ref="ta" name="bio" rows="6"
                                      class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400 resize-y"
                                      maxlength="5000"
                                      placeholder="Write their bio here. HTML is allowed.">{{ old('bio', $profile?->bio) }}</textarea>
                        </div>
                        <p class="text-xs text-gray-400">HTML allowed — &lt;b&gt;, &lt;i&gt;, &lt;a href=""&gt;, etc. Max 5000 characters.</p>
                        <x-input-error :messages="$errors->get('bio')" class="mt-1" />
                        <div class="px-3 py-2 bg-gray-50 border border-gray-200 rounded text-xs text-gray-500 space-y-1">
                            <p class="font-medium text-gray-600">Website shortcodes</p>
                            <p><code class="bg-white px-1 rounded border border-gray-200 select-all">[sr_staff_photo id="{{ $user->id }}" float="left" width="200" height="200" gap="1.5em"]</code></p>
                            <p><code class="bg-white px-1 rounded border border-gray-200 select-all">[sr_staff_bio id="{{ $user->id }}"]</code></p>
                            <p class="text-gray-400 italic">Changes take up to 30 seconds to appear on the website due to caching.</p>
                        </div>
                        @if (!empty($pendingBioContent))
                            @include('partials.approval-panel', [
                                'type'       => 'bio',
                                'approveUrl' => route('admin.approvals.bio.approve', $user),
                                'rejectUrl'  => route('admin.approvals.bio.reject', $user),
                                'preview'    => '<div class="text-xs text-gray-600 italic w-full">'.e(strip_tags($pendingBioContent)).'</div>',
                                'label'      => 'Pending bio waiting for approval:',
                            ])
                        @endif
                        <div class="flex justify-end">
                            <x-primary-button>Save Bio</x-primary-button>
                        </div>
                    </div>
                </div>

                {{-- Logline (admin override) --}}
                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <div class="max-w-xl space-y-4">
                        <h2 class="text-lg font-medium text-gray-900 mb-1">Logline</h2>
                        <p class="text-sm text-gray-600 -mt-2">A short status line shown when teammates click their staff icon. Clear the field to remove it.</p>
                        <textarea name="custom_message" rows="2"
                                  class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400 resize-none"
                                  maxlength="200"
                                  placeholder="e.g. On holiday until Jan 15">{{ old('custom_message', $profile?->custom_message) }}</textarea>
                        <p class="text-xs text-gray-400 -mt-2">Plain text only · Max 200 characters</p>
                        <x-input-error :messages="$errors->get('custom_message')" class="mt-1" />
                        <div class="flex justify-end">
                            <x-primary-button>Save Logline</x-primary-button>
                        </div>
                    </div>
                </div>

                @if (!$isEditingReader)
                {{-- Timezone (editors/admins) --}}
                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <div class="max-w-xl space-y-4">
                        <h2 class="text-lg font-medium text-gray-900">Timezone</h2>
                        <div>
                            <x-timezone-select name="timezone" :selected="old('timezone', $profile?->timezone ?? 'UTC')" class="block w-full" />
                            <p class="mt-1 text-xs text-gray-400">Used for displaying and entering assignment dates.</p>
                            <x-input-error :messages="$errors->get('timezone')" class="mt-1" />
                        </div>
                        <div class="flex justify-end">
                            <x-primary-button>Save Timezone</x-primary-button>
                        </div>
                    </div>
                </div>
                @endif

                {{-- Account --}}
                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <div class="max-w-xl space-y-4">
                        <h2 class="text-lg font-medium text-gray-900">Account</h2>
                        <div>
                            <x-input-label for="email" value="Email" />
                            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full"
                                value="{{ old('email', $user->email) }}" required />
                            <x-input-error :messages="$errors->get('email')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="password" value="New Password" />
                            <x-text-input id="password" name="password" type="password" class="mt-1 block w-full"
                                placeholder="Leave blank to keep current password" />
                            <x-input-error :messages="$errors->get('password')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="password_confirmation" value="Confirm New Password" />
                            <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full"
                                placeholder="Leave blank to keep current password" />
                        </div>
                        <div class="flex justify-end">
                            <x-primary-button>Save Account</x-primary-button>
                        </div>
                    </div>
                </div>

                {{-- Identity — admin only --}}
                <div class="p-4 sm:p-8 bg-gray-50 shadow sm:rounded-lg">
                    <div class="max-w-xl space-y-4">
                        <h2 class="text-lg font-medium text-gray-900">Identity <span class="ml-1 align-middle text-[11px] font-medium bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full border border-gray-200">admin only</span></h2>
                        <div>
                            <x-input-label for="initials" value="Initials" />
                            <div class="mt-1 flex items-center gap-3">
                                <x-text-input id="initials" name="initials" type="text"
                                    class="block w-24 uppercase tracking-widest font-mono text-center text-lg"
                                    value="{{ old('initials', $profile?->initials) }}"
                                    maxlength="3" placeholder="AB"
                                    oninput="this.value = this.value.toUpperCase()" required />
                                <p class="text-xs text-gray-400">1–3 uppercase letters — primary identifier throughout the app</p>
                            </div>
                            <x-input-error :messages="$errors->get('initials')" class="mt-1" />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <x-input-label for="first_name" value="First Name" />
                                <x-text-input id="first_name" name="first_name" type="text" class="mt-1 block w-full"
                                    value="{{ old('first_name', $profile?->first_name ?? $fallbackFirst) }}" required />
                                <x-input-error :messages="$errors->get('first_name')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label for="last_name" value="Last Name" />
                                <x-text-input id="last_name" name="last_name" type="text" class="mt-1 block w-full"
                                    value="{{ old('last_name', $profile?->last_name ?? $fallbackLast) }}" required />
                                <x-input-error :messages="$errors->get('last_name')" class="mt-1" />
                            </div>
                        </div>
                        <div>
                            <x-input-label for="title" value="Title" />
                            <x-text-input id="title" name="title" type="text" class="mt-1 block w-full"
                                value="{{ old('title', $profile?->title) }}"
                                placeholder="{{ $isEditingReader ? 'e.g. Lead Reader, Formatting Specialist' : 'e.g. Senior Editor, Managing Editor' }}" />
                            <x-input-error :messages="$errors->get('title')" class="mt-1" />
                        </div>
                        <div class="flex justify-end">
                            <x-primary-button>Save Identity</x-primary-button>
                        </div>
                    </div>
                </div>

                @if ($isEditingReader)
                {{-- Capacity & Pay (readers only) — admin only --}}
                <div class="p-4 sm:p-8 bg-gray-50 shadow sm:rounded-lg">
                    <div class="max-w-xl space-y-4">
                        <h2 class="text-lg font-medium text-gray-900">Capacity &amp; Pay <span class="ml-1 align-middle text-[11px] font-medium bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full border border-gray-200">admin only</span></h2>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <x-input-label for="max_concurrent_assignments" value="Max Concurrent Assignments" />
                                <x-text-input id="max_concurrent_assignments" name="max_concurrent_assignments" type="number"
                                    class="mt-1 block w-full"
                                    value="{{ old('max_concurrent_assignments', $profile?->max_concurrent_assignments ?? 3) }}"
                                    min="0" max="20" required />
                                <x-input-error :messages="$errors->get('max_concurrent_assignments')" class="mt-1" />
                                <label class="flex items-center gap-2 mt-2 cursor-pointer select-none">
                                    <input type="checkbox" name="requests_bypass_capacity" value="1"
                                        {{ old('requests_bypass_capacity', $profile?->requests_bypass_capacity) ? 'checked' : '' }}
                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                    <span class="text-sm text-gray-600">Requests don't count toward capacity</span>
                                </label>
                            </div>
                            <div>
                                <x-input-label for="paypal_email" value="PayPal Email" />
                                <x-text-input id="paypal_email" name="paypal_email" type="email" class="mt-1 block w-full"
                                    value="{{ old('paypal_email', $profile?->paypal_email) }}" placeholder="optional" />
                                <x-input-error :messages="$errors->get('paypal_email')" class="mt-1" />
                                <label class="flex items-center gap-2 mt-2 cursor-pointer select-none">
                                    <input type="checkbox" name="is_1099" value="1"
                                        {{ old('is_1099', $profile?->is_1099) ? 'checked' : '' }}
                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                    <span class="text-sm text-gray-600">1099 contractor</span>
                                </label>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-x-5 gap-y-2">
                            <span class="text-sm font-medium text-gray-700">Tier Membership</span>
                            <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer select-none">
                                <input type="checkbox" name="tier_1" value="1"
                                       {{ old('tier_1', $profile?->tier_1 ?? true) ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                Tier 1
                            </label>
                            <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer select-none">
                                <input type="checkbox" name="tier_2" value="1"
                                       {{ old('tier_2', $profile?->tier_2 ?? false) ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                Tier 2
                            </label>
                        </div>
                        <div class="flex justify-end">
                            <x-primary-button>Save Capacity &amp; Pay</x-primary-button>
                        </div>
                    </div>
                </div>
                @else
                {{-- Pay (editors/admins only) — admin only --}}
                <div class="p-4 sm:p-8 bg-gray-50 shadow sm:rounded-lg">
                    <div class="max-w-xl space-y-4">
                        <h2 class="text-lg font-medium text-gray-900">Pay <span class="ml-1 align-middle text-[11px] font-medium bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full border border-gray-200">admin only</span></h2>
                        <div>
                            <x-input-label for="paypal_email" value="PayPal Email" />
                            <x-text-input id="paypal_email" name="paypal_email" type="email" class="mt-1 block w-full"
                                value="{{ old('paypal_email', $profile?->paypal_email) }}" placeholder="optional" />
                            <x-input-error :messages="$errors->get('paypal_email')" class="mt-1" />
                        </div>
                        <div class="flex justify-end">
                            <x-primary-button>Save Pay</x-primary-button>
                        </div>
                    </div>
                </div>
                @endif

                {{-- Upload Form Warning — admin only --}}
                <div class="p-4 sm:p-8 bg-gray-50 shadow sm:rounded-lg">
                    <div class="max-w-xl space-y-4">
                        <h2 class="text-lg font-medium text-gray-900 mb-1">Upload Form Warning <span class="ml-1 align-middle text-[11px] font-medium bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full border border-gray-200">admin only</span></h2>
                        <p class="text-sm text-gray-600 -mt-2">Shown as an orange warning box on the customer upload form when this {{ $isEditingReader ? 'reader' : 'editor' }} is selected. Leave blank for no warning.</p>
                        <textarea id="upload_warning" name="upload_warning" rows="3"
                                  class="block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                  placeholder="e.g. Currently on reduced availability — allow extra time.">{{ old('upload_warning', $profile?->upload_warning) }}</textarea>
                        <x-input-error :messages="$errors->get('upload_warning')" class="mt-1" />
                        <div class="flex justify-end">
                            <x-primary-button>Save Warning</x-primary-button>
                        </div>
                    </div>
                </div>

                {{-- Test account toggle — admin only --}}
                @if(auth()->user()->isAdmin())
                <div class="p-4 sm:p-8 bg-gray-50 shadow sm:rounded-lg">
                    <div class="max-w-xl space-y-4">
                        <h2 class="text-lg font-medium text-gray-900">Test Account <span class="ml-1 align-middle text-[11px] font-medium bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full border border-gray-200">admin only</span></h2>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="is_test" value="0">
                            <input type="checkbox" name="is_test" value="1"
                                   {{ old('is_test', $user->is_test) ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-amber-600 focus:ring-amber-500" />
                            <span class="text-sm text-gray-700">Mark as test account</span>
                        </label>
                        <p class="text-xs text-gray-400">Test accounts are excluded from payroll and commission calculations.</p>
                        <div class="flex justify-end">
                            <x-primary-button>Save</x-primary-button>
                        </div>
                    </div>
                </div>
                @endif

            </form>{{-- end admin-form --}}

            @if (auth()->user()->isAdmin() && !$user->isAdmin())
            {{-- Role Change (admin only) --}}
            <div class="p-4 sm:p-8 bg-gray-50 shadow sm:rounded-lg">
                <div class="max-w-xl space-y-4">
                    <h2 class="text-lg font-medium text-gray-900">Role <span class="ml-1 align-middle text-[11px] font-medium bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full border border-gray-200">admin only</span></h2>
                    <p class="text-sm text-gray-600 -mt-2">
                        @if ($isEditingReader)
                            Changing to Editor moves this user out of the reader pool.
                        @else
                            Changing to Reader moves this user into the reader pool.
                        @endif
                    </p>
                    <form method="POST"
                          action="{{ $isEditingReader ? route('readers.update', $user) : route('admin.editors.update', $user) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="_action" value="role_change">
                        <select name="role" class="block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @if ($isEditingReader)
                                <option value="reader" selected>Reader</option>
                                <option value="editor">Editor</option>
                            @else
                                <option value="editor" selected>Editor</option>
                                <option value="reader">Reader</option>
                            @endif
                        </select>
                        <div class="flex justify-end mt-4">
                            <x-primary-button>Save Role</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
            @endif

            {{-- Delete (separate form — DELETE method) --}}
            @if (auth()->user()->isAdmin() && !$user->isAdmin())
            <div class="p-4 sm:p-8 bg-gray-50 shadow sm:rounded-lg">
                <div class="max-w-xl">
                    <h2 class="text-lg font-medium text-red-700 mb-1">Delete {{ $isEditingReader ? 'Reader' : 'Editor' }} <span class="ml-1 align-middle text-[11px] font-medium bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full border border-gray-200">admin only</span></h2>
                    <p class="text-sm text-gray-600 mb-4">Permanently removes this account. This cannot be undone.</p>
                    <form method="POST"
                          action="{{ $isEditingReader ? route('readers.destroy', $user) : route('admin.editors.destroy', $user) }}"
                          onsubmit="return confirm('Permanently delete {{ addslashes($user->name) }}? This cannot be undone.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="inline-flex items-center px-3 py-1.5 bg-white border border-red-300 rounded text-xs font-medium text-red-600 hover:bg-red-50 transition">
                            Delete {{ $isEditingReader ? 'Reader' : 'Editor' }}
                        </button>
                    </form>
                </div>
            </div>
            @endif

            {{-- Editor Rates + Commission Config (editors only, own forms) --}}
            @if ($user->isEditor())
            <div class="p-4 sm:p-8 bg-gray-50 shadow sm:rounded-lg">
                <div class="max-w-xl space-y-4">
                    <h2 class="text-lg font-medium text-gray-900">Editor Rates <span class="ml-1 align-middle text-[11px] font-medium bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full border border-gray-200">admin only</span></h2>
                    <p class="text-sm text-gray-600 -mt-2">Commission rate and weekly flat pay for this editor.</p>
                    <form method="POST" action="{{ route('admin.editors.updateRates', $user) }}" class="space-y-4">
                        @csrf
                        @method('PATCH')
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="editor_commission" value="Commission Rate" />
                                <div class="mt-1 flex items-center gap-1">
                                    <x-text-input id="editor_commission" name="editor_commission" type="number"
                                        class="block w-24 text-right"
                                        value="{{ old('editor_commission', $profile?->editor_commission) }}"
                                        min="0" max="100" step="0.01" placeholder="0.00" />
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
                                        value="{{ old('editor_weekly_flat', $profile?->editor_weekly_flat) }}"
                                        min="0" max="9999.99" step="0.01" placeholder="0.00" />
                                </div>
                                <x-input-error :messages="$errors->get('editor_weekly_flat')" class="mt-1" />
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <x-primary-button>Save Rates</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>

            @php
                $commissionConfig   = $profile?->productCommissionsKeyed() ?? collect();
                $allProducts        = \App\Models\EditorProductCommission::allProducts();
                $builtinIds         = array_keys(\App\Models\EditorProductCommission::PRODUCTS);
                $customProductsJson = json_decode(\App\Models\Setting::getValue('commission_custom_products', '[]'), true) ?: [];
            @endphp
            <div class="p-4 sm:p-8 bg-gray-50 shadow sm:rounded-lg">
                <div class="max-w-xl">
                    <h2 class="text-lg font-medium text-gray-900 mb-1">Commission Config <span class="ml-1 align-middle text-[11px] font-medium bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full border border-gray-200">admin only</span></h2>
                    <p class="text-sm text-gray-600 mb-4">Toggle which products earn commission and set a custom fixed amount per occurrence. Leave blank to use this editor's commission rate.</p>
                    <form method="POST" action="{{ route('admin.editors.commissions', $user) }}">
                        @csrf
                        @method('PATCH')
                        <table class="min-w-full divide-y divide-gray-100 text-sm">
                            <thead class="bg-gray-50 text-xs font-medium text-gray-500 uppercase tracking-wide">
                                <tr>
                                    <th class="px-5 py-2 text-left">Product / Service</th>
                                    <th class="px-4 py-2 text-center">Earns Commission</th>
                                    <th class="px-4 py-2 text-right">Custom Commission</th>
                                    <th class="px-2 py-2 w-8"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($allProducts as $productId => $product)
                                @php
                                    $existing  = $commissionConfig->get($productId);
                                    $enabled   = $existing ? $existing->commission_enabled : $product['commission'];
                                    $custom    = $existing?->custom_amount;
                                    $isBuiltin = in_array($productId, $builtinIds, true);
                                @endphp
                                <tr class="hover:bg-gray-50 {{ !$enabled ? 'opacity-50' : '' }}">
                                    <td class="px-5 py-2 text-gray-700">
                                        {{ $product['label'] }}
                                        <span class="ml-1 text-[10px] text-gray-400 font-mono">{{ $productId }}</span>
                                    </td>
                                    <td class="px-4 py-2 text-center">
                                        <input type="checkbox" name="commissions[{{ $productId }}][enabled]" value="1"
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
                                    <td class="px-2 py-2 text-center">
                                        @if(!$isBuiltin)
                                        <form method="POST" action="{{ route('settings.commission-products.remove') }}" class="inline"
                                              onsubmit="return confirm('Remove {{ $product['label'] }} from the commission product list for all editors?')">
                                            @csrf
                                            <input type="hidden" name="product_id" value="{{ $productId }}">
                                            <button type="submit" class="text-gray-300 hover:text-red-500 transition" title="Remove product">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </form>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <div class="flex justify-end mt-4">
                            <x-primary-button>Save Commission Config</x-primary-button>
                        </div>
                    </form>

                    {{-- Add Product --}}
                    <div class="mt-4 pt-4 border-t border-gray-200" x-data="{ open: false }">
                        <button type="button" @click="open = !open"
                                class="text-xs font-medium text-indigo-600 hover:text-indigo-800 flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Add product to commission list
                        </button>
                        <form method="POST" action="{{ route('settings.commission-products.add') }}"
                              x-show="open" x-cloak class="mt-3 flex items-end gap-2 flex-wrap">
                            @csrf
                            <div>
                                <x-input-label value="Product ID" class="text-xs" />
                                <x-text-input name="product_id" type="number" min="1" required
                                    class="mt-1 w-28 text-sm" placeholder="e.g. 12345" />
                            </div>
                            <div>
                                <x-input-label value="Label" class="text-xs" />
                                <x-text-input name="product_label" type="text" required
                                    class="mt-1 w-40 text-sm" placeholder="e.g. Proofreading" />
                            </div>
                            <label class="flex items-center gap-1.5 text-xs text-gray-600 pb-2">
                                <input type="checkbox" name="commission" value="1"
                                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                Earns commission by default
                            </label>
                            <x-primary-button class="text-xs py-1.5">Add</x-primary-button>
                        </form>
                    </div>
                </div>
            </div>
            @endif

        @endif

        </div>
    </div>
</x-app-layout>
