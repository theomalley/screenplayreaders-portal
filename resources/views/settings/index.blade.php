<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Settings</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="bg-green-50 border border-green-200 rounded-lg px-4 py-3 text-sm text-green-700">
                    {{ session('success') }}
                </div>
            @endif

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

            {{-- App Timezone --}}
            @if($isAdmin)
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
            @endif

            {{-- Pay Period --}}
            @if($isAdmin)
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-800 mb-1">Pay Period</h3>
                <p class="text-xs text-gray-500 mb-4">Defines when each pay period opens and closes. Used to group reader earnings and payout calculations. All times are in the app timezone ({{ $appTimezone }}).</p>
                <form method="POST" action="{{ route('settings.pay-period') }}">
                    @csrf
                    @method('PATCH')
                    @php
                        $dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                    @endphp
                    <div class="space-y-3">
                        <div class="flex items-center gap-3">
                            <span class="w-12 text-xs font-medium text-gray-500 shrink-0">Start</span>
                            <select name="period_start_day"
                                    class="text-sm border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                @foreach($dayNames as $i => $name)
                                    <option value="{{ $i }}" @selected($payPeriod['start_day'] === $i)>{{ $name }}</option>
                                @endforeach
                            </select>
                            <input type="time" name="period_start_time" value="{{ $payPeriod['start_time'] }}" step="60"
                                   class="text-sm border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="w-12 text-xs font-medium text-gray-500 shrink-0">End</span>
                            <select name="period_end_day"
                                    class="text-sm border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                @foreach($dayNames as $i => $name)
                                    <option value="{{ $i }}" @selected($payPeriod['end_day'] === $i)>{{ $name }}</option>
                                @endforeach
                            </select>
                            <input type="time" name="period_end_time" value="{{ $payPeriod['end_time'] }}" step="60"
                                   class="text-sm border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>
                    <div class="mt-4">
                        <x-primary-button type="submit">Save</x-primary-button>
                    </div>
                </form>
            </div>
            @endif

            {{-- Payout Schedule (admin only) --}}
            @if($isAdmin)
            @php
                $payoutDays = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
            @endphp
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-1">
                    <h3 class="text-sm font-semibold text-gray-800">Payout Schedule</h3>
                    <span class="text-sm text-gray-500">
                        Next payout:
                        @if ($payoutSchedule['override'])
                            <span class="font-semibold text-amber-600">{{ $nextPayout->format('D M j') }} at {{ $nextPayout->format('g:i A') }} PT</span>
                            <span class="ml-1 text-xs text-amber-500">(override active)</span>
                        @else
                            <span class="font-semibold text-gray-700">{{ $nextPayout->format('D M j') }} at {{ $nextPayout->format('g:i A') }} PT</span>
                        @endif
                    </span>
                </div>
                <p class="text-xs text-gray-500 mb-4">Controls how often and when reader and editor payouts go out.</p>

                <form method="POST" action="{{ route('payroll.schedule.update') }}" class="flex flex-wrap items-end gap-4">
                    @csrf @method('PATCH')

                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Frequency</label>
                        <div class="flex gap-4 items-center h-9">
                            <label class="flex items-center gap-1.5 text-sm text-gray-700 cursor-pointer">
                                <input type="radio" name="frequency" value="weekly"
                                    {{ $payoutSchedule['frequency'] === 'weekly' ? 'checked' : '' }}
                                    class="text-indigo-600 focus:ring-indigo-500">
                                Weekly
                            </label>
                            <label class="flex items-center gap-1.5 text-sm text-gray-700 cursor-pointer">
                                <input type="radio" name="frequency" value="biweekly"
                                    {{ $payoutSchedule['frequency'] === 'biweekly' ? 'checked' : '' }}
                                    class="text-indigo-600 focus:ring-indigo-500">
                                Biweekly
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Payout Day</label>
                        <select name="day" class="text-sm border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 h-9">
                            @foreach ($payoutDays as $i => $dayName)
                                <option value="{{ $i }}" {{ $payoutSchedule['day'] === $i ? 'selected' : '' }}>{{ $dayName }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Payout Time (PT)</label>
                        <input type="time" name="time" value="{{ $payoutSchedule['time'] }}"
                            class="text-sm border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 h-9 px-2">
                    </div>

                    <button type="submit"
                        class="h-9 px-4 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-md shadow-sm">
                        Save Schedule
                    </button>
                </form>

                {{-- Override next payout date --}}
                <div class="border-t border-gray-100 mt-4 pt-4">
                    <div class="text-xs font-medium text-gray-500 mb-2 uppercase tracking-wide">Override Next Payout Date</div>
                    <form method="POST" action="{{ route('payroll.schedule.override') }}" class="flex flex-wrap items-end gap-3">
                        @csrf @method('PATCH')
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Date</label>
                            <input type="date" name="override_date"
                                value="{{ $payoutSchedule['override'] ?? '' }}"
                                class="text-sm border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 h-9 px-2">
                        </div>
                        <button type="submit"
                            class="h-9 px-4 text-sm font-medium text-white bg-amber-500 hover:bg-amber-600 rounded-md shadow-sm">
                            Set Override
                        </button>
                        @if ($payoutSchedule['override'])
                        <button type="submit" name="override_date" value=""
                            class="h-9 px-4 text-sm font-medium text-gray-600 bg-white border border-gray-300 hover:bg-gray-50 rounded-md shadow-sm">
                            Clear Override
                        </button>
                        @endif
                    </form>
                </div>
            </div>
            @endif

            {{-- Assignment Age Colour Thresholds (admin only) --}}
            @if($isAdmin)
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-800 mb-1">Assignment Age Colours</h3>
                <p class="text-xs text-gray-500 mb-4">
                    Set the hour thresholds at which each assignment type's age text changes colour.
                    <span class="inline-block w-2 h-2 rounded-full bg-green-500 mx-0.5"></span> Green = fresh,
                    <span class="inline-block w-2 h-2 rounded-full bg-yellow-400 mx-0.5"></span> Yellow,
                    <span class="inline-block w-2 h-2 rounded-full bg-orange-400 mx-0.5"></span> Orange,
                    <span class="inline-block w-2 h-2 rounded-full bg-red-500 mx-0.5"></span> Red = overdue.
                </p>
                @if($isAdmin)
                    <form method="POST" action="{{ route('settings.age-thresholds') }}">
                        @csrf
                        @method('PATCH')
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-xs text-gray-500 uppercase tracking-wide border-b border-gray-100">
                                        <th class="text-left py-2 pr-4 font-medium">Service</th>
                                        <th class="text-center py-2 px-3 font-medium">
                                            <span class="inline-block w-2 h-2 rounded-full bg-yellow-400 mr-1"></span>Yellow after (hours)
                                        </th>
                                        <th class="text-center py-2 px-3 font-medium">
                                            <span class="inline-block w-2 h-2 rounded-full bg-orange-400 mr-1"></span>Orange after (hours)
                                        </th>
                                        <th class="text-center py-2 px-3 font-medium">
                                            <span class="inline-block w-2 h-2 rounded-full bg-red-500 mr-1"></span>Red after (hours)
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    @foreach ($ageThresholdTypes as $type => $label)
                                        <tr>
                                            <td class="py-2 pr-4 text-gray-700 font-medium whitespace-nowrap">{{ $label }}</td>
                                            <td class="py-2 px-3 text-center">
                                                <input type="number" name="yellow_{{ $type }}"
                                                       value="{{ $ageThresholds[$type]['yellow'] }}"
                                                       min="1" max="8760" required
                                                       class="w-16 text-center border-gray-300 rounded-md shadow-sm text-sm focus:ring-yellow-400 focus:border-yellow-400">
                                            </td>
                                            <td class="py-2 px-3 text-center">
                                                <input type="number" name="orange_{{ $type }}"
                                                       value="{{ $ageThresholds[$type]['orange'] }}"
                                                       min="1" max="8760" required
                                                       class="w-16 text-center border-gray-300 rounded-md shadow-sm text-sm focus:ring-orange-400 focus:border-orange-400">
                                            </td>
                                            <td class="py-2 px-3 text-center">
                                                <input type="number" name="red_{{ $type }}"
                                                       value="{{ $ageThresholds[$type]['red'] }}"
                                                       min="1" max="8760" required
                                                       class="w-16 text-center border-gray-300 rounded-md shadow-sm text-sm focus:ring-red-500 focus:border-red-500">
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4">
                            <x-primary-button>Save Thresholds</x-primary-button>
                        </div>
                    </form>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-xs text-gray-500 uppercase tracking-wide border-b border-gray-100">
                                    <th class="text-left py-2 pr-4 font-medium">Service</th>
                                    <th class="text-center py-2 px-3 font-medium">
                                        <span class="inline-block w-2 h-2 rounded-full bg-yellow-400 mr-1"></span>Yellow after (hours)
                                    </th>
                                    <th class="text-center py-2 px-3 font-medium">
                                        <span class="inline-block w-2 h-2 rounded-full bg-orange-400 mr-1"></span>Orange after (hours)
                                    </th>
                                    <th class="text-center py-2 px-3 font-medium">
                                        <span class="inline-block w-2 h-2 rounded-full bg-red-500 mr-1"></span>Red after (hours)
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach ($ageThresholdTypes as $type => $label)
                                    <tr>
                                        <td class="py-2 pr-4 text-gray-700 font-medium whitespace-nowrap">{{ $label }}</td>
                                        <td class="py-2 px-3 text-center text-gray-600 tabular-nums">{{ $ageThresholds[$type]['yellow'] }}</td>
                                        <td class="py-2 px-3 text-center text-gray-600 tabular-nums">{{ $ageThresholds[$type]['orange'] }}</td>
                                        <td class="py-2 px-3 text-center text-gray-600 tabular-nums">{{ $ageThresholds[$type]['red'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-3 text-xs text-gray-400">Age colour thresholds can only be modified by an admin.</p>
                @endif
            </div>
            @endif

            {{-- Admin-only sections --}}
            @if ($isAdmin)

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

                {{-- Invoice Settings --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h3 class="text-sm font-semibold text-gray-800 mb-1">Invoice Settings</h3>
                    <p class="text-xs text-gray-500 mb-4">Used when generating client invoices. The SR address can be overridden per-client.</p>

                    <form method="POST" action="{{ route('settings.invoice') }}" class="space-y-4">
                        @csrf
                        @method('PATCH')

                        <div>
                            <x-input-label for="sr_invoice_address" value="Screenplay Readers Address (default for invoices)" />
                            <textarea id="sr_invoice_address" name="sr_invoice_address" rows="4"
                                class="mt-1 block w-full text-sm rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                placeholder="e.g. Screenplay Readers&#10;123 Main St&#10;Los Angeles, CA 90001"
                            >{{ old('sr_invoice_address', $srInvoiceAddress) }}</textarea>
                            <p class="mt-1 text-xs text-gray-400">Appears as the sender address on PDF invoices.</p>
                        </div>

                        <div>
                            <x-input-label for="invoice_email_body" value="PDF Invoice Email Body (Help Scout draft)" />
                            <textarea id="invoice_email_body" name="invoice_email_body" rows="6"
                                class="mt-1 block w-full text-sm rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                placeholder="Hi,&#10;&#10;Please find your invoice attached…"
                            >{{ old('invoice_email_body', $invoiceEmailBody) }}</textarea>
                            <p class="mt-1 text-xs text-gray-400">Used as the body of the Help Scout draft reply when sending PDF invoices. Plain text or HTML.</p>
                        </div>

                        <div class="flex justify-end">
                            <x-primary-button>Save Invoice Settings</x-primary-button>
                        </div>
                    </form>
                </div>

                {{-- Reader Download Watermark --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6"
                     x-data="{
                         showName: {{ $watermarkSettings['watermark_show_name'] ? 'true' : 'false' }},
                         showOrder: {{ $watermarkSettings['watermark_show_order'] ? 'true' : 'false' }},
                         showDatetime: {{ $watermarkSettings['watermark_show_datetime'] ? 'true' : 'false' }},
                         showRef: {{ $watermarkSettings['watermark_show_ref'] ? 'true' : 'false' }},
                         customText: @js($watermarkSettings['watermark_custom_text']),
                         get preview() {
                             const parts = [];
                             if (this.customText.trim()) parts.push(this.customText.trim());
                             if (this.showName) parts.push('Jane Reader');
                             if (this.showOrder) parts.push('Order #SR-12345');
                             if (this.showDatetime) parts.push('Jun 10, 2026 3:45pm');
                             if (this.showRef) parts.push('Ref DL-42');
                             return parts.length ? parts.join(' · ') : 'Screenplay Readers';
                         }
                     }">
                    <h3 class="text-sm font-semibold text-gray-800 mb-1">Reader Download Watermark</h3>
                    <p class="text-xs text-gray-500 mb-4">Choose which fields appear in the diagonal watermark tiled across scripts readers download.</p>

                    <form method="POST" action="{{ route('settings.watermark') }}" class="space-y-4">
                        @csrf
                        @method('PATCH')

                        <div class="space-y-2">
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" name="watermark_show_name" value="1" x-model="showName"
                                       class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                <span class="text-sm text-gray-700">Reader name</span>
                            </label>
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" name="watermark_show_order" value="1" x-model="showOrder"
                                       class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                <span class="text-sm text-gray-700">Order number</span>
                            </label>
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" name="watermark_show_datetime" value="1" x-model="showDatetime"
                                       class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                <span class="text-sm text-gray-700">Download date &amp; time</span>
                            </label>
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" name="watermark_show_ref" value="1" x-model="showRef"
                                       class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                <span class="text-sm text-gray-700">Download reference ID</span>
                            </label>
                        </div>

                        <div>
                            <x-input-label for="watermark_custom_text" value="Custom text (optional)" />
                            <input type="text" id="watermark_custom_text" name="watermark_custom_text" maxlength="200"
                                   x-model="customText"
                                   placeholder="e.g. Confidential — Property of Screenplay Readers"
                                   class="mt-1 block w-full text-sm rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" />
                            <p class="mt-1 text-xs text-gray-400">Prepended to the watermark, e.g. a confidentiality notice or company name.</p>
                            <x-input-error class="mt-1" :messages="$errors->get('watermark_custom_text')" />
                        </div>

                        <div>
                            <p class="text-xs text-gray-500 mb-1">Preview:</p>
                            <p class="text-sm font-mono bg-gray-50 border border-gray-200 rounded px-3 py-2 text-gray-600" x-text="preview"></p>
                        </div>

                        <div class="flex justify-end">
                            <x-primary-button>Save Watermark Settings</x-primary-button>
                        </div>
                    </form>
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

                {{-- Filename Conventions --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h3 class="text-sm font-semibold text-gray-800 mb-1">Filename Conventions</h3>
                        <p class="text-xs text-gray-500">
                            Coverage docs use: <code class="bg-gray-100 rounded px-1 py-0.5 text-xs">ordernumber_YYYYMMDD_Title_WLast_<span class="text-indigo-600">suffix</span>-ReaderInitials.pdf</code>
                        </p>
                    </div>

                    <form method="POST" action="{{ route('admin.filenames.update') }}">
                        @csrf
                        @method('PATCH')

                        {{-- SR Types --}}
                        <div class="px-6 py-4 border-b border-gray-100">
                            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Screenplay Readers (SR)</h4>
                            <div class="space-y-3" x-data="{
                                sr_script_coverage: '{{ $filenameSuffixes['filename_suffix_sr_script_coverage'] }}',
                                sr_notes_only:      '{{ $filenameSuffixes['filename_suffix_sr_notes_only'] }}',
                                sr_deep_dive:       '{{ $filenameSuffixes['filename_suffix_sr_deep_dive'] }}',
                                sr_book:            '{{ $filenameSuffixes['filename_suffix_sr_book'] }}',
                                sr_budget:          '{{ $filenameSuffixes['filename_suffix_sr_budget'] }}',
                                sr_short:           '{{ $filenameSuffixes['filename_suffix_sr_short'] }}',
                            }">
                                @foreach([
                                    ['key' => 'sr_script_coverage', 'label' => 'Script Coverage'],
                                    ['key' => 'sr_notes_only',      'label' => 'Notes Only'],
                                    ['key' => 'sr_deep_dive',       'label' => 'Deep Dive'],
                                    ['key' => 'sr_book',            'label' => 'Book Coverage'],
                                    ['key' => 'sr_budget',          'label' => 'Budget Coverage'],
                                    ['key' => 'sr_short',           'label' => 'Short Coverage'],
                                ] as $row)
                                    <div class="grid grid-cols-[160px_160px_1fr] gap-4 items-center">
                                        <label class="text-sm text-gray-700">{{ $row['label'] }}</label>
                                        <input type="text"
                                               name="filename_suffix_{{ $row['key'] }}"
                                               x-model="{{ $row['key'] }}"
                                               placeholder="suffix"
                                               class="rounded-md border-gray-300 shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500 font-mono" />
                                        <p class="text-xs text-gray-400 font-mono truncate">
                                            19192_…_GLucas_<span class="text-indigo-600" x-text="{{ $row['key'] }} || '…'"></span>-KD.pdf
                                        </p>
                                    </div>
                                    @error('filename_suffix_' . $row['key'])
                                        <p class="text-xs text-red-600 col-start-2">{{ $message }}</p>
                                    @enderror
                                @endforeach
                            </div>
                        </div>

                        {{-- WD Types --}}
                        <div class="px-6 py-4 border-b border-gray-100">
                            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Writer's Digest (WD)</h4>
                            <div class="space-y-3" x-data="{
                                wd_coverage:          '{{ $filenameSuffixes['filename_suffix_wd_coverage'] }}',
                                wd_development_notes: '{{ $filenameSuffixes['filename_suffix_wd_development_notes'] }}',
                            }">
                                @foreach([
                                    ['key' => 'wd_coverage',          'label' => 'Coverage'],
                                    ['key' => 'wd_development_notes', 'label' => 'Development Notes'],
                                ] as $row)
                                    <div class="grid grid-cols-[160px_160px_1fr] gap-4 items-center">
                                        <label class="text-sm text-gray-700">{{ $row['label'] }}</label>
                                        <input type="text"
                                               name="filename_suffix_{{ $row['key'] }}"
                                               x-model="{{ $row['key'] }}"
                                               placeholder="suffix"
                                               class="rounded-md border-gray-300 shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500 font-mono" />
                                        <p class="text-xs text-gray-400 font-mono truncate">
                                            WD_…_GLucas_<span class="text-indigo-600" x-text="{{ $row['key'] }} || '…'"></span>-KD.pdf
                                        </p>
                                    </div>
                                    @error('filename_suffix_' . $row['key'])
                                        <p class="text-xs text-red-600 col-start-2">{{ $message }}</p>
                                    @enderror
                                @endforeach
                            </div>
                        </div>

                        <div class="px-6 py-4">
                            <x-primary-button>Save Conventions</x-primary-button>
                        </div>
                    </form>
                </div>

                {{-- Coverage Submission Page --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h3 class="text-sm font-semibold text-gray-800 mb-1">Coverage Submission Page</h3>
                    <p class="text-xs text-gray-500 mb-4">
                        Content shown beneath the "Assignment Submitted for QC" confirmation. Accepts raw HTML. Leave blank for none.
                    </p>

                    <form method="POST" action="{{ route('settings.coverage-success.update') }}">
                        @csrf
                        @method('PATCH')
                        <textarea name="content" rows="8"
                                  class="block w-full border-gray-300 rounded-md shadow-sm text-sm font-mono focus:border-indigo-500 focus:ring-indigo-500"
                                  placeholder="<p>Your HTML here...</p>">{{ old('content', $coverageSuccessHtml) }}</textarea>
                        <x-input-error :messages="$errors->get('content')" class="mt-1" />

                        @if($coverageSuccessHtml)
                            <details class="mt-3">
                                <summary class="text-xs text-gray-400 cursor-pointer select-none hover:text-gray-600">Preview</summary>
                                <div class="mt-2 p-4 bg-gray-50 border border-gray-200 rounded-md text-sm text-gray-700 prose prose-sm max-w-none">
                                    {!! $coverageSuccessHtml !!}
                                </div>
                            </details>
                        @endif

                        <div class="flex justify-end mt-4">
                            <x-primary-button>Save</x-primary-button>
                        </div>
                    </form>
                </div>

                {{-- Followup Form HTML --}}
                @if($isAdmin)
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h3 class="text-sm font-semibold text-gray-800 mb-1">Followup Questions Form</h3>
                    <p class="text-xs text-gray-500 mb-4">
                        Optional HTML injected into the public followup form sent to customers. Accepts raw HTML. Leave blank for none.
                    </p>

                    <form method="POST" action="{{ route('settings.followup-html') }}" class="space-y-4">
                        @csrf
                        @method('PATCH')

                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Page heading</label>
                            <input type="text" name="followup_heading"
                                   value="{{ old('followup_heading', $followupHeading) }}"
                                   placeholder="Followup Questions"
                                   class="block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <p class="mt-1 text-xs text-gray-400">Defaults to "Followup Questions" if left blank.</p>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">HTML before the form</label>
                            <textarea name="followup_before_html" rows="5"
                                      class="block w-full border-gray-300 rounded-md shadow-sm text-sm font-mono focus:border-indigo-500 focus:ring-indigo-500"
                                      placeholder="<p>Your HTML here...</p>">{{ old('followup_before_html', $followupBeforeHtml) }}</textarea>
                            @if($followupBeforeHtml)
                                <details class="mt-2">
                                    <summary class="text-xs text-gray-400 cursor-pointer select-none hover:text-gray-600">Preview</summary>
                                    <div class="mt-2 p-4 bg-gray-50 border border-gray-200 rounded-md text-sm text-gray-700 prose prose-sm max-w-none">
                                        {!! $followupBeforeHtml !!}
                                    </div>
                                </details>
                            @endif
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">HTML after the form</label>
                            <textarea name="followup_after_html" rows="5"
                                      class="block w-full border-gray-300 rounded-md shadow-sm text-sm font-mono focus:border-indigo-500 focus:ring-indigo-500"
                                      placeholder="<p>Your HTML here...</p>">{{ old('followup_after_html', $followupAfterHtml) }}</textarea>
                            @if($followupAfterHtml)
                                <details class="mt-2">
                                    <summary class="text-xs text-gray-400 cursor-pointer select-none hover:text-gray-600">Preview</summary>
                                    <div class="mt-2 p-4 bg-gray-50 border border-gray-200 rounded-md text-sm text-gray-700 prose prose-sm max-w-none">
                                        {!! $followupAfterHtml !!}
                                    </div>
                                </details>
                            @endif
                        </div>

                        <div class="flex justify-end">
                            <x-primary-button>Save</x-primary-button>
                        </div>
                    </form>
                </div>
                @endif

                {{-- Completion Draft Email --}}
                @if($isAdmin)
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h3 class="text-sm font-semibold text-gray-800 mb-1">Completion Draft Email</h3>
                    <p class="text-xs text-gray-500 mb-4">
                        HTML body for the HelpScout draft created when all readers on an order are approved. Accepts raw HTML.
                        Use <code class="text-xs bg-gray-100 px-1 rounded">{%customer.firstName,fallback=...%}</code> for HelpScout
                        merge fields and <code class="text-xs bg-gray-100 px-1 rounded">{{ '{{followup_url}}' }}</code> for the
                        customer's followup-questions link.
                    </p>

                    <form method="POST" action="{{ route('settings.completion-draft') }}" class="space-y-4">
                        @csrf
                        @method('PATCH')

                        <textarea name="completion_draft_body" rows="14"
                                  class="block w-full border-gray-300 rounded-md shadow-sm text-sm font-mono focus:border-indigo-500 focus:ring-indigo-500">{{ old('completion_draft_body', $completionDraftBody) }}</textarea>
                        <x-input-error :messages="$errors->get('completion_draft_body')" class="mt-1" />

                        <details class="mt-2">
                            <summary class="text-xs text-gray-400 cursor-pointer select-none hover:text-gray-600">Preview</summary>
                            <div class="mt-2 p-4 bg-gray-50 border border-gray-200 rounded-md text-sm text-gray-700 prose prose-sm max-w-none">
                                {!! $completionDraftBody !!}
                            </div>
                        </details>

                        <div class="flex items-center justify-end gap-3">
                            <div x-data="{ loading: false, error: '' }" class="relative">
                                <button type="button"
                                        @click="
                                            loading = true; error = '';
                                            fetch('{{ route('settings.completion-draft.test') }}', {
                                                method: 'POST',
                                                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' }
                                            })
                                            .then(r => r.json())
                                            .then(d => { loading = false; if (d.url) window.open(d.url, '_blank'); else error = d.error ?? 'Unknown error'; })
                                            .catch(e => { loading = false; error = e.message; })
                                        "
                                        :disabled="loading"
                                        :class="loading ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-300'"
                                        class="inline-flex items-center px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-800 uppercase tracking-widest transition ease-in-out duration-150">
                                    <span x-text="loading ? 'Sending…' : 'Send Test Draft'"></span>
                                </button>
                                <p x-show="error" x-cloak x-text="error"
                                   class="absolute right-0 top-full mt-1 text-xs text-red-600 bg-white border border-red-200 rounded px-2 py-1 whitespace-nowrap shadow-sm z-10"></p>
                            </div>
                            <x-primary-button>Save</x-primary-button>
                        </div>
                        <p class="text-xs text-gray-400 -mt-2">
                            "Send Test Draft" creates a draft using the saved template (no attachment) on a HelpScout sandbox conversation —
                            it does not contact a real customer.
                        </p>
                    </form>
                </div>
                @endif

                {{-- Dev Autofill --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h3 class="text-sm font-semibold text-gray-800 mb-1">Coverage Form — Dev Autofill</h3>
                    <p class="text-xs text-gray-500 mb-4">
                        Show the "DEV: Autofill test data" button on the Write Coverage form. Enable per role for testing; disable before going live.
                    </p>
                    <form method="POST" action="{{ route('settings.dev-autofill') }}">
                        @csrf
                        @method('PATCH')
                        <div class="space-y-3">
                            @foreach (['admin' => 'Admins', 'editor' => 'Editors', 'reader' => 'Readers'] as $role => $label)
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox" name="dev_autofill_{{ $role }}" value="1"
                                           {{ $devAutofill[$role] ? 'checked' : '' }}
                                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                    <span class="text-sm text-gray-700">Show autofill button for <strong>{{ $label }}</strong></span>
                                </label>
                            @endforeach
                        </div>
                        <div class="flex justify-end mt-4">
                            <x-primary-button>Save</x-primary-button>
                        </div>
                    </form>
                </div>

            @endif

            {{-- QC Saved Replies --}}
            @if($isAdmin)
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6"
                 x-data="{
                     replies: {{ Js::from($qcSavedReplies) }},
                     addReply() {
                         this.replies.push({ name: '', body: '' });
                         this.$nextTick(() => {
                             const inputs = this.$el.querySelectorAll('input[name*=\"[name]\"]');
                             inputs[inputs.length - 1]?.focus();
                         });
                     },
                     removeReply(idx) {
                         this.replies.splice(idx, 1);
                     }
                 }">
                <h3 class="text-sm font-semibold text-gray-800 mb-1">QC Saved Replies</h3>
                <p class="text-xs text-gray-500 mb-4">
                    Quick-insert notes shown as checkboxes in the "Send Back to Reader" modal on the QC review page.
                    Check one or more to append the text into the notes field before sending.
                </p>

                <form method="POST" action="{{ route('settings.qc-saved-replies') }}">
                    @csrf
                    @method('PATCH')

                    <div class="space-y-3 mb-4">
                        <template x-for="(reply, idx) in replies" :key="idx">
                            <div class="flex gap-2 items-start p-3 bg-gray-50 rounded-md border border-gray-200">
                                <div class="flex flex-col gap-2 flex-1 min-w-0">
                                    <input type="text"
                                           :name="'replies[' + idx + '][name]'"
                                           :value="replies[idx].name"
                                           @input="replies[idx].name = $event.target.value"
                                           placeholder="Reply name (e.g. Too much formatting talk)"
                                           maxlength="100"
                                           class="w-full text-sm border border-gray-300 rounded px-2.5 py-1.5 focus:outline-none focus:ring-1 focus:ring-indigo-400" />
                                    <textarea
                                           :name="'replies[' + idx + '][body]'"
                                           @input="replies[idx].body = $event.target.value"
                                           placeholder="Text inserted into the notes field…"
                                           rows="2"
                                           maxlength="2000"
                                           x-text="replies[idx].body"
                                           class="w-full text-sm border border-gray-300 rounded px-2.5 py-1.5 resize-none focus:outline-none focus:ring-1 focus:ring-indigo-400"></textarea>
                                </div>
                                <button type="button" @click="removeReply(idx)"
                                        class="shrink-0 mt-1 text-gray-400 hover:text-red-500 transition-colors"
                                        title="Remove">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                        </template>
                    </div>

                    <div class="flex items-center justify-between">
                        <button type="button" @click="addReply()"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm text-indigo-600 border border-indigo-300 rounded-md hover:bg-indigo-50 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Add reply
                        </button>
                        <x-primary-button>Save replies</x-primary-button>
                    </div>
                </form>
            </div>
            @endif

            @if ($isAdmin)
            {{-- Email Notification Texts --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-sm font-semibold text-gray-800 mb-1">Email Notification Text</h3>
                <p class="text-xs text-gray-500 mb-4">Text sent to readers when a new assignment is available. These map directly to your MailerSend template variables.</p>

                <form method="POST" action="{{ route('settings.email-notifications') }}" class="space-y-5">
                    @csrf
                    @method('PATCH')

                    <fieldset>
                        <legend class="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-2">Subject lines <span class="normal-case font-normal text-gray-400">(@{{ subject }} in template)</span></legend>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            @foreach ([
                                'email_notif_subject_new'          => 'Standard new assignment',
                                'email_notif_subject_rush'         => 'Rush assignment',
                                'email_notif_subject_request'      => 'Reader request',
                                'email_notif_subject_rush_request' => 'Rush reader request',
                            ] as $key => $label)
                            <div>
                                <x-input-label :for="$key" :value="$label" class="mb-1" />
                                <x-text-input :id="$key" :name="$key" type="text" class="w-full text-sm"
                                    :value="old($key, $emailNotifTexts[$key])" required maxlength="500" />
                            </div>
                            @endforeach
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend class="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-2">Headers <span class="normal-case font-normal text-gray-400">(@{{ header }} in template)</span></legend>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            @foreach ([
                                'email_notif_header_new'          => 'Standard new assignment',
                                'email_notif_header_rush'         => 'Rush assignment',
                                'email_notif_header_request'      => 'Reader request',
                                'email_notif_header_rush_request' => 'Rush reader request',
                            ] as $key => $label)
                            <div>
                                <x-input-label :for="$key" :value="$label" class="mb-1" />
                                <x-text-input :id="$key" :name="$key" type="text" class="w-full text-sm"
                                    :value="old($key, $emailNotifTexts[$key])" required maxlength="500" />
                            </div>
                            @endforeach
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend class="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-2">Body messages <span class="normal-case font-normal text-gray-400">(@{{ body_message }} in template)</span></legend>
                        <div class="space-y-3">
                            @foreach ([
                                'email_notif_body_new'     => 'Standard / rush (open pool)',
                                'email_notif_body_request' => 'Reader request',
                            ] as $key => $label)
                            <div>
                                <x-input-label :for="$key" :value="$label" class="mb-1" />
                                <textarea :id="$key" id="{{ $key }}" name="{{ $key }}" rows="2" maxlength="500"
                                    class="w-full text-sm border border-gray-300 rounded px-2.5 py-1.5 resize-none focus:outline-none focus:ring-1 focus:ring-indigo-400"
                                >{{ old($key, $emailNotifTexts[$key]) }}</textarea>
                            </div>
                            @endforeach
                        </div>
                    </fieldset>

                    <div class="flex justify-end">
                        <x-primary-button>Save notification text</x-primary-button>
                    </div>
                </form>
            </div>
            @endif

            @if($isAdmin && $wordCounts !== null)
            {{-- Word Count Minimums --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-800 mb-1">Coverage Word Count Minimums</h3>
                <p class="text-xs text-gray-500 mb-4">
                    Set minimum word counts for each coverage field. Readers cannot submit coverage until these minimums are met
                    (unless the assignment is marked <em>Exempt from word counts</em>). Set a field to 0 to require no minimum.
                </p>

                <form method="POST" action="{{ route('settings.word-counts') }}" class="space-y-6">
                    @csrf
                    @method('PATCH')

                    {{-- Global toggle --}}
                    <div class="flex items-center gap-3">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="wc_enabled" value="0" />
                            <input type="checkbox" name="wc_enabled" value="1"
                                {{ $wordCounts['wc_enabled'] ? 'checked' : '' }}
                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                            <span class="text-sm font-medium text-gray-700">Enable word count minimums globally</span>
                        </label>
                        @if(!$wordCounts['wc_enabled'])
                            <span class="text-xs text-amber-600 font-medium">Currently disabled — no word counts enforced</span>
                        @endif
                    </div>

                    {{-- SR Coverage --}}
                    <div class="border-t border-gray-100 pt-5 space-y-4">
                        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">SR Coverage</h4>

                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                            <div>
                                <x-input-label value="Logline (min words)" />
                                <input type="number" name="wc_sr_logline" min="0" max="99999"
                                    value="{{ old('wc_sr_logline', $wordCounts['wc_sr_logline']) }}"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                                <p class="mt-0.5 text-xs text-gray-400">Applies to SR logline field</p>
                            </div>
                            <div>
                                <x-input-label value="Synopsis (min words)" />
                                <input type="number" name="wc_sr_synopsis" min="0" max="99999"
                                    value="{{ old('wc_sr_synopsis', $wordCounts['wc_sr_synopsis']) }}"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                                <p class="mt-0.5 text-xs text-gray-400">Script Coverage & Book types</p>
                            </div>
                        </div>

                        <div>
                            <p class="text-xs font-medium text-gray-600 mb-2">Notes — minimum words by assignment type</p>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                                @foreach([
                                    'wc_sr_notes_script_coverage' => 'Script Coverage',
                                    'wc_sr_notes_notes_only'      => 'Notes-Only',
                                    'wc_sr_notes_short'           => 'Short',
                                    'wc_sr_notes_deep_dive'       => 'Deep-Dive',
                                    'wc_sr_notes_budget'          => 'Budget',
                                    'wc_sr_notes_book'            => 'Book',
                                ] as $key => $label)
                                    <div>
                                        <x-input-label :value="$label" />
                                        <input type="number" name="{{ $key }}" min="0" max="99999"
                                            value="{{ old($key, $wordCounts[$key]) }}"
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- WD Coverage --}}
                    <div class="border-t border-gray-100 pt-5 space-y-4">
                        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">WD Coverage</h4>

                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                            <div>
                                <x-input-label value="Logline (min words)" />
                                <input type="number" name="wc_wd_logline" min="0" max="99999"
                                    value="{{ old('wc_wd_logline', $wordCounts['wc_wd_logline']) }}"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                            </div>
                            <div>
                                <x-input-label value="Synopsis (min words)" />
                                <input type="number" name="wc_wd_synopsis" min="0" max="99999"
                                    value="{{ old('wc_wd_synopsis', $wordCounts['wc_wd_synopsis']) }}"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                                <p class="mt-0.5 text-xs text-gray-400">Coverage type only</p>
                            </div>
                        </div>

                        <div>
                            <p class="text-xs font-medium text-gray-600 mb-2">Notes — total minimum words by assignment type</p>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                                @foreach([
                                    'wc_wd_notes_coverage'          => 'Coverage',
                                    'wc_wd_notes_development_notes' => 'Development Notes',
                                ] as $key => $label)
                                    <div>
                                        <x-input-label :value="$label" />
                                        <input type="number" name="{{ $key }}" min="0" max="99999"
                                            value="{{ old($key, $wordCounts[$key]) }}"
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end border-t border-gray-100 pt-4">
                        <x-primary-button>Save word count settings</x-primary-button>
                    </div>
                </form>
            </div>
            @endif

            {{-- Quick-Login Link --}}
            @if($isAdmin)
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

                    {{-- Landing page selector --}}
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
                                <span x-show="copied" x-cloak class="text-green-700">✓ Copied</span>
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
            @endif

            {{-- Last Seen Reset --}}
            @if($isAdmin)
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
</x-app-layout>
