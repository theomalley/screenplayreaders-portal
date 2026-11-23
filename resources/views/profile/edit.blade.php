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
            @elseif($user->isAdmin())
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-admin-notifications-form')
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

            @if($user->isAdmin())
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    <h2 class="text-lg font-medium text-gray-900 mb-1">Your Session Timeout</h2>
                    <p class="text-sm text-gray-600 mb-4">Override the site-wide session timeout ({{ (int) \App\Models\Setting::getValue('session_timeout_minutes', 120) }} minutes) for your own account only. Leave blank to use the site-wide value.</p>

                    @if (session('status') === 'session-timeout-updated')
                        <div class="mb-4 bg-green-50 border border-green-200 rounded-lg p-3 text-sm text-green-700">
                            Saved.
                        </div>
                    @endif

                    <form method="POST" action="{{ route('profile.session-timeout') }}" class="space-y-4">
                        @csrf
                        @method('PATCH')
                        <div>
                            <x-input-label for="session_timeout_minutes" value="Timeout after (minutes)" />
                            <input type="number" id="session_timeout_minutes" name="session_timeout_minutes" min="5" max="1440"
                                   value="{{ old('session_timeout_minutes', $user->session_timeout_minutes) }}"
                                   placeholder="Use site-wide default"
                                   class="mt-1 block w-48 border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                            <x-input-error :messages="$errors->get('session_timeout_minutes')" class="mt-1" />
                            <p class="mt-1 text-xs text-gray-400">Between 5 and 1440 minutes. Blank uses the site-wide default.</p>
                        </div>
                        <div class="flex justify-end">
                            <x-primary-button>Save</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
            @endif

            @if($user->isAdminOrEditor() || $user->isReader())
            @php
                $currentPhoto            = $profile?->photo             ? asset('storage/' . $profile->photo)             : null;
                $aboutPath               = $profile?->about_photo ?? $profile?->about_photo_pending ?? $profile?->photo;
                $currentAboutPhoto       = $aboutPath ? asset('storage/' . $aboutPath) : null;
                $pendingAboutPhotoSelf   = $profile?->about_photo_pending ? asset('storage/' . $profile->about_photo_pending) : null;
                $pendingPhotoSelf        = $profile?->photo_pending ? asset('storage/' . $profile->photo_pending) : null;
                $pendingBioSelf          = $profile?->bio_pending;
                $aboutPhotoRejectionNote = $profile?->about_photo_rejection_note;
                $photoRejectionNote      = $profile?->photo_rejection_note;
                $bioRejectionNote        = $profile?->bio_rejection_note;
            @endphp

            {{-- Reader Icon + About Page Photo — side by side so the two drop-zones don't eat
                 twice the vertical space of one card. --}}
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="flex flex-col sm:flex-row gap-8">
                <div class="flex-1 min-w-0">
                    <h2 class="text-lg font-medium text-gray-900 mb-1">Reader Icon</h2>
                    <p class="text-sm text-gray-600 mb-3">Your avatar in the portal — also used as your photo on the public website wherever a [sr_staff_photo] shortcode doesn't request the About Page Photo. Min 600×600 px.</p>
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
                        @elseif (session('status') === 'photo-pending')
                            <p class="mt-2 text-sm text-amber-600">Submitted for admin approval.</p>
                        @endif
                        @if ($photoRejectionNote)
                            <div class="mt-2 px-3 py-2 bg-red-50 border border-red-200 rounded text-xs text-red-700">
                                <p class="font-medium">Reader icon rejected by admin:</p>
                                <p class="mt-0.5">{{ $photoRejectionNote }}</p>
                            </div>
                        @elseif ($pendingPhotoSelf)
                            <div class="mt-2 flex items-center gap-3 px-3 py-2 bg-amber-50 border border-amber-200 rounded text-xs text-amber-700">
                                <img src="{{ $pendingPhotoSelf }}" class="w-10 h-10 rounded-full object-cover shrink-0" alt="Pending reader icon" />
                                <span>Pending admin approval.</span>
                            </div>
                        @endif
                    </form>
                </div>

                <div class="flex-1 min-w-0">
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
            </div>

            @php
                $showVideoConsultingSection = $user->isAdminOrEditor()
                    || ($user->isReader() && \App\Support\VideoConsultingAccess::enabledFor($user));
                // video_consulting_available lives on ReaderProfile regardless of role — for an
                // admin/editor, $profile is their EditorProfile (no such column), so the source
                // is $videoConsultingOverrideSource instead, same as $favoritesProfile below.
                $vcAvailabilitySource = $user->isReader() ? $profile : ($videoConsultingOverrideSource ?? null);
            @endphp

            @if ($profile)
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    <h2 class="text-lg font-medium text-gray-900 mb-1">Availability</h2>
                    <p class="text-sm text-gray-600 mb-6">Two separate settings — one for regular script assignments, one for paid video consulting calls. Setting one does not affect the other.</p>

                    <div class="pb-6 mb-6 border-b border-gray-100">
                        <h3 class="text-sm font-semibold text-gray-900 mb-1">Coverage/Editing Assignment Availability</h3>
                        <p class="text-sm text-gray-600 mb-4">Visible to the assignments team — not shown to customers. Does not affect your video consulting calls below.</p>
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

                    @if ($showVideoConsultingSection && \App\Support\VideoConsultingAccess::enabledFor($user))
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 mb-1">Video Call Availability</h3>
                        <p class="text-sm text-gray-600 mb-4">Weekly hours and blackout dates for paid video consulting calls — separate from your assignment availability above. Manage your rate and listing under Video Consulting Settings further down.</p>
                        @include('partials.video-consulting-availability', [
                            'rules' => $videoConsultingRules ?? collect(),
                            'blackouts' => $videoConsultingBlackouts ?? collect(),
                            'minLeadHours' => $videoConsultingMinLeadHours ?? 0,
                            'leadHoursBounds' => $videoConsultingLeadHoursBounds ?? \App\Models\Setting::VIDEO_CONSULTING_LEAD_HOURS_DEFAULTS,
                            'available' => (bool) ($vcAvailabilitySource?->video_consulting_available ?? true),
                            'holidayBlackouts' => $vcAvailabilitySource?->video_consulting_holiday_blackouts,
                            'updateAction' => route('video-consulting-availability.update'),
                            'blackoutStoreAction' => route('video-consulting-availability.blackouts.store'),
                            'holidaysUpdateAction' => route('video-consulting-availability.holidays.update'),
                        ])
                    </div>
                    @endif
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

            @if ($user->isReader() || $user->isAdminOrEditor())
            @php
                // Favorites always live on ReaderProfile, same as the video-consulting fields
                // above — for an admin/editor, $profile is their EditorProfile, which has no
                // favorite_genres/favorite_films columns, so it's the wrong source here.
                $favoritesProfile = $user->isReader() ? $profile : ($videoConsultingOverrideSource ?? null);
            @endphp
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    <h2 class="text-lg font-medium text-gray-900 mb-1">Favorite Genres &amp; Films</h2>
                    <p class="text-sm text-gray-600 mb-4">Shown on the Script Consulting page. Up to 5 each — leave any blank to skip.</p>
                    <form method="POST" action="{{ route('profile.favorites') }}" class="space-y-4">
                        @csrf
                        @method('PATCH')
                        <div>
                            <x-input-label value="Top 5 Favorite Genres" />
                            <div class="mt-1 space-y-2">
                                @for ($i = 0; $i < 5; $i++)
                                    <x-text-input name="favorite_genres[]" type="text" class="block w-full"
                                        value="{{ old('favorite_genres.' . $i, $favoritesProfile?->favorite_genres[$i] ?? '') }}"
                                        placeholder="Genre {{ $i + 1 }}" />
                                @endfor
                            </div>
                            <x-input-error :messages="$errors->get('favorite_genres.*')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label value="Top 5 Favorite Films/Shows" />
                            <div class="mt-1 space-y-2">
                                @for ($i = 0; $i < 5; $i++)
                                    <x-text-input name="favorite_films[]" type="text" class="block w-full"
                                        value="{{ old('favorite_films.' . $i, $favoritesProfile?->favorite_films[$i] ?? '') }}"
                                        placeholder="Film/Show {{ $i + 1 }}" />
                                @endfor
                            </div>
                            <x-input-error :messages="$errors->get('favorite_films.*')" class="mt-1" />
                        </div>
                        <div>
                            <x-primary-button>Save</x-primary-button>
                            @if (session('status') === 'favorites-updated')
                                <span class="ml-3 text-sm text-green-600">Saved.</span>
                            @endif
                        </div>
                    </form>
                </div>
            </div>
            @endif

            @if ($showVideoConsultingSection)
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    <h2 class="text-lg font-medium text-gray-900 mb-1">Video Consulting Settings</h2>
                    <p class="text-sm text-gray-600 mb-6">Opting in, your rate, and your listing for paid video consulting calls. Your call schedule and blackout dates are under Video Call Availability in the Availability section above.</p>

                    @if ($user->isAdminOrEditor())
                    {{-- Self-serve enable/disable — the only path for an admin/editor to opt into
                         taking calls themselves without another admin editing their profile for them.
                         Readers don't get this toggle: their override stays admin-only. --}}
                    <div class="pb-6 mb-6 border-b border-gray-100">
                        <h3 class="text-sm font-semibold text-gray-900 mb-3">Take Calls</h3>
                        @php
                            $videoConsultingOverride = match (($videoConsultingOverrideSource ?? null)?->video_consulting_enabled) {
                                true => 'enabled', false => 'disabled', default => 'default',
                            };
                        @endphp
                        <form method="POST" action="{{ route('profile.video-consulting-enabled') }}" class="flex items-end gap-3">
                            @csrf
                            @method('PATCH')
                            <div>
                                <label for="video_consulting_enabled" class="block text-xs text-gray-600 mb-1">Video consulting</label>
                                <select id="video_consulting_enabled" name="video_consulting_enabled"
                                        class="text-sm border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="default" @selected(old('video_consulting_enabled', $videoConsultingOverride) === 'default')>Disabled (default — no sitewide setting for admin/editor)</option>
                                    <option value="enabled" @selected(old('video_consulting_enabled', $videoConsultingOverride) === 'enabled')>Enabled</option>
                                    <option value="disabled" @selected(old('video_consulting_enabled', $videoConsultingOverride) === 'disabled')>Disabled</option>
                                </select>
                            </div>
                            <x-primary-button>Save</x-primary-button>
                            @if (session('status') === 'video-consulting-enabled-updated')
                                <span class="text-sm text-green-600 mb-2">Saved.</span>
                            @endif
                        </form>

                        {{-- Off by default — neither an admin nor an editor writes coverage
                             automatically (unlike a reader), so a customer who books a call with
                             one shouldn't be offered "count them as one of my requested readers
                             too" unless they've opted in here. --}}
                        <form method="POST" action="{{ route('profile.reader-request-eligible') }}" class="flex items-center gap-3 mt-4 pt-4 border-t border-gray-100">
                            @csrf
                            @method('PATCH')
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="hidden" name="video_consulting_reader_request_eligible" value="0">
                                <input type="checkbox" name="video_consulting_reader_request_eligible" value="1"
                                       @checked(old('video_consulting_reader_request_eligible', $videoConsultingOverrideSource?->video_consulting_reader_request_eligible ?? false))
                                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                Let customers count me as one of their requested readers for coverage
                            </label>
                            <x-primary-button>Save</x-primary-button>
                            @if (session('status') === 'reader-request-eligible-updated')
                                <span class="text-sm text-green-600">Saved.</span>
                            @endif
                        </form>
                    </div>
                    @endif

                    @if (\App\Support\VideoConsultingAccess::enabledFor($user))
                    <div class="pb-6 mb-6 border-b border-gray-100">
                        <h3 class="text-sm font-semibold text-gray-900 mb-1">Your Rate</h3>
                        <p class="text-sm text-gray-600 mb-4">What you want to be paid per video consulting call. Leave a field blank if you don't want to offer that call length. You can change these rates as often as you like. You can see your listing at <a href="https://screenplayreaders.com/screenplay-consulting/" target="_blank" rel="noopener" class="text-indigo-600 hover:underline">screenplayreaders.com/screenplay-consulting <span aria-hidden="true">&#8599;</span></a>.</p>

                        @php $vcDurations = $videoConsultingDurations ?? collect(); @endphp
                        @if ($errors->any() && $errors->hasAny($vcDurations->map(fn ($d) => "rates.{$d->id}")->all()))
                            <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-sm text-red-700 mb-4">
                                <ul class="list-disc list-inside space-y-0.5">
                                    @foreach ($vcDurations as $duration)
                                        @foreach ($errors->get("rates.{$duration->id}") as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('video-consulting-rate.update') }}" class="space-y-4">
                            @csrf
                            @method('PATCH')

                            @php
                                $myFeeOverrideEnabled = (bool) ($vcAvailabilitySource?->video_consulting_fee_override_enabled ?? false);
                                $myFeeOverrideAmount = $vcAvailabilitySource?->video_consulting_fee_override_amount;
                                $myFeeOverridePercent = $vcAvailabilitySource?->video_consulting_fee_override_percent;
                            @endphp

                            @if (auth()->user()->isAdmin())
                            {{-- Admin-only, even on their own Rate page — see the matching
                                 override on the "Video Consulting Rates (admin only)" card for
                                 other people's profiles. Submits with this same form/button. --}}
                            <div class="pb-4 mb-2 border-b border-gray-100" x-data="{ feeOverride: {{ old('fee_override_enabled', $myFeeOverrideEnabled) ? 'true' : 'false' }} }">
                                <label class="flex items-center gap-2 text-sm font-medium text-gray-900">
                                    <input type="hidden" name="fee_override_enabled" value="0">
                                    <input type="checkbox" name="fee_override_enabled" value="1" x-model="feeOverride"
                                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                    Override SR's fee for me <span class="ml-1 align-middle text-[11px] font-medium bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full border border-gray-200">admin only</span>
                                </label>
                                <p class="mt-1 text-xs text-gray-500">
                                    Replaces the per-duration fee below with a flat $ and % of your own rate,
                                    applied the same way across every call length. Set both to 0 to charge the
                                    customer exactly your own rate, with no SR fee added.
                                </p>
                                <div class="mt-2 flex items-end gap-4" x-show="feeOverride" x-cloak>
                                    <div>
                                        <x-input-label value="SR Fee ($)" />
                                        <div class="mt-1 flex items-center gap-1">
                                            <span class="text-gray-400 text-sm">$</span>
                                            <x-text-input name="fee_override_amount" type="number" class="block w-24 text-right"
                                                value="{{ old('fee_override_amount', $myFeeOverrideAmount) }}" min="0" step="0.01" />
                                        </div>
                                    </div>
                                    <div>
                                        <x-input-label value="SR Fee (%)" />
                                        <div class="mt-1 flex items-center gap-1">
                                            <x-text-input name="fee_override_percent" type="number" class="block w-24 text-right"
                                                value="{{ old('fee_override_percent', $myFeeOverridePercent) }}" min="0" max="100" step="0.01" />
                                            <span class="text-gray-400 text-sm">%</span>
                                        </div>
                                    </div>
                                </div>
                                <x-input-error :messages="$errors->get('fee_override_amount')" class="mt-1" />
                                <x-input-error :messages="$errors->get('fee_override_percent')" class="mt-1" />
                            </div>
                            @endif

                            @forelse ($vcDurations as $duration)
                                @php
                                    $myEffectiveBaseRate = $myFeeOverrideEnabled ? (float) ($myFeeOverrideAmount ?? 0) : (float) $duration->base_rate;
                                    $myEffectiveBasePercent = $myFeeOverrideEnabled ? (float) ($myFeeOverridePercent ?? 0) : (float) $duration->base_rate_percent;
                                @endphp
                                <div x-data="{
                                        rate: '{{ old('rates.' . $duration->id, ($videoConsultingMyRates ?? collect())[$duration->id] ?? '') }}',
                                        baseRate: {{ $myEffectiveBaseRate }},
                                        basePercent: {{ $myEffectiveBasePercent }},
                                        get payout() {
                                            return parseFloat(this.rate) || 0;
                                        },
                                        get cut() {
                                            return this.baseRate + Math.round((this.basePercent / 100) * this.payout);
                                        },
                                        get total() {
                                            return this.payout + this.cut;
                                        }
                                     }">
                                    <x-input-label for="rate_{{ $duration->id }}" value="{{ $duration->label }}" />
                                    <div class="mt-1 flex items-center gap-2">
                                        <span class="text-sm text-gray-500">$</span>
                                        <input type="text" id="rate_{{ $duration->id }}" name="rates[{{ $duration->id }}]"
                                               x-model="rate"
                                               class="block w-32 border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        <span class="text-xs text-gray-400">
                                            (${{ number_format($duration->min_reader_rate, 2) }} – ${{ number_format($duration->max_reader_rate, 2) }})
                                        </span>
                                    </div>
                                    <p class="mt-1 text-xs text-gray-400">
                                        You're paid exactly what you enter above — SR adds its own fee on top to set the
                                        customer's price; nothing is ever taken out of your rate. SR's fee is a flat
                                        ${{ number_format($myEffectiveBaseRate, 2) }} plus <span x-text="basePercent"></span>%
                                        of your rate. For example, at this rate
                                        <span class="font-medium text-gray-600">you are paid $<span x-text="payout"></span></span>,
                                        SR's fee is <span class="font-medium text-gray-600" x-text="'$' + cut"></span>,
                                        so the customer pays <span class="font-medium text-gray-600" x-text="'$' + total"></span> total.
                                    </p>
                                    <x-input-error :messages="$errors->get('rates.' . $duration->id)" class="mt-1" />
                                </div>
                            @empty
                                <p class="text-sm text-gray-400">No video consulting durations have been set up yet.</p>
                            @endforelse

                            <div class="flex justify-end pt-2 border-t border-gray-100">
                                <x-primary-button>Save Rate</x-primary-button>
                            </div>
                        </form>
                    </div>

                    @php
                        $vcListingSource = $user->isReader() ? $profile : ($videoConsultingOverrideSource ?? null);
                        $vcListingPending = $vcListingSource?->video_consulting_listing_pending;
                        $vcListingRejectionNote = $vcListingSource?->video_consulting_listing_rejection_note;
                    @endphp
                    <div class="pb-6 mb-6 border-b border-gray-100">
                        <h3 class="text-sm font-semibold text-gray-900 mb-1">Listing</h3>
                        <p class="text-sm text-gray-600 mb-4">A header + blurb customers see on the Script Consulting page, a note customers see on your listing (e.g. when you'll be back), and what kind of calls you're available for. The header/note are capped at 30 words and the blurb at 70 words; changes to any of these go live once an admin approves them.</p>
                        @if ($vcListingRejectionNote)
                            <div class="mb-3 px-3 py-2 bg-red-50 border border-red-200 rounded text-xs text-red-700">
                                <p class="font-medium">Listing change rejected by admin:</p>
                                <p class="mt-0.5">{{ $vcListingRejectionNote }}</p>
                            </div>
                        @elseif ($vcListingPending !== null)
                            <div class="mb-3 px-3 py-2 bg-amber-50 border border-amber-200 rounded text-xs text-amber-700">
                                Listing change pending admin approval.
                            </div>
                        @endif
                        <form method="POST" action="{{ route('profile.video-consulting-listing') }}" class="space-y-4">
                            @csrf
                            @method('PATCH')
                            <div>
                                <x-input-label for="video_consulting_blurb_header" value="Listing header (e.g. &quot;Animation specialist will take your script to next level&quot;) — up to 30 words" />
                                <x-text-input id="video_consulting_blurb_header" name="video_consulting_blurb_header" type="text" maxlength="150"
                                    class="mt-1 block w-full"
                                    value="{{ old('video_consulting_blurb_header', $vcListingSource?->video_consulting_blurb_header) }}" />
                                <x-input-error :messages="$errors->get('video_consulting_blurb_header')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label for="video_consulting_blurb" value="Listing blurb — up to 70 words" />
                                <textarea id="video_consulting_blurb" name="video_consulting_blurb" rows="4" maxlength="1000"
                                          class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                          placeholder="Tell customers a bit about yourself and what you bring to a call.">{{ old('video_consulting_blurb', $vcListingSource?->video_consulting_blurb) }}</textarea>
                                <x-input-error :messages="$errors->get('video_consulting_blurb')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label for="video_consulting_note" value="Note (shown even when you have no open times right now) — up to 30 words" />
                                <textarea id="video_consulting_note" name="video_consulting_note" rows="2" maxlength="500"
                                          class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                          placeholder="e.g. Back and taking calls again on Monday!">{{ old('video_consulting_note', $vcListingSource?->video_consulting_note) }}</textarea>
                                <x-input-error :messages="$errors->get('video_consulting_note')" class="mt-1" />
                            </div>
                            <div>
                                <label for="video_consulting_call_mode" class="block text-xs text-gray-600 mb-1">Types of calls I'm available for</label>
                                <select id="video_consulting_call_mode" name="video_consulting_call_mode"
                                        class="text-sm border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="video" @selected(old('video_consulting_call_mode', $vcListingSource?->video_consulting_call_mode ?? 'video') === 'video')>Video + Audio</option>
                                    <option value="audio" @selected(old('video_consulting_call_mode', $vcListingSource?->video_consulting_call_mode ?? 'video') === 'audio')>Audio only</option>
                                </select>
                                <x-input-error :messages="$errors->get('video_consulting_call_mode')" class="mt-1" />
                            </div>
                            <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
                                <x-primary-button>Save</x-primary-button>
                                @if (session('status') === 'video-consulting-listing-updated')
                                    <span class="text-sm text-green-600">Saved.</span>
                                @elseif (session('status') === 'video-consulting-listing-pending')
                                    <span class="text-sm text-amber-600">Saved — awaiting admin approval before it goes live.</span>
                                @endif
                            </div>
                        </form>
                    </div>
                    @endif
                </div>
            </div>
            @endif

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
                // Hoisted up here (rather than computed inline where the Video Consulting card
                // used to be) since Reader Rates/Video Consulting Rates now render ABOVE that
                // card and also depend on this for the fee override — see line ~1441.
                $videoConsultingProfileSource = $isEditingReader ? $profile : ($videoConsultingOverrideSource ?? null);
            @endphp

            {{-- Reader Icon + About Page Photo — kept near the top to match the self-edit
                 layout's order (where this is effectively the first content card). Lives
                 outside <form id="admin-form"> below; its controls still submit with that
                 form via the HTML form="admin-form" attribute, same pattern as Test Account
                 further down. Side by side so the two drop-zones don't eat twice the vertical
                 space of one card. --}}
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="flex flex-col sm:flex-row gap-8">
                <div class="flex-1 min-w-0" x-data="{ previewUrl: '{{ $currentPhotoUrl }}', hasFile: false }">
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
                    <input x-ref="photoInput" name="photo" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" form="admin-form"
                           @change="if ($event.target.files[0]) { const r=new FileReader(); r.onload=e=>{previewUrl=e.target.result;hasFile=true}; r.readAsDataURL($event.target.files[0]); }" />
                    <p class="text-xs text-gray-500 mb-2">JPG, PNG or WebP &nbsp;·&nbsp; min 600×600 px &nbsp;·&nbsp; max 8 MB</p>
                    <x-input-error :messages="$errors->get('photo')" class="mt-1" />
                    <x-primary-button form="admin-form" x-bind:disabled="!hasFile" x-bind:class="!hasFile ? 'opacity-40 cursor-not-allowed' : ''">Save Photo</x-primary-button>
                </div>

                <div class="flex-1 min-w-0" x-data="{ previewUrl: '', hasFile: false }">
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
                    <input x-ref="aboutInput" name="about_photo" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" form="admin-form"
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
                    <x-primary-button form="admin-form" x-bind:disabled="!hasFile" x-bind:class="!hasFile ? 'opacity-40 cursor-not-allowed' : ''">Save Photo</x-primary-button>
                </div>
                </div>
            </div>

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

                {{-- Availability — Coverage/Editing assignment status + Video Call Availability
                     (schedule, blackouts, holidays) combined in one card, mirroring the
                     self-edit layout's Availability card. --}}
                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <div class="max-w-xl">
                        <h2 class="text-lg font-medium text-gray-900 mb-1">Availability</h2>
                        <p class="text-sm text-gray-600 mb-6">Two separate settings — one for regular script assignments, one for paid video consulting calls. Setting one does not affect the other.</p>

                        <div class="pb-6 mb-6 border-b border-gray-100">
                            <h3 class="text-sm font-semibold text-gray-900 mb-1">Coverage/Editing Assignment Availability</h3>
                            <p class="text-sm text-gray-600 mb-4">Visible to the assignments team — not shown to customers. Does not affect video consulting calls below.</p>
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

                        @if ($user->isReader() || $user->isAdminOrEditor())
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900 mb-1">Video Call Availability</h3>
                            @include('partials.video-consulting-availability', [
                                'rules' => $videoConsultingRules ?? collect(),
                                'blackouts' => $videoConsultingBlackouts ?? collect(),
                                'minLeadHours' => $videoConsultingMinLeadHours ?? 0,
                                'leadHoursBounds' => $videoConsultingLeadHoursBounds ?? \App\Models\Setting::VIDEO_CONSULTING_LEAD_HOURS_DEFAULTS,
                                'available' => (bool) ($videoConsultingProfileSource?->video_consulting_available ?? true),
                                'holidayBlackouts' => $videoConsultingProfileSource?->video_consulting_holiday_blackouts,
                                'updateAction' => $isEditingReader
                                    ? route('readers.updateVideoConsultingAvailability', $user)
                                    : route('admin.editors.updateVideoConsultingAvailability', $user),
                                'blackoutStoreAction' => $isEditingReader
                                    ? route('readers.videoConsultingAvailability.blackouts.store', $user)
                                    : route('admin.editors.videoConsultingAvailability.blackouts.store', $user),
                                'holidaysUpdateAction' => $isEditingReader
                                    ? route('readers.videoConsultingAvailability.holidays.update', $user)
                                    : route('admin.editors.videoConsultingAvailability.holidays.update', $user),
                                // This card sits inside <form id="admin-form"> below — a nested <form> is
                                // invalid HTML and gets silently merged into the outer one by the browser,
                                // which is what broke this save. formId makes the partial render plain
                                // form="..."-tagged fields here instead, with the real (empty) <form> tags
                                // pushed onto 'late-forms' and rendered just after admin-form closes.
                                'formId' => 'vc-availability-admin',
                            ])
                        </div>
                        @endif
                    </div>
                </div>

                @if ($user->isReader() || $user->isAdminOrEditor())
                {{-- Video Consulting (admin override) — enable/disable + Reader Request
                     eligibility. Availability/schedule now lives in the combined Availability
                     card above — see App\Support\VideoConsultingAccess::enabledFor(). --}}
                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <div class="max-w-xl">
                        <h2 class="text-lg font-medium text-gray-900 mb-1">Video Consulting <span class="ml-1 align-middle text-[11px] font-medium bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full border border-gray-200">admin only</span></h2>
                        <p class="text-sm text-gray-600 mb-4">Enable/disable video consulting for this person, and whether customers can count them as one of their requested readers.</p>
                        @php
                            $videoConsultingOverride = match ($videoConsultingProfileSource?->video_consulting_enabled) {
                                true => 'enabled', false => 'disabled', default => 'default',
                            };
                        @endphp
                        <div class="mb-4">
                            <label for="video_consulting_enabled" class="block text-xs text-gray-600 mb-1">Video consulting</label>
                            <select id="video_consulting_enabled" name="video_consulting_enabled"
                                    class="text-sm border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="default" @selected(old('video_consulting_enabled', $videoConsultingOverride) === 'default')>{{ $isEditingReader ? 'Follow sitewide setting' : 'Disabled (default — no sitewide setting for admin/editor)' }}</option>
                                <option value="enabled" @selected(old('video_consulting_enabled', $videoConsultingOverride) === 'enabled')>Enabled</option>
                                <option value="disabled" @selected(old('video_consulting_enabled', $videoConsultingOverride) === 'disabled')>Disabled{{ $isEditingReader ? ' (overrides sitewide setting)' : '' }}</option>
                            </select>
                        </div>
                        @if ($user->isAdminOrEditor())
                        {{-- Off by default — see the matching self-edit toggle for why. This
                             field is part of the single outer <form id="admin-form">, saved by
                             any Save button on this page like the select above it. --}}
                        <div class="mb-4">
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="hidden" name="video_consulting_reader_request_eligible" value="0">
                                <input type="checkbox" name="video_consulting_reader_request_eligible" value="1"
                                       @checked(old('video_consulting_reader_request_eligible', $videoConsultingProfileSource?->video_consulting_reader_request_eligible ?? false))
                                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                Let customers count them as one of their requested readers for coverage
                            </label>
                        </div>
                        @endif

                        @php
                            $vcListingPendingAdmin = $videoConsultingProfileSource?->video_consulting_listing_pending;
                        @endphp
                        @if ($vcListingPendingAdmin !== null)
                            <div class="pt-4 mt-4 border-t border-gray-100">
                                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Listing (Script Consulting page)</p>
                                @include('partials.approval-panel', [
                                    'type'       => 'video-consulting-listing',
                                    'approveUrl' => route('admin.approvals.video-consulting-listing.approve', $user),
                                    'rejectUrl'  => route('admin.approvals.video-consulting-listing.reject', $user),
                                    'preview'    => '<div class="text-xs text-gray-600 w-full space-y-1">'
                                        .(!empty($vcListingPendingAdmin['video_consulting_blurb_header']) ? '<p class="font-medium">'.e($vcListingPendingAdmin['video_consulting_blurb_header']).'</p>' : '')
                                        .(!empty($vcListingPendingAdmin['video_consulting_blurb']) ? '<p>'.e($vcListingPendingAdmin['video_consulting_blurb']).'</p>' : '')
                                        .(!empty($vcListingPendingAdmin['video_consulting_note']) ? '<p class="italic">Note: '.e($vcListingPendingAdmin['video_consulting_note']).'</p>' : '')
                                        .'</div>',
                                    'label'      => 'Pending listing header/blurb/note waiting for approval:',
                                ])
                            </div>
                        @endif
                    </div>
                </div>
                @endif

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

                @if ($user->isReader() || $user->isAdminOrEditor())
                {{-- Favorite Genres & Films (admin override) --}}
                @php
                    $favoritesProfileSource = $isEditingReader ? $profile : ($videoConsultingOverrideSource ?? null);
                @endphp
                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <div class="max-w-xl space-y-4">
                        <h2 class="text-lg font-medium text-gray-900 mb-1">Favorite Genres &amp; Films</h2>
                        <p class="text-sm text-gray-600 -mt-2">Shown on the Script Consulting page. Up to 5 each — leave any blank to skip.</p>
                        <div>
                            <x-input-label value="Top 5 Favorite Genres" />
                            <div class="mt-1 space-y-2">
                                @for ($i = 0; $i < 5; $i++)
                                    <x-text-input name="favorite_genres[]" type="text" class="block w-full"
                                        value="{{ old('favorite_genres.' . $i, $favoritesProfileSource?->favorite_genres[$i] ?? '') }}"
                                        placeholder="Genre {{ $i + 1 }}" />
                                @endfor
                            </div>
                            <x-input-error :messages="$errors->get('favorite_genres.*')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label value="Top 5 Favorite Films/Shows" />
                            <div class="mt-1 space-y-2">
                                @for ($i = 0; $i < 5; $i++)
                                    <x-text-input name="favorite_films[]" type="text" class="block w-full"
                                        value="{{ old('favorite_films.' . $i, $favoritesProfileSource?->favorite_films[$i] ?? '') }}"
                                        placeholder="Film/Show {{ $i + 1 }}" />
                                @endfor
                            </div>
                            <x-input-error :messages="$errors->get('favorite_films.*')" class="mt-1" />
                        </div>
                        <div class="flex justify-end">
                            <x-primary-button>Save Favorites</x-primary-button>
                        </div>
                    </div>
                </div>
                @endif

                {{-- Reader Rates + Video Consulting Rates — sit here to match the self-edit
                     layout's order (its "Video Consulting Settings" card, which holds the
                     self-serve equivalent of these, is in this same spot: right after
                     Favorites, before Logline). Own standalone forms — nesting a real <form>
                     here would be invalid HTML since this card is already inside
                     <form id="admin-form">, so each uses the same formId/late-forms
                     cross-reference trick as the Video Call Availability partial above. --}}
                @if ($user->isReader())
                @php
                    $globalRates = \App\Models\Setting::ratesForForms();
                    $readerRateFields = [
                        'rate_script_coverage'   => ['label' => 'Script Coverage',               'default' => $globalRates['rate_sr_script_coverage']],
                        'rate_notes_only'        => ['label' => 'Notes-Only Coverage',             'default' => $globalRates['rate_sr_notes_only']],
                        'rate_deep_dive'         => ['label' => 'Advanced Script Coverage',        'default' => $globalRates['rate_sr_deep_dive']],
                        'rate_proofreading'      => ['label' => 'Proofreading',                   'default' => $globalRates['rate_sr_proofreading']],
                        'rate_oversized_121_160' => ['label' => 'Oversized (121-160 pages)',       'default' => $globalRates['rate_sr_oversized_121_160']],
                        'rate_rush'              => ['label' => 'Rush (24h turnaround)',           'default' => $globalRates['rate_sr_rush']],
                        'rate_request'           => ['label' => 'Reader Request',                 'default' => $globalRates['rate_sr_request']],
                    ];
                @endphp
                @push('late-forms')
                    <form id="reader-rates-admin" method="POST" action="{{ route('readers.updateRates', $user) }}">
                        @csrf
                        @method('PATCH')
                    </form>
                @endpush
                <div class="p-4 sm:p-8 bg-gray-50 shadow sm:rounded-lg">
                    <div class="max-w-xl space-y-4">
                        <h2 class="text-lg font-medium text-gray-900">Reader Rates <span class="ml-1 align-middle text-[11px] font-medium bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full border border-gray-200">admin only</span></h2>
                        <p class="text-sm text-gray-600 -mt-2">Custom per-reader pay rates. Leave a field blank to use the sitewide Ratebook default shown as its placeholder.</p>
                        <div class="grid grid-cols-2 gap-4">
                            @foreach ($readerRateFields as $key => $field)
                                <div>
                                    <x-input-label for="{{ $key }}" value="{{ $field['label'] }}" />
                                    <div class="mt-1 flex items-center gap-1">
                                        <span class="text-gray-400 text-sm">$</span>
                                        <x-text-input id="{{ $key }}" name="{{ $key }}" type="number" form="reader-rates-admin"
                                            class="block w-28 text-right"
                                            value="{{ old($key, $profile?->{$key}) }}"
                                            min="0" max="9999.99" step="0.01"
                                            placeholder="{{ number_format((float) $field['default'], 2) }}" />
                                    </div>
                                    <x-input-error :messages="$errors->get($key)" class="mt-1" />
                                </div>
                            @endforeach
                        </div>
                        <div class="flex justify-end">
                            <x-primary-button form="reader-rates-admin">Save Rates</x-primary-button>
                        </div>
                    </div>
                </div>
                @endif

                @if ($user->isReader() || $user->isAdminOrEditor())
                @push('late-forms')
                    <form id="vc-rates-admin" method="POST" action="{{ $isEditingReader ? route('readers.updateVideoConsultingRates', $user) : route('admin.editors.updateVideoConsultingRates', $user) }}">
                        @csrf
                        @method('PATCH')
                    </form>
                @endpush
                <div class="p-4 sm:p-8 bg-gray-50 shadow sm:rounded-lg">
                    <div class="max-w-xl space-y-4">
                        <h2 class="text-lg font-medium text-gray-900">Video Consulting Rates <span class="ml-1 align-middle text-[11px] font-medium bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full border border-gray-200">admin only</span></h2>
                        <p class="text-sm text-gray-600 -mt-2">What this {{ $isEditingReader ? 'reader' : 'person' }} is paid for a video consulting call of each length. Leave a field blank to opt them out of that call length. They need at least one rate set here (or via their own self-serve page) before they appear as bookable on the checkout upgrade or the Script Consulting page. Rates can be changed as often as needed.</p>
                        @if ($videoConsultingDurations->isEmpty())
                            <p class="text-sm text-gray-500 italic">No call-length durations are configured yet — add some under Settings first.</p>
                        @else
                        @php
                            $feeOverrideEnabled = (bool) ($videoConsultingProfileSource?->video_consulting_fee_override_enabled ?? false);
                            $feeOverrideAmount = $videoConsultingProfileSource?->video_consulting_fee_override_amount;
                            $feeOverridePercent = $videoConsultingProfileSource?->video_consulting_fee_override_percent;
                        @endphp

                        <div class="pb-4 mb-2 border-b border-gray-100" x-data="{ feeOverride: {{ old('fee_override_enabled', $feeOverrideEnabled) ? 'true' : 'false' }} }">
                            <label class="flex items-center gap-2 text-sm font-medium text-gray-900">
                                <input type="hidden" name="fee_override_enabled" value="0" form="vc-rates-admin">
                                <input type="checkbox" name="fee_override_enabled" value="1" form="vc-rates-admin" x-model="feeOverride"
                                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                Override SR's fee for this person
                            </label>
                            <p class="mt-1 text-xs text-gray-500">
                                Replaces the per-duration fee below with a flat $ and % of their own rate,
                                applied the same way across every call length. Set both to 0 to charge the
                                customer exactly this person's rate, with no SR fee added.
                            </p>
                            <div class="mt-2 flex items-end gap-4" x-show="feeOverride" x-cloak>
                                <div>
                                    <x-input-label value="SR Fee ($)" />
                                    <div class="mt-1 flex items-center gap-1">
                                        <span class="text-gray-400 text-sm">$</span>
                                        <x-text-input name="fee_override_amount" type="number" class="block w-24 text-right" form="vc-rates-admin"
                                            value="{{ old('fee_override_amount', $feeOverrideAmount) }}" min="0" step="0.01" />
                                    </div>
                                </div>
                                <div>
                                    <x-input-label value="SR Fee (%)" />
                                    <div class="mt-1 flex items-center gap-1">
                                        <x-text-input name="fee_override_percent" type="number" class="block w-24 text-right" form="vc-rates-admin"
                                            value="{{ old('fee_override_percent', $feeOverridePercent) }}" min="0" max="100" step="0.01" />
                                        <span class="text-gray-400 text-sm">%</span>
                                    </div>
                                </div>
                            </div>
                            <x-input-error :messages="$errors->get('fee_override_amount')" class="mt-1" />
                            <x-input-error :messages="$errors->get('fee_override_percent')" class="mt-1" />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            @foreach ($videoConsultingDurations as $duration)
                                @php
                                    $effectiveBaseRate = $feeOverrideEnabled ? (float) ($feeOverrideAmount ?? 0) : (float) $duration->base_rate;
                                    $effectiveBasePercent = $feeOverrideEnabled ? (float) ($feeOverridePercent ?? 0) : (float) $duration->base_rate_percent;
                                @endphp
                                <div x-data="{
                                        rate: '{{ old('vc_rates.' . $duration->id, $videoConsultingRates[$duration->id] ?? '') }}',
                                        baseRate: {{ $effectiveBaseRate }},
                                        basePercent: {{ $effectiveBasePercent }},
                                        get payout() {
                                            return parseFloat(this.rate) || 0;
                                        },
                                        get cut() {
                                            return this.baseRate + Math.round((this.basePercent / 100) * this.payout);
                                        },
                                        get total() {
                                            return this.payout + this.cut;
                                        }
                                     }">
                                    <x-input-label for="vc_rate_{{ $duration->id }}" value="{{ $duration->label }}" />
                                    <div class="mt-1 flex items-center gap-1">
                                        <span class="text-gray-400 text-sm">$</span>
                                        <x-text-input id="vc_rate_{{ $duration->id }}" name="vc_rates[{{ $duration->id }}]" type="number" form="vc-rates-admin"
                                            class="block w-28 text-right"
                                            x-model="rate"
                                            min="{{ $duration->min_reader_rate }}" max="{{ $duration->max_reader_rate }}" step="0.01"
                                            placeholder="blank = opt out" />
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500">${{ number_format((float) $duration->min_reader_rate, 2) }}–${{ number_format((float) $duration->max_reader_rate, 2) }}</p>
                                    <p class="mt-1 text-xs text-gray-500">
                                        They're paid exactly what's entered above — SR's fee (a flat ${{ number_format($effectiveBaseRate, 2) }}
                                        plus <span x-text="basePercent"></span>% of their rate) is added on top for
                                        the customer's price, never taken out of their rate. At this rate
                                        <span class="font-medium text-gray-700">they're paid $<span x-text="payout"></span></span>,
                                        SR's fee is <span class="font-medium text-gray-700" x-text="'$' + cut"></span>,
                                        customer pays <span class="font-medium text-gray-700" x-text="'$' + total"></span> total
                                    </p>
                                    <x-input-error :messages="$errors->get('vc_rates.' . $duration->id)" class="mt-1" />
                                </div>
                            @endforeach
                        </div>
                        <div class="flex justify-end">
                            <x-primary-button form="vc-rates-admin">Save Video Consulting Rates</x-primary-button>
                        </div>
                        @endif
                    </div>
                </div>
                @endif

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
                                <x-input-label for="max_concurrent_assignments" value="Max Concurrent Assignments (override)" />
                                <x-text-input id="max_concurrent_assignments" name="max_concurrent_assignments" type="number"
                                    class="mt-1 block w-full"
                                    value="{{ old('max_concurrent_assignments', $profile?->max_concurrent_assignments) }}"
                                    placeholder="Default: {{ \App\Models\ReaderProfile::globalDefaultCap() }}"
                                    min="0" max="20" />
                                <x-input-error :messages="$errors->get('max_concurrent_assignments')" class="mt-1" />
                                <p class="mt-1 text-xs text-gray-500">Leave blank to use the sitewide default (Settings &gt; Assignments &amp; Coverage).</p>
                                <label class="flex items-center gap-2 mt-2 cursor-pointer select-none">
                                    <input type="checkbox" name="requests_bypass_capacity" value="1"
                                        {{ old('requests_bypass_capacity', $profile?->requests_bypass_capacity) ? 'checked' : '' }}
                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                    <span class="text-sm text-gray-600">Requests don't count toward capacity</span>
                                </label>
                                <label class="flex items-center gap-2 mt-2 cursor-pointer select-none">
                                    <input type="checkbox" name="watermark_enabled" value="1"
                                        {{ old('watermark_enabled', $profile?->watermark_enabled ?? true) ? 'checked' : '' }}
                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                    <span class="text-sm text-gray-600">Watermark viewed/downloaded scripts</span>
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
                            @foreach (\App\Models\Tier::ordered()->get() as $tierOption)
                                <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer select-none">
                                    <input type="checkbox" name="tiers[]" value="{{ $tierOption->id }}"
                                           {{ $profile?->tiers->contains('id', $tierOption->id) ? 'checked' : '' }}
                                           class="rounded border-gray-300 {{ $tierOption->is_onboarding ? 'text-amber-600 focus:ring-amber-500' : 'text-indigo-600 focus:ring-indigo-500' }} shadow-sm" />
                                    {{ $tierOption->name }}{{ $tierOption->is_onboarding ? ' (Onboarding)' : '' }}
                                </label>
                            @endforeach
                        </div>
                        <p class="text-xs text-gray-400 -mt-1">Manage tiers themselves under Settings &gt; Tiers. Onboarding readers can only accept the onboarding sandbox assignment.</p>
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
                            <label class="flex items-center gap-2 mt-2 cursor-pointer select-none">
                                <input type="checkbox" name="is_1099" value="1"
                                    {{ old('is_1099', $profile?->is_1099) ? 'checked' : '' }}
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                <span class="text-sm text-gray-600">1099 contractor</span>
                            </label>
                        </div>
                        <div class="flex justify-end">
                            <x-primary-button>Save Pay</x-primary-button>
                        </div>
                    </div>
                </div>
                @endif

                @if ($isEditingReader)
                {{-- Notifications (read-only) — admin/editor view of what the reader has opted into --}}
                <div class="p-4 sm:p-8 bg-gray-50 shadow sm:rounded-lg">
                    <div class="max-w-xl space-y-4">
                        <h2 class="text-lg font-medium text-gray-900">Notifications <span class="ml-1 align-middle text-[11px] font-medium bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full border border-gray-200">read only</span></h2>
                        <p class="text-sm text-gray-600 -mt-2">What this reader has opted into for new-assignment alerts. Readers manage this themselves under their own Profile.</p>

                        <div>
                            <label class="flex items-center gap-2">
                                <input type="checkbox" disabled {{ $profile?->sms_notifications ? 'checked' : '' }}
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm disabled:opacity-100" />
                                <span class="text-sm font-medium text-gray-700">SMS notifications</span>
                            </label>
                            @if ($profile?->sms_notifications)
                            <ul class="mt-2 ml-7 space-y-1 text-sm text-gray-600">
                                <li>{{ $profile->sms_notify_any ? '✓' : '—' }} Any new assignment</li>
                                <li>{{ ($profile->sms_notify_any || $profile->sms_notify_rush) ? '✓' : '—' }} Rush assignments</li>
                                <li>{{ ($profile->sms_notify_any || $profile->sms_notify_requests) ? '✓' : '—' }} Reader requests</li>
                                <li>{{ $profile->sms_notify_followup ? '✓' : '—' }} Followup questions</li>
                            </ul>
                            @endif
                        </div>

                        <div>
                            <label class="flex items-center gap-2">
                                <input type="checkbox" disabled {{ $profile?->email_notifications ? 'checked' : '' }}
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm disabled:opacity-100" />
                                <span class="text-sm font-medium text-gray-700">Email notifications</span>
                            </label>
                            @if ($profile?->email_notifications)
                            <ul class="mt-2 ml-7 space-y-1 text-sm text-gray-600">
                                <li>{{ $profile->email_notify_any ? '✓' : '—' }} Any new assignment</li>
                                <li>{{ ($profile->email_notify_any || $profile->email_notify_rush) ? '✓' : '—' }} Rush assignments</li>
                                <li>{{ ($profile->email_notify_any || $profile->email_notify_requests) ? '✓' : '—' }} Reader requests</li>
                                <li>✓ Followup questions <span class="text-gray-400">(always on)</span></li>
                                <li>✓ Coverage fails QC <span class="text-gray-400">(always on)</span></li>
                            </ul>
                            @endif
                        </div>

                        <label class="flex items-center gap-2 pt-2 border-t border-gray-200">
                            <input type="checkbox" disabled {{ $profile?->notify_only_if_under_capacity ? 'checked' : '' }}
                                class="rounded border-gray-300 text-indigo-600 shadow-sm disabled:opacity-100" />
                            <span class="text-sm text-gray-700">Only notify when under capacity (max {{ $profile?->effectiveCap() ?? \App\Models\ReaderProfile::globalDefaultCap() }})</span>
                        </label>
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

            </form>{{-- end admin-form --}}

            {{-- Real (empty-bodied) <form> tags for any card above that had to render its
                 visible fields nested inside admin-form via form="..." cross-references
                 instead of its own <form> wrapper — see partials/video-consulting-availability. --}}
            @stack('late-forms')

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
                            <div>
                                <x-input-label for="video_consulting_commission_cap" value="Max Commission per Video Call" />
                                <div class="mt-1 flex items-center gap-1">
                                    <span class="text-gray-400 text-sm">$</span>
                                    <x-text-input id="video_consulting_commission_cap" name="video_consulting_commission_cap" type="number"
                                        class="block w-28 text-right"
                                        value="{{ old('video_consulting_commission_cap', $profile?->video_consulting_commission_cap) }}"
                                        min="0" max="9999.99" step="0.01" placeholder="blank = no cap" />
                                </div>
                                <p class="mt-1 text-xs text-gray-500">Caps commission earned on a single video consulting booking, regardless of the Commission Rate above. Leave blank for no cap.</p>
                                <x-input-error :messages="$errors->get('video_consulting_commission_cap')" class="mt-1" />
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

            {{-- Account — moved down to sit just above Delete, matching the self-edit layout's
                 order (Account is the last editable card there too, right before Delete).
                 Lives outside <form id="admin-form"> now; its controls still submit with that
                 form via the HTML form="admin-form" attribute, same pattern as Test Account
                 below. --}}
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl space-y-4">
                    <h2 class="text-lg font-medium text-gray-900">Account</h2>
                    <div>
                        <x-input-label for="email" value="Email" />
                        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" form="admin-form"
                            value="{{ old('email', $user->email) }}" required />
                        <x-input-error :messages="$errors->get('email')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="password" value="New Password" />
                        <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" form="admin-form"
                            placeholder="Leave blank to keep current password" />
                        <x-input-error :messages="$errors->get('password')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="password_confirmation" value="Confirm New Password" />
                        <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" form="admin-form"
                            placeholder="Leave blank to keep current password" />
                    </div>
                    <div class="flex justify-end">
                        <x-primary-button form="admin-form">Save Account</x-primary-button>
                    </div>
                </div>
            </div>

            {{-- Test account toggle — admin only. Lives outside <form id="admin-form"> now (moved
                 to sit just above Delete) but its controls still submit with that form via the
                 HTML form="admin-form" attribute, since update() still expects is_test alongside
                 the form's other required fields (name/email/availability/etc). --}}
            @if(auth()->user()->isAdmin())
            <div class="p-4 sm:p-8 bg-gray-50 shadow sm:rounded-lg">
                <div class="max-w-xl space-y-4">
                    <h2 class="text-lg font-medium text-gray-900">Test Account <span class="ml-1 align-middle text-[11px] font-medium bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full border border-gray-200">admin only</span></h2>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="hidden" name="is_test" value="0" form="admin-form">
                        <input type="checkbox" name="is_test" value="1" form="admin-form"
                               {{ old('is_test', $user->is_test) ? 'checked' : '' }}
                               class="rounded border-gray-300 text-amber-600 focus:ring-amber-500" />
                        <span class="text-sm text-gray-700">Mark as test account</span>
                    </label>
                    <p class="text-xs text-gray-400">Test accounts are excluded from payroll and commission calculations.</p>
                    <div class="flex justify-end">
                        <x-primary-button form="admin-form">Save</x-primary-button>
                    </div>
                </div>
            </div>
            @endif

            {{-- Delete — kept as the very last card on the page (separate form — DELETE method) --}}
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

        @endif

        </div>
    </div>
</x-app-layout>
