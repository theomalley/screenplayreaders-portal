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
                $currentPhoto = auth()->user()->isAdminOrEditor()
                    ? (auth()->user()->editorProfile?->photo ? asset('storage/' . auth()->user()->editorProfile->photo) : null)
                    : (auth()->user()->readerProfile?->photo  ? asset('storage/' . auth()->user()->readerProfile->photo)  : null);
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
                                @endif
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @php
                        $currentBio = auth()->user()->isAdminOrEditor()
                            ? auth()->user()->editorProfile?->bio
                            : auth()->user()->readerProfile?->bio;
                    @endphp
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
