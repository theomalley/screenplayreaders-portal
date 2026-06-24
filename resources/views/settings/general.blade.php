<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Settings</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">

            @include('settings._nav')

            <div class="space-y-6">

            @if(session('success'))
                <div class="bg-green-50 border border-green-200 rounded-lg px-4 py-3 text-sm text-green-700">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Portal Theme --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-800 mb-1">Portal Theme</h3>
                <p class="text-xs text-gray-500 mb-4">Sets the colour scheme for the navigation bar and accent colours throughout the portal. <strong class="text-gray-600">This changes the theme for all users.</strong></p>
                <div class="flex flex-wrap gap-3">
                    @foreach([
                        'default'  => ['label' => 'Default',  'nav' => '#2b4158', 'border_nav' => '#1e3047', 'body' => '#f7f4e6', 'accent' => '#3c9590'],
                        'midnight' => ['label' => 'Midnight', 'nav' => '#16213e', 'border_nav' => '#0f3460', 'body' => '#1c1c2e', 'accent' => '#e94560'],
                        'forest'   => ['label' => 'Forest',   'nav' => '#1e3a2f', 'border_nav' => '#2d5440', 'body' => '#f0f4f0', 'accent' => '#4caf50'],
                        'warm'     => ['label' => 'Warm',     'nav' => '#5c3317', 'border_nav' => '#7a4520', 'body' => '#faf6f1', 'accent' => '#d4793b'],
                        'ocean'    => ['label' => 'Ocean',    'nav' => '#1a3a5c', 'border_nav' => '#0f2840', 'body' => '#eef3f8', 'accent' => '#0ea5e9'],
                        'slate'    => ['label' => 'Slate',    'nav' => '#334155', 'border_nav' => '#1e293b', 'body' => '#f8f7f5', 'accent' => '#f59e0b'],
                        'rose'     => ['label' => 'Rose',     'nav' => '#6b2040', 'border_nav' => '#4a1530', 'body' => '#fff5f7', 'accent' => '#e11d48'],
                        'dusk'     => ['label' => 'Dusk',     'nav' => '#2d1f5e', 'border_nav' => '#1f1342', 'body' => '#f3f0ff', 'accent' => '#8b5cf6'],
                        'crimson'  => ['label' => 'Crimson',  'nav' => '#7f1d1d', 'border_nav' => '#5a1212', 'body' => '#fef9f0', 'accent' => '#b45309'],
                        'steel'    => ['label' => 'Steel',    'nav' => '#374151', 'border_nav' => '#1f2937', 'body' => '#f9fafb', 'accent' => '#3b82f6'],
                        'teal'     => ['label' => 'Teal',     'nav' => '#134e4a', 'border_nav' => '#0f3d3a', 'body' => '#f0fdfb', 'accent' => '#0d9488'],
                        'mocha'    => ['label' => 'Mocha',    'nav' => '#4a2c1a', 'border_nav' => '#3a1f0e', 'body' => '#fdf8f3', 'accent' => '#c2853a'],
                        'arctic'   => ['label' => 'Arctic',   'nav' => '#f0f4f8', 'border_nav' => '#dde3ea', 'body' => '#ffffff', 'accent' => '#4f46e5'],
                        'noir'     => ['label' => 'Noir',     'nav' => '#0a0a0a', 'border_nav' => '#1a1a1a', 'body' => '#121212', 'accent' => '#fbbf24'],
                    ] as $slug => $theme)
                        <form method="POST" action="{{ route('settings.theme') }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="portal_theme" value="{{ $slug }}">
                            <button type="submit"
                                    class="group text-left rounded-lg overflow-hidden border-2 transition {{ $portalTheme === $slug ? 'border-indigo-500 shadow-md' : 'border-gray-200 hover:border-gray-400' }}">
                                <div class="w-28">
                                    <div class="h-7 flex items-center px-2 gap-1.5"
                                         style="background-color: {{ $theme['nav'] }}; border-bottom: 1px solid {{ $theme['border_nav'] }}">
                                        <div class="w-2 h-2 rounded-full" style="background-color: {{ $theme['accent'] }}"></div>
                                        <div class="h-1.5 rounded w-8 opacity-50" style="background-color: {{ $theme['nav'] === '#ffffff' ? '#9ca3af' : '#ffffff' }}"></div>
                                    </div>
                                    <div class="h-10 flex flex-col justify-center px-2 gap-1"
                                         style="background-color: {{ $theme['body'] }}">
                                        <div class="h-1.5 rounded w-14 bg-gray-300 opacity-60"></div>
                                        <div class="h-1.5 rounded w-10 bg-gray-300 opacity-40"></div>
                                    </div>
                                </div>
                                <div class="px-2 py-1.5 bg-white border-t border-gray-100">
                                    <p class="text-xs font-medium text-gray-700">{{ $theme['label'] }}</p>
                                    @if($portalTheme === $slug)
                                        <p class="text-[10px] text-indigo-500">Active</p>
                                    @endif
                                </div>
                            </button>
                        </form>
                    @endforeach
                </div>
            </div>

            @if($isAdmin)

            {{-- App Timezone --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-800 mb-1">App Timezone</h3>
                <p class="text-xs text-gray-500 mb-4">Controls how assignment dates are displayed and parsed on the Edit Assignment form. Set this to the timezone your team operates in.</p>
                <form method="POST" action="{{ route('settings.timezone') }}" class="flex items-center gap-3">
                    @csrf
                    @method('PATCH')
                    <x-timezone-select name="app_timezone" :selected="$appTimezone" class="w-72" />
                    <x-primary-button type="submit">Save</x-primary-button>
                </form>
            </div>

            {{-- Navigation Logo --}}
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

            {{-- Login Screen Logo --}}
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

            {{-- Session Timeout --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-800 mb-1">Session Timeout</h3>
                <p class="text-xs text-gray-500 mb-4">
                    How long a user can be idle before being automatically logged out. Minimum 5 minutes, maximum 1440 (24 hours).
                </p>
                <form method="POST" action="{{ route('settings.session-timeout') }}" class="flex items-end gap-3">
                    @csrf
                    @method('PATCH')
                    <div>
                        <x-input-label for="session_timeout_minutes" value="Timeout (minutes)" />
                        <input type="number" id="session_timeout_minutes" name="session_timeout_minutes"
                               min="5" max="1440" step="1"
                               value="{{ $sessionTimeout }}"
                               class="mt-1 block w-28 border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                        <x-input-error class="mt-1" :messages="$errors->get('session_timeout_minutes')" />
                    </div>
                    <x-primary-button>Save</x-primary-button>
                </form>
            </div>

            {{-- Quick-Login Link --}}
            @php $qlToken = \App\Http\Controllers\QuickLoginController::currentToken(); @endphp
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
                <div>
                    <h3 class="text-sm font-semibold text-gray-800 mb-1">Admin Quick-Login Link</h3>
                    <p class="text-xs text-gray-500">
                        A tokenized URL you can bookmark on your phone or browser to log straight in as admin — no password prompt.
                        Treat it like a password: keep it private and revoke it if it's ever compromised.
                    </p>
                </div>

                @if($qlToken)
                    @php
                        $qlUrl      = url('/ql/' . $qlToken);
                        $qlLanding  = \App\Models\Setting::getValue('admin_quick_login_landing', 'assignments.index');
                        $qlOptions  = \App\Http\Controllers\QuickLoginController::LANDING_OPTIONS;
                    @endphp

                    <form method="POST" action="{{ route('quick-login.landing') }}" class="flex items-center gap-3">
                        @csrf
                        <label class="text-xs font-medium text-gray-600 whitespace-nowrap">Lands on</label>
                        <select name="landing" onchange="this.form.submit()"
                                class="text-sm border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                            @foreach($qlOptions as $route => $label)
                                <option value="{{ $route }}" @selected($qlLanding === $route)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </form>

                    <div x-data="{ copied: false }">
                        <div class="flex items-center gap-2">
                            <input type="text" readonly value="{{ $qlUrl }}"
                                   class="flex-1 text-xs font-mono border border-gray-300 rounded-md px-3 py-2 bg-gray-50 text-gray-700 focus:outline-none select-all"
                                   onclick="this.select()">
                            <button type="button"
                                    @click="navigator.clipboard.writeText('{{ $qlUrl }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                    class="px-3 py-2 text-xs font-medium text-indigo-700 bg-indigo-50 border border-indigo-200 hover:bg-indigo-100 rounded-md transition-colors whitespace-nowrap">
                                <span x-show="!copied">Copy URL</span>
                                <span x-show="copied" x-cloak class="text-green-700">Copied</span>
                            </button>
                        </div>
                        <p class="mt-1.5 text-[10px] text-gray-400">Bookmark this URL. Clicking it logs you in directly. Rate-limited to 10 attempts/min per IP.</p>
                    </div>
                    <div class="flex gap-3">
                        <form method="POST" action="{{ route('quick-login.generate') }}"
                              onsubmit="return confirm('Regenerate? Your existing bookmark will stop working.')">
                            @csrf
                            <button type="submit"
                                    class="px-3 py-1.5 text-xs font-medium text-gray-600 bg-white border border-gray-300 hover:bg-gray-50 rounded-md transition-colors">
                                Regenerate
                            </button>
                        </form>
                        <form method="POST" action="{{ route('quick-login.revoke') }}"
                              onsubmit="return confirm('Revoke this link? Any saved bookmarks will stop working immediately.')">
                            @csrf @method('DELETE')
                            <button type="submit"
                                    class="px-3 py-1.5 text-xs font-medium text-red-600 bg-white border border-red-200 hover:bg-red-50 rounded-md transition-colors">
                                Revoke
                            </button>
                        </form>
                    </div>
                @else
                    <form method="POST" action="{{ route('quick-login.generate') }}">
                        @csrf
                        <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-md transition-colors">
                            Generate Quick-Login Link
                        </button>
                    </form>
                @endif
            </div>

            {{-- Last Seen Reset --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
                <div>
                    <h3 class="text-sm font-semibold text-gray-800 mb-1">Last Seen Times</h3>
                    <p class="text-xs text-gray-500">Clear the "last seen" timestamps that determine online/offline status. Useful when testing with real accounts.</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <form method="POST" action="{{ route('settings.reset-last-seen-all') }}"
                          onsubmit="return confirm('Clear last-seen time for ALL users?')">
                        @csrf
                        <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-md transition-colors">
                            Reset All Last Seen
                        </button>
                    </form>
                    <form method="POST" action="{{ route('settings.reset-last-seen-me') }}">
                        @csrf
                        <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 rounded-md transition-colors">
                            Reset Mine Only
                        </button>
                    </form>
                </div>
            </div>

            @endif
            </div>
        </div>
    </div>
</x-app-layout>
