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

            {{-- Reader Announcements --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-800 mb-1">Reader Announcements</h3>
                <p class="text-xs text-gray-500 mb-4">Post updates that appear as a banner at the top of every page for all readers. Readers can mark them as read or dismiss them.</p>

                <form method="POST" action="{{ route('announcements.store') }}" class="flex gap-3 items-end mb-5">
                    @csrf
                    <div class="flex-1">
                        <x-input-label for="announcement_body" value="New Announcement" />
                        <textarea id="announcement_body" name="body" rows="2"
                                  class="mt-1 block w-full text-sm rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                  placeholder="e.g. SR will be closed Monday for a holiday. No new assignments will be sent."
                                  maxlength="2000" required></textarea>
                        <x-input-error :messages="$errors->get('body')" class="mt-1" />
                    </div>
                    <x-primary-button>Post</x-primary-button>
                </form>

                @php $existingAnnouncements = \App\Models\Announcement::with('createdBy')->orderBy('created_at', 'desc')->get(); @endphp
                @if($existingAnnouncements->isNotEmpty())
                    <ul class="space-y-2">
                        @foreach($existingAnnouncements as $ann)
                            <li class="flex items-start gap-3 text-sm border border-gray-100 rounded-md px-3 py-2.5 bg-gray-50">
                                <div class="flex-1 min-w-0">
                                    <p class="text-gray-800">{{ $ann->body }}</p>
                                    <p class="text-xs text-gray-400 mt-0.5">
                                        Posted by {{ $ann->createdBy?->name ?? 'Unknown' }} · {{ $ann->created_at->format('M j, Y \a\t g:i A') }} · {{ $ann->created_at->diffForHumans() }}
                                    </p>
                                </div>
                                <form method="POST" action="{{ route('announcements.destroy', $ann) }}"
                                      onsubmit="return confirm('Delete this announcement for all readers?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-400 hover:text-red-600 text-xs underline whitespace-nowrap">Delete</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-xs text-gray-400">No announcements posted yet.</p>
                @endif
            </div>

            {{-- Portal Theme --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-800 mb-1">Portal Theme</h3>
                <p class="text-xs text-gray-500 mb-4">Sets the colour scheme for the navigation bar and accent colours throughout the portal.</p>
                <div class="flex flex-wrap gap-3">
                    @foreach([
                        'default'  => ['label' => 'Default',  'nav' => '#2b4158', 'border_nav' => '#1e3047', 'body' => '#f7f4e6', 'accent' => '#3c9590'],
                        'midnight' => ['label' => 'Midnight', 'nav' => '#16213e', 'border_nav' => '#0f3460', 'body' => '#1c1c2e', 'accent' => '#e94560'],
                        'forest'   => ['label' => 'Forest',   'nav' => '#1e3a2f', 'border_nav' => '#2d5440', 'body' => '#f0f4f0', 'accent' => '#4caf50'],
                        'warm'     => ['label' => 'Warm',     'nav' => '#5c3317', 'border_nav' => '#7a4520', 'body' => '#faf6f1', 'accent' => '#d4793b'],
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

            {{-- Assignment Age Colour Thresholds --}}
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

        </div>
    </div>
</x-app-layout>
