<nav class="bg-white border-b border-gray-100" x-data="{ open: false }">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <div class="shrink-0 flex items-center">
                    @php
                        $metaFile = storage_path('app/portal-logo-path.txt');
                        $logoUrl  = is_readable($metaFile) ? asset('storage/' . trim(file_get_contents($metaFile))) : null;
                    @endphp
                    @if(auth()->user()?->canManageAssignments())
                        <form method="POST" action="{{ route('settings.logo') }}" enctype="multipart/form-data" id="nav-logo-form">
                            @csrf
                            <input type="file" id="nav-logo-input" name="logo" accept="image/*" class="hidden"
                                   onchange="document.getElementById('nav-logo-form').submit()">
                            <button type="button" onclick="document.getElementById('nav-logo-input').click()"
                                    title="Click to replace logo"
                                    class="group relative flex items-center justify-center h-9 w-9 rounded overflow-hidden transition">
                                @if($logoUrl)
                                    <img src="{{ $logoUrl }}" alt="" class="w-full h-full object-contain">
                                @else
                                    <x-application-logo class="block h-9 w-auto fill-current text-gray-800 group-hover:opacity-60 transition" />
                                @endif
                                <span class="absolute inset-0 bg-black/25 opacity-0 group-hover:opacity-100 flex items-center justify-center transition rounded">
                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                                    </svg>
                                </span>
                            </button>
                        </form>
                    @else
                        <a href="{{ route('dashboard') }}">
                            @if($logoUrl)
                                <img src="{{ $logoUrl }}" alt="" class="block h-9 w-auto object-contain">
                            @else
                                <x-application-logo class="block h-9 w-auto fill-current text-gray-800" />
                            @endif
                        </a>
                    @endif
                </div>

                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('assignments.index')" :active="request()->routeIs('assignments.*')">
                        {{ __('Assignments') }}
                    </x-nav-link>
                    @if(auth()->user()?->canManageAssignments())
                        <x-nav-link :href="route('qc.index')" :active="request()->routeIs('qc.*')">
                            {{ __('QC') }}
                        </x-nav-link>

                        {{-- Admin dropdown (Readers, Archive, Ratebook, Reader Manual) --}}
                        @php
                            $adminActive = request()->routeIs('readers.*') || request()->routeIs('archive.*') || request()->routeIs('ratebook.*') || request()->routeIs('manual.*') || request()->routeIs('admin.permissions*') || request()->routeIs('admin.filenames*');
                        @endphp
                        <div class="relative flex items-center"
                             x-data="{ adminOpen: false }"
                             @mouseenter="adminOpen = true"
                             @mouseleave="adminOpen = false">
                            <button type="button"
                                class="inline-flex items-center gap-1 px-1 pt-1 border-b-2 text-sm font-medium leading-5 focus:outline-none transition duration-150 ease-in-out {{ $adminActive ? 'border-indigo-400 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                                Admin
                                <svg class="w-3 h-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>
                            <div x-show="adminOpen" x-cloak
                                 class="absolute top-full left-0 mt-0 w-48 bg-white border border-gray-200 rounded-md shadow-lg py-1 z-50">
                                <a href="{{ route('readers.index') }}"
                                    class="block px-4 py-2 text-sm {{ request()->routeIs('readers.*') ? 'text-indigo-700 font-semibold bg-indigo-50' : 'text-gray-700 hover:bg-gray-50' }}">
                                    Readers
                                </a>
                                <a href="{{ route('archive.index') }}"
                                    class="block px-4 py-2 text-sm {{ request()->routeIs('archive.*') ? 'text-indigo-700 font-semibold bg-indigo-50' : 'text-gray-700 hover:bg-gray-50' }}">
                                    Archive
                                </a>
                                <a href="{{ route('ratebook.index') }}"
                                    class="block px-4 py-2 text-sm {{ request()->routeIs('ratebook.*') ? 'text-indigo-700 font-semibold bg-indigo-50' : 'text-gray-700 hover:bg-gray-50' }}">
                                    Ratebook
                                </a>
                                <a href="{{ route('manual.show') }}"
                                    class="block px-4 py-2 text-sm {{ request()->routeIs('manual.*') ? 'text-indigo-700 font-semibold bg-indigo-50' : 'text-gray-700 hover:bg-gray-50' }}">
                                    Reader Manual
                                </a>
                                @if(auth()->user()?->isAdmin())
                                    <div class="my-1 border-t border-gray-100"></div>
                                    <a href="{{ route('admin.permissions') }}"
                                        class="block px-4 py-2 text-sm {{ request()->routeIs('admin.permissions*') ? 'text-indigo-700 font-semibold bg-indigo-50' : 'text-gray-700 hover:bg-gray-50' }}">
                                        Permissions
                                    </a>
                                    <a href="{{ route('admin.filenames') }}"
                                        class="block px-4 py-2 text-sm {{ request()->routeIs('admin.filenames*') ? 'text-indigo-700 font-semibold bg-indigo-50' : 'text-gray-700 hover:bg-gray-50' }}">
                                        Filenames
                                    </a>
                                @endif
                            </div>
                        </div>
                    @else
                        <x-nav-link :href="route('manual.show')" :active="request()->routeIs('manual.*')">
                            {{ __('Reader Manual') }}
                        </x-nav-link>
                    @endif
                </div>
            </div>

            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                            <div>{{ Auth::user()->name }}</div>
                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')"
                                onclick="event.preventDefault(); this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('assignments.index')" :active="request()->routeIs('assignments.*')">
                {{ __('Assignments') }}
            </x-responsive-nav-link>
            @if(auth()->user()?->canManageAssignments())
                <x-responsive-nav-link :href="route('qc.index')" :active="request()->routeIs('qc.*')">
                    {{ __('QC') }}
                </x-responsive-nav-link>
                <div class="px-4 pt-3 pb-1 text-xs font-semibold text-gray-400 uppercase tracking-wide">Admin</div>
                <x-responsive-nav-link :href="route('readers.index')" :active="request()->routeIs('readers.*')">
                    {{ __('Readers') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('archive.index')" :active="request()->routeIs('archive.*')">
                    {{ __('Archive') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('ratebook.index')" :active="request()->routeIs('ratebook.*')">
                    {{ __('Ratebook') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('manual.show')" :active="request()->routeIs('manual.*')">
                    {{ __('Reader Manual') }}
                </x-responsive-nav-link>
                @if(auth()->user()?->isAdmin())
                    <x-responsive-nav-link :href="route('admin.permissions')" :active="request()->routeIs('admin.permissions*')">
                        {{ __('Permissions') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('admin.filenames')" :active="request()->routeIs('admin.filenames*')">
                        {{ __('Filenames') }}
                    </x-responsive-nav-link>
                @endif
            @else
                <x-responsive-nav-link :href="route('manual.show')" :active="request()->routeIs('manual.*')">
                    {{ __('Reader Manual') }}
                </x-responsive-nav-link>
            @endif
        </div>

        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-responsive-nav-link :href="route('logout')"
                        onclick="event.preventDefault(); this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
