<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(auth()->user()->isAdminOrEditor() || auth()->user()->isReader())
            @php
                $currentPhoto = auth()->user()->isAdminOrEditor()
                    ? (auth()->user()->editorProfile?->photo ? asset('storage/' . auth()->user()->editorProfile->photo) : null)
                    : (auth()->user()->readerProfile?->photo  ? asset('storage/' . auth()->user()->readerProfile->photo)  : null);
            @endphp
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl" x-data="{ preview: null }">
                    <h2 class="text-lg font-medium text-gray-900 mb-1">Profile Photo</h2>
                    <p class="text-sm text-gray-600 mb-4">Shown as your avatar on announcements and staff views.</p>

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
                            <p class="mt-1 text-xs text-gray-400">JPG, PNG, or WebP · max 4 MB</p>
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
