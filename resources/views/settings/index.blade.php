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

            {{-- Favicon --}}
            <div x-data="{ preview: null, existing: @js($faviconUrl) }"
                 class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-800 mb-1">Favicon</h3>
                <p class="text-xs text-gray-500 mb-4">Browser tab icon for the portal. PNG recommended (32×32 or 64×64).</p>

                <div class="mb-5">
                    <img x-show="preview || existing"
                         :src="preview || existing"
                         alt="Favicon preview"
                         class="w-8 h-8 object-contain rounded border border-gray-200 bg-gray-50">
                    <p x-show="!preview && !existing" class="text-sm text-gray-400">No favicon uploaded yet — browser will use its default.</p>
                </div>

                <form method="POST" action="{{ route('settings.favicon') }}" enctype="multipart/form-data"
                      class="flex items-end gap-3">
                    @csrf
                    <div class="flex-1">
                        <x-input-label for="favicon" :value="__('Choose file')" />
                        <input id="favicon" name="favicon" type="file" accept="image/png,image/x-icon,image/svg+xml,image/webp"
                               @change="const f = $event.target.files[0]; if (f) { const r = new FileReader(); r.onload = e => { preview = e.target.result }; r.readAsDataURL(f) }"
                               class="mt-1 block w-full text-sm text-gray-500
                                      file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0
                                      file:text-sm file:font-medium file:bg-gray-100 file:text-gray-700
                                      hover:file:bg-gray-200 cursor-pointer">
                        <x-input-error class="mt-1" :messages="$errors->get('favicon')" />
                        <p class="mt-1 text-xs text-gray-400">PNG, ICO, SVG, or WebP · max 512 KB</p>
                    </div>
                    <x-primary-button>Upload</x-primary-button>
                </form>
            </div>

            {{-- Reader Capacity Override --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-800 mb-1">Reader Capacity Override</h3>
                <p class="text-xs text-gray-500 mb-4">
                    Set a single concurrent-assignment cap that applies to <strong>all readers</strong>, overriding their individual limits.
                    Leave blank (or set to 0) to use each reader's own setting.
                </p>

                @if ($capacityOverride > 0)
                    <div class="mb-4 inline-flex items-center gap-2 px-3 py-1.5 bg-amber-50 border border-amber-200 rounded text-xs text-amber-800">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        Override active — all readers capped at <strong class="mx-0.5">{{ $capacityOverride }}</strong> assignment{{ $capacityOverride === 1 ? '' : 's' }}.
                    </div>
                @endif

                <form method="POST" action="{{ route('settings.capacity-override') }}" class="flex items-end gap-3">
                    @csrf
                    @method('PATCH')
                    <div>
                        <x-input-label for="capacity_override" value="Max concurrent assignments (all readers)" />
                        <input type="number" id="capacity_override" name="capacity_override"
                               min="0" max="99" step="1"
                               value="{{ $capacityOverride > 0 ? $capacityOverride : '' }}"
                               placeholder="e.g. 3"
                               class="mt-1 block w-28 border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                        <x-input-error class="mt-1" :messages="$errors->get('capacity_override')" />
                    </div>
                    <x-primary-button>Save</x-primary-button>
                    @if ($capacityOverride > 0)
                        <button type="submit" name="capacity_override" value="0"
                                class="inline-flex items-center px-3 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-50 transition">
                            Clear override
                        </button>
                    @endif
                </form>
            </div>

        </div>
    </div>
</x-app-layout>
