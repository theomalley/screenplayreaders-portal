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

            {{-- Permissions --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-800 mb-1">Permissions</h3>
                <p class="text-xs text-gray-500 mb-4">Controls which roles can access each feature. Admin is always granted all permissions.</p>

                <form method="POST" action="{{ route('admin.permissions.update') }}">
                    @csrf
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm border border-gray-200 rounded-lg overflow-hidden">
                            <thead class="bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Feature</th>
                                    @foreach(\App\Support\Permission::ROLES as $role)
                                        <th class="px-4 py-2.5 text-center text-xs font-semibold uppercase tracking-wide whitespace-nowrap
                                            {{ $role === 'admin' ? 'text-indigo-400' : 'text-gray-500' }}">
                                            {{ ucfirst($role) }}
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach(\App\Support\Permission::FEATURES as $feature => $label)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-2.5 text-gray-700">{{ $label }}</td>
                                        @foreach(\App\Support\Permission::ROLES as $role)
                                            @php
                                                $checked     = $permissionsGrid[$feature][$role] ?? false;
                                                $isAdminRole = $role === 'admin';
                                                $inputName   = 'perm_' . $role . '_' . str_replace('.', '_', $feature);
                                            @endphp
                                            <td class="px-4 py-2.5 text-center">
                                                @if($isAdminRole)
                                                    <span title="Admin always has access"
                                                          class="inline-flex items-center justify-center w-5 h-5 rounded bg-indigo-100 text-indigo-500">
                                                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                        </svg>
                                                    </span>
                                                @else
                                                    <input type="checkbox"
                                                           name="{{ $inputName }}"
                                                           {{ $checked ? 'checked' : '' }}
                                                           class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 cursor-pointer">
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="flex justify-end pt-3">
                        <x-primary-button>Save Permissions</x-primary-button>
                    </div>
                </form>
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
