<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Settings</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="bg-green-50 border border-green-200 rounded-lg px-4 py-3 text-sm text-green-700">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Nav logo --}}
            <div x-data="{ preview: null, existing: @js($logoUrl) }"
                 class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-800 mb-1">Navigation Logo</h3>
                <p class="text-xs text-gray-500 mb-4">Appears in the top-left of the portal navigation bar.</p>

                <div class="mb-5">
                    <img x-show="preview || existing"
                         :src="preview || existing"
                         alt="Navigation logo preview"
                         class="h-14 w-auto object-contain rounded border border-gray-200 p-2 bg-gray-50">
                    <div x-show="!preview && !existing" class="flex items-center gap-3 text-sm text-gray-400">
                        <x-application-logo class="h-10 w-10 fill-current text-gray-300" />
                        <span>Default logo — no custom logo uploaded yet.</span>
                    </div>
                </div>

                <form method="POST" action="{{ route('settings.logo') }}" enctype="multipart/form-data"
                      class="flex items-end gap-3">
                    @csrf
                    <div class="flex-1">
                        <x-input-label for="logo" :value="__('Choose file')" />
                        <input id="logo" name="logo" type="file" accept="image/*"
                               @change="const f = $event.target.files[0]; if (f) { const r = new FileReader(); r.onload = e => { preview = e.target.result }; r.readAsDataURL(f) }"
                               class="mt-1 block w-full text-sm text-gray-500
                                      file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0
                                      file:text-sm file:font-medium file:bg-gray-100 file:text-gray-700
                                      hover:file:bg-gray-200 cursor-pointer">
                        <x-input-error class="mt-1" :messages="$errors->get('logo')" />
                        <p class="mt-1 text-xs text-gray-400">PNG, JPG, SVG, or WebP · max 4 MB</p>
                    </div>
                    <x-primary-button>Upload</x-primary-button>
                </form>
            </div>

            {{-- Login logo --}}
            <div x-data="{ preview: null, existing: @js($loginLogoUrl) }"
                 class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-800 mb-1">Login Screen Logo</h3>
                <p class="text-xs text-gray-500 mb-4">Appears above the login form on the sign-in page.</p>

                <div class="mb-5">
                    <img x-show="preview || existing"
                         :src="preview || existing"
                         alt="Login logo preview"
                         class="h-20 w-auto object-contain rounded border border-gray-200 p-2 bg-gray-50">
                    <div x-show="!preview && !existing" class="flex items-center gap-3 text-sm text-gray-400">
                        <x-application-logo class="h-10 w-10 fill-current text-gray-300" />
                        <span>Default logo — no custom login logo uploaded yet.</span>
                    </div>
                </div>

                <form method="POST" action="{{ route('settings.login-logo') }}" enctype="multipart/form-data"
                      class="flex items-end gap-3">
                    @csrf
                    <div class="flex-1">
                        <x-input-label for="login_logo" :value="__('Choose file')" />
                        <input id="login_logo" name="login_logo" type="file" accept="image/*"
                               @change="const f = $event.target.files[0]; if (f) { const r = new FileReader(); r.onload = e => { preview = e.target.result }; r.readAsDataURL(f) }"
                               class="mt-1 block w-full text-sm text-gray-500
                                      file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0
                                      file:text-sm file:font-medium file:bg-gray-100 file:text-gray-700
                                      hover:file:bg-gray-200 cursor-pointer">
                        <x-input-error class="mt-1" :messages="$errors->get('login_logo')" />
                        <p class="mt-1 text-xs text-gray-400">PNG, JPG, SVG, or WebP · max 4 MB</p>
                    </div>
                    <x-primary-button>Upload</x-primary-button>
                </form>
            </div>

        </div>
    </div>
</x-app-layout>
