<nav id="portal-nav" class="bg-white border-b border-gray-100" x-data="{ open: false }">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <div class="shrink-0 flex items-center">
                    @php
                        $metaFile = storage_path('app/portal-logo-path.txt');
                        $logoUrl  = is_readable($metaFile) ? asset('storage/' . trim(file_get_contents($metaFile))) : null;
                    @endphp
                    <a href="{{ route('assignments.index') }}">
                        @if($logoUrl)
                            <img src="{{ $logoUrl }}" alt="" class="block h-11 w-auto object-contain">
                        @else
                            <x-application-logo class="block h-11 w-auto fill-current text-gray-800" />
                        @endif
                    </a>
                </div>

                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('assignments.index')" :active="request()->routeIs('assignments.*')">
                        {{ __('Assignments') }}
                    </x-nav-link>
                    @if(auth()->user()?->canManageAssignments())
                        @php $qcCount = \App\Models\Assignment::where('status', 'qc')->count(); @endphp
                        <x-nav-link :href="route('qc.index')" :active="request()->routeIs('qc.*')">
                            <span class="inline-flex items-center gap-1.5">
                                {{ __('QC') }}
                                @if($qcCount > 0)
                                    <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-green-500 text-white text-[9px] font-bold leading-none">{{ $qcCount }}</span>
                                @endif
                            </span>
                        </x-nav-link>

                        {{-- Admin + Editor top-level tabs --}}
                        @if(auth()->user()?->isAdmin())
                            @php $ordersActive = request()->routeIs('woo-orders.*') || request()->routeIs('order-log.*') || request()->routeIs('read-credits.*'); @endphp
                            <div class="relative flex items-center"
                                 x-data="{ ordersOpen: false }"
                                 @mouseenter="ordersOpen = true"
                                 @mouseleave="ordersOpen = false">
                                <button type="button"
                                    class="inline-flex items-center gap-1 px-1 pt-1 border-b-2 text-sm font-medium leading-5 focus:outline-none transition duration-150 ease-in-out {{ $ordersActive ? 'border-indigo-400 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                                    Woo Orders
                                    <svg class="w-3 h-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </button>
                                <div x-show="ordersOpen" x-cloak
                                     class="absolute top-full left-0 mt-0 w-40 bg-white border border-gray-200 rounded-md shadow-lg py-1 z-50">
                                    <a href="{{ route('woo-orders.index') }}"
                                       class="block px-4 py-2 text-sm {{ request()->routeIs('woo-orders.*') ? 'text-indigo-700 font-semibold bg-indigo-50' : 'text-gray-700 hover:bg-gray-50' }}">
                                        Woo Orders
                                    </a>
                                    <a href="{{ route('order-log.index') }}"
                                       class="block px-4 py-2 text-sm {{ request()->routeIs('order-log.*') ? 'text-indigo-700 font-semibold bg-indigo-50' : 'text-gray-700 hover:bg-gray-50' }}">
                                        Order Log
                                    </a>
                                    <a href="{{ route('read-credits.index') }}"
                                       class="block px-4 py-2 text-sm {{ request()->routeIs('read-credits.*') ? 'text-indigo-700 font-semibold bg-indigo-50' : 'text-gray-700 hover:bg-gray-50' }}">
                                        Read Credits
                                    </a>
                                </div>
                            </div>
                        @else
                            @php $ordersActive = request()->routeIs('woo-orders.*') || request()->routeIs('read-credits.*'); @endphp
                            <div class="relative flex items-center"
                                 x-data="{ ordersOpen: false }"
                                 @mouseenter="ordersOpen = true"
                                 @mouseleave="ordersOpen = false">
                                <button type="button"
                                    class="inline-flex items-center gap-1 px-1 pt-1 border-b-2 text-sm font-medium leading-5 focus:outline-none transition duration-150 ease-in-out {{ $ordersActive ? 'border-indigo-400 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                                    Woo Orders
                                    <svg class="w-3 h-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </button>
                                <div x-show="ordersOpen" x-cloak
                                     class="absolute top-full left-0 mt-0 w-40 bg-white border border-gray-200 rounded-md shadow-lg py-1 z-50">
                                    <a href="{{ route('woo-orders.index') }}"
                                       class="block px-4 py-2 text-sm {{ request()->routeIs('woo-orders.*') ? 'text-indigo-700 font-semibold bg-indigo-50' : 'text-gray-700 hover:bg-gray-50' }}">
                                        Woo Orders
                                    </a>
                                    <a href="{{ route('read-credits.index') }}"
                                       class="block px-4 py-2 text-sm {{ request()->routeIs('read-credits.*') ? 'text-indigo-700 font-semibold bg-indigo-50' : 'text-gray-700 hover:bg-gray-50' }}">
                                        Read Credits
                                    </a>
                                </div>
                            </div>
                        @endif
                        {{-- Clients + Invoicing dropdown --}}
                        @php $clientsActive = request()->routeIs('clients.*') || request()->routeIs('invoicing.*'); @endphp
                        <div class="relative flex items-center"
                             x-data="{ clientsOpen: false }"
                             @mouseenter="clientsOpen = true"
                             @mouseleave="clientsOpen = false">
                            <button type="button"
                                class="inline-flex items-center gap-1 px-1 pt-1 border-b-2 text-sm font-medium leading-5 focus:outline-none transition duration-150 ease-in-out {{ $clientsActive ? 'border-indigo-400 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                                Clients
                                <svg class="w-3 h-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>
                            <div x-show="clientsOpen" x-cloak
                                 class="absolute top-full left-0 mt-0 w-44 bg-white border border-gray-200 rounded-md shadow-lg py-1 z-50">
                                <a href="{{ route('clients.index') }}"
                                   class="block px-4 py-2 text-sm {{ request()->routeIs('clients.*') ? 'text-indigo-700 font-semibold bg-indigo-50' : 'text-gray-700 hover:bg-gray-50' }}">
                                    Clients
                                </a>
                                <a href="{{ route('invoicing.index') }}"
                                   class="block px-4 py-2 text-sm {{ request()->routeIs('invoicing.index') ? 'text-indigo-700 font-semibold bg-indigo-50' : 'text-gray-700 hover:bg-gray-50' }}">
                                    All Invoices
                                </a>
                                <a href="{{ route('invoicing.create') }}"
                                   class="block px-4 py-2 text-sm {{ request()->routeIs('invoicing.create') ? 'text-indigo-700 font-semibold bg-indigo-50' : 'text-gray-700 hover:bg-gray-50' }}">
                                    Create Invoice
                                </a>
                            </div>
                        </div>

                        {{-- Admin-only top-level tabs --}}
                        @if(auth()->user()?->isAdmin())
                            @php $revenueActive = request()->routeIs('revenue.*') || request()->routeIs('statistics.*') || request()->routeIs('payroll.*'); @endphp
                            <div class="relative flex items-center"
                                 x-data="{ revenueOpen: false }"
                                 @mouseenter="revenueOpen = true"
                                 @mouseleave="revenueOpen = false">
                                <button type="button"
                                    class="inline-flex items-center gap-1 px-1 pt-1 border-b-2 text-sm font-medium leading-5 focus:outline-none transition duration-150 ease-in-out {{ $revenueActive ? 'border-indigo-400 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                                    Revenue
                                    <svg class="w-3 h-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </button>
                                <div x-show="revenueOpen" x-cloak
                                     class="absolute top-full left-0 mt-0 w-36 bg-white border border-gray-200 rounded-md shadow-lg py-1 z-50">
                                    <a href="{{ route('revenue.index') }}"
                                       class="block px-4 py-2 text-sm {{ request()->routeIs('revenue.index') ? 'text-indigo-700 font-semibold bg-indigo-50' : 'text-gray-700 hover:bg-gray-50' }}">
                                        Revenue
                                    </a>
                                    <a href="{{ route('revenue.by-customer') }}"
                                       class="block px-4 py-2 text-sm {{ request()->routeIs('revenue.by-customer') ? 'text-indigo-700 font-semibold bg-indigo-50' : 'text-gray-700 hover:bg-gray-50' }}">
                                        By Customer
                                    </a>
                                    <a href="{{ route('statistics.index') }}"
                                       class="block px-4 py-2 text-sm {{ request()->routeIs('statistics.*') ? 'text-indigo-700 font-semibold bg-indigo-50' : 'text-gray-700 hover:bg-gray-50' }}">
                                        Statistics
                                    </a>
                                    <a href="{{ route('payroll.index') }}"
                                       class="block px-4 py-2 text-sm {{ request()->routeIs('payroll.*') ? 'text-indigo-700 font-semibold bg-indigo-50' : 'text-gray-700 hover:bg-gray-50' }}">
                                        Payroll
                                    </a>
                                </div>
                            </div>
                        @endif

                        {{-- Marketing (admin only) --}}
                        @if(auth()->user()?->isAdmin())
                            @php $marketingActive = request()->routeIs('marketing.*'); @endphp
                            <div class="relative flex items-center"
                                 x-data="{ marketingOpen: false }"
                                 @mouseenter="marketingOpen = true"
                                 @mouseleave="marketingOpen = false">
                                <button type="button"
                                    class="inline-flex items-center gap-1 px-1 pt-1 border-b-2 text-sm font-medium leading-5 focus:outline-none transition duration-150 ease-in-out {{ $marketingActive ? 'border-indigo-400 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                                    Marketing
                                    <svg class="w-3 h-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </button>
                                <div x-show="marketingOpen" x-cloak
                                     class="absolute top-full left-0 mt-0 w-48 bg-white border border-gray-200 rounded-md shadow-lg py-1 z-50">
                                    <a href="{{ route('marketing.partner-sites.index') }}"
                                       class="block px-4 py-2 text-sm {{ request()->routeIs('marketing.partner-sites.*') ? 'text-indigo-700 font-semibold bg-indigo-50' : 'text-gray-700 hover:bg-gray-50' }}">
                                        Partner Links
                                    </a>
                                    <a href="{{ route('marketing.email-campaigns.index') }}"
                                       class="block px-4 py-2 text-sm {{ request()->routeIs('marketing.email-campaigns.*') ? 'text-indigo-700 font-semibold bg-indigo-50' : 'text-gray-700 hover:bg-gray-50' }}">
                                        Email Campaigns
                                    </a>
                                    <a href="{{ route('marketing.email-templates.index') }}"
                                       class="block px-4 py-2 text-sm {{ request()->routeIs('marketing.email-templates.*') ? 'text-indigo-700 font-semibold bg-indigo-50' : 'text-gray-700 hover:bg-gray-50' }}">
                                        Email Templates
                                    </a>
                                    <a href="{{ route('marketing.base-email-template.edit') }}"
                                       class="block px-4 py-2 text-sm {{ request()->routeIs('marketing.base-email-template.*') ? 'text-indigo-700 font-semibold bg-indigo-50' : 'text-gray-700 hover:bg-gray-50' }}">
                                        Base Email Template
                                    </a>
                                </div>
                            </div>
                        @endif

                        {{-- Earnings tab (editors only) --}}
                        @if(auth()->user()?->isEditor())
                        <x-nav-link :href="route('editor-earnings.index')" :active="request()->routeIs('editor-earnings.*')">
                            Earnings
                        </x-nav-link>
                        @endif

                        {{-- Admin dropdown (Team, Archive, Ratebook, Reader Manual) --}}
                        @php
                            $adminActive = request()->routeIs('team.*') || request()->routeIs('readers.*') || request()->routeIs('archive.*') || request()->routeIs('ratebook.*') || request()->routeIs('manual.*') || request()->routeIs('admin.editors*') || request()->routeIs('settings.*') || request()->routeIs('test-data.*');
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
                                <a href="{{ route('settings.index') }}"
                                    class="block px-4 py-2 text-sm {{ request()->routeIs('settings.*') ? 'text-indigo-700 font-semibold bg-indigo-50' : 'text-gray-700 hover:bg-gray-50' }}">
                                    Settings
                                </a>
                                <a href="{{ route('archive.index') }}"
                                    class="block px-4 py-2 text-sm {{ request()->routeIs('archive.*') ? 'text-indigo-700 font-semibold bg-indigo-50' : 'text-gray-700 hover:bg-gray-50' }}">
                                    Archive
                                </a>
                                <a href="{{ route('ratebook.index') }}"
                                    class="block px-4 py-2 text-sm {{ request()->routeIs('ratebook.*') ? 'text-indigo-700 font-semibold bg-indigo-50' : 'text-gray-700 hover:bg-gray-50' }}">
                                    Rates
                                </a>
                                <a href="{{ route('manual.show') }}"
                                    class="block px-4 py-2 text-sm {{ request()->routeIs('manual.*') ? 'text-indigo-700 font-semibold bg-indigo-50' : 'text-gray-700 hover:bg-gray-50' }}">
                                    Reader Manual
                                </a>
                                <a href="{{ route('announcements.history') }}"
                                    class="block px-4 py-2 text-sm {{ request()->routeIs('announcements.history') ? 'text-indigo-700 font-semibold bg-indigo-50' : 'text-gray-700 hover:bg-gray-50' }}">
                                    Announcements
                                </a>
                                @if(\App\Support\Permission::check('team'))
                                <a href="{{ route('team.index') }}"
                                    class="block px-4 py-2 text-sm {{ request()->routeIs('team.*') ? 'text-indigo-700 font-semibold bg-indigo-50' : 'text-gray-700 hover:bg-gray-50' }}">
                                    Team
                                </a>
                                @endif
                                @if(auth()->user()?->isAdmin())
                                    <div class="my-1 border-t border-gray-100"></div>
                                    <a href="{{ route('test-data.index') }}"
                                        class="block px-4 py-2 text-sm {{ request()->routeIs('test-data.*') ? 'text-indigo-700 font-semibold bg-indigo-50' : 'text-gray-700 hover:bg-gray-50' }}">
                                        Test Data
                                    </a>
                                @endif
                            </div>
                        </div>
                    @else
                        @if(auth()->user()?->isReader())
                            <x-nav-link :href="route('reader-earnings.index')" :active="request()->routeIs('reader-earnings.*')">
                                {{ __('Earnings') }}
                            </x-nav-link>
                        @endif
                        @if(\App\Support\Permission::check('ratebook'))
                            <x-nav-link :href="route('ratebook.index')" :active="request()->routeIs('ratebook.*')">
                                {{ __('Rates') }}
                            </x-nav-link>
                        @endif
                        <x-nav-link :href="route('manual.show')" :active="request()->routeIs('manual.*')">
                            {{ __('Reader Manual') }}
                        </x-nav-link>
                        <x-nav-link :href="route('announcements.history')" :active="request()->routeIs('announcements.history')">
                            {{ __('Announcements') }}
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

                        <x-dropdown-link :href="route('notification-history.index')">
                            {{ __('Notification History') }}
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
                    <span class="inline-flex items-center gap-1.5">
                        {{ __('QC') }}
                        @if($qcCount > 0)
                            <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-green-500 text-white text-[9px] font-bold leading-none">{{ $qcCount }}</span>
                        @endif
                    </span>
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('woo-orders.index')" :active="request()->routeIs('woo-orders.*')">
                    {{ __('Woo Orders') }}
                </x-responsive-nav-link>
                @if(auth()->user()?->isAdmin())
                    <x-responsive-nav-link :href="route('order-log.index')" :active="request()->routeIs('order-log.*')">
                        {{ __('Order Log') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('read-credits.index')" :active="request()->routeIs('read-credits.*')">
                        {{ __('Read Credits') }}
                    </x-responsive-nav-link>
                @endif
                <x-responsive-nav-link :href="route('clients.index')" :active="request()->routeIs('clients.*')">
                    {{ __('Clients') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('invoicing.index')" :active="request()->routeIs('invoicing.index')">
                    {{ __('All Invoices') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('invoicing.create')" :active="request()->routeIs('invoicing.create')">
                    {{ __('Create Invoice') }}
                </x-responsive-nav-link>
                @if(auth()->user()?->isAdmin())
                    <x-responsive-nav-link :href="route('revenue.index')" :active="request()->routeIs('revenue.*')">
                        {{ __('Revenue') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('statistics.index')" :active="request()->routeIs('statistics.*')">
                        {{ __('Statistics') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('marketing.partner-sites.index')" :active="request()->routeIs('marketing.partner-sites.*')">
                        {{ __('Partner Links') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('marketing.email-campaigns.index')" :active="request()->routeIs('marketing.email-campaigns.*')">
                        {{ __('Email Campaigns') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('marketing.email-templates.index')" :active="request()->routeIs('marketing.email-templates.*')">
                        {{ __('Email Templates') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('marketing.base-email-template.edit')" :active="request()->routeIs('marketing.base-email-template.*')">
                        {{ __('Base Email Template') }}
                    </x-responsive-nav-link>
                @endif

                <div class="px-4 pt-3 pb-1 text-xs font-semibold text-gray-400 uppercase tracking-wide">Admin</div>
                <x-responsive-nav-link :href="route('archive.index')" :active="request()->routeIs('archive.*')">
                    {{ __('Archive') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('ratebook.index')" :active="request()->routeIs('ratebook.*')">
                    {{ __('Rates') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('manual.show')" :active="request()->routeIs('manual.*')">
                    {{ __('Reader Manual') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('settings.index')" :active="request()->routeIs('settings.*')">
                    {{ __('Settings') }}
                </x-responsive-nav-link>
                @if(\App\Support\Permission::check('team'))
                <x-responsive-nav-link :href="route('team.index')" :active="request()->routeIs('team.*')">
                    {{ __('Team') }}
                </x-responsive-nav-link>
                @endif
                @if(auth()->user()?->isEditor() && !auth()->user()?->isAdmin())
                    <x-responsive-nav-link :href="route('editor-earnings.index')" :active="request()->routeIs('editor-earnings.*')">
                        {{ __('Earnings') }}
                    </x-responsive-nav-link>
                @endif
                @if(auth()->user()?->isAdmin())
                    <x-responsive-nav-link :href="route('payroll.index')" :active="request()->routeIs('payroll.*')">
                        {{ __('Payroll') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('test-data.index')" :active="request()->routeIs('test-data.*')">
                        {{ __('Test Data') }}
                    </x-responsive-nav-link>
                @endif
                @if(session('impersonator_id'))
                    <div class="px-4 py-2">
                        <form method="POST" action="{{ route('impersonate.stop') }}">
                            @csrf
                            <button type="submit"
                                    class="w-full text-left text-sm font-medium text-yellow-700 bg-yellow-50 px-3 py-2 rounded-md">
                                ← Return to Admin (stop viewing as {{ auth()->user()->name }})
                            </button>
                        </form>
                    </div>
                @endif
            @else
                @if(auth()->user()?->isReader())
                    <x-responsive-nav-link :href="route('reader-earnings.index')" :active="request()->routeIs('reader-earnings.*')">
                        {{ __('Earnings') }}
                    </x-responsive-nav-link>
                @endif
                @if(\App\Support\Permission::check('ratebook'))
                    <x-responsive-nav-link :href="route('ratebook.index')" :active="request()->routeIs('ratebook.*')">
                        {{ __('Rates') }}
                    </x-responsive-nav-link>
                @endif
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

                <x-responsive-nav-link :href="route('notification-history.index')">
                    {{ __('Notification History') }}
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
