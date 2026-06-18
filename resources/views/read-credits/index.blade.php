<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Read Credits</h2>
            <div class="flex flex-col sm:flex-row gap-2">
                <form method="GET" action="{{ route('read-credits.index') }}" class="flex gap-2">
                    <input type="hidden" name="status" value="{{ $status }}">
                    <x-text-input
                        name="q"
                        value="{{ $q }}"
                        placeholder="Email, name, order #…"
                        class="text-sm h-9 py-1.5 w-56"
                    />
                    <x-primary-button class="h-9 py-1.5 text-sm">Search</x-primary-button>
                    @if($q)
                        <a href="{{ route('read-credits.index', ['status' => $status]) }}"
                           class="inline-flex items-center h-9 px-3 text-sm text-gray-500 hover:text-gray-700">
                            Clear
                        </a>
                    @endif
                </form>

                <form method="GET" action="{{ route('read-credits.index') }}" id="status-form">
                    <input type="hidden" name="q" value="{{ $q }}">
                    <select name="status" onchange="document.getElementById('status-form').submit()"
                        class="h-9 rounded-md border-gray-300 shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="all" @selected($status === 'all')>All Statuses</option>
                        <option value="active" @selected($status === 'active')>Active</option>
                        <option value="expired" @selected($status === 'expired')>Expired</option>
                        <option value="exhausted" @selected($status === 'exhausted')>Exhausted</option>
                    </select>
                </form>

                <a href="{{ route('read-credits.create') }}"
                   class="inline-flex items-center h-9 px-4 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 transition">
                    + Create
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-4 px-4 py-3 rounded bg-green-50 border border-green-200 text-green-800 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="overflow-x-auto">

                    @if($packages->total() > 0)
                        <div class="px-4 py-2 border-b border-gray-100 text-xs text-gray-500">
                            {{ number_format($packages->total()) }} package{{ $packages->total() === 1 ? '' : 's' }}
                        </div>
                    @endif

                    <table class="min-w-full text-sm divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Customer</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Email</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-24">Order #</th>
                                <th class="px-4 py-2 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider w-24">Package</th>
                                <th class="px-4 py-2 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider w-28">Credits</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-36">Upload URL</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-28">Expires</th>
                                <th class="px-4 py-2 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider w-24">Status</th>
                                <th class="px-4 py-2 w-16"></th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            @forelse($packages as $pkg)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2.5 text-gray-900">{{ $pkg->customer_name }}</td>
                                    <td class="px-4 py-2.5 text-gray-500">{{ $pkg->customer_email }}</td>
                                    <td class="px-4 py-2.5 text-gray-500 font-mono text-xs">{{ $pkg->woo_order_number }}</td>
                                    <td class="px-4 py-2.5 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                            {{ $pkg->packageLabel() }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5 text-center font-medium {{ $pkg->credits_remaining > 0 ? 'text-green-700' : 'text-gray-400' }}">
                                        {{ $pkg->credits_remaining }} / {{ $pkg->credits_purchased }}
                                    </td>
                                    <td class="px-4 py-2.5" x-data="{ copied: false }">
                                        <button type="button"
                                            @click="navigator.clipboard.writeText(@js($pkg->uploadUrl())).then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
                                            class="text-xs text-indigo-600 hover:text-indigo-800 underline">
                                            <span x-show="!copied">Copy URL</span>
                                            <span x-show="copied" x-cloak class="text-green-600">Copied!</span>
                                        </button>
                                    </td>
                                    <td class="px-4 py-2.5 text-xs text-gray-500">
                                        {{ $pkg->expires_at->format('M j, Y') }}
                                        @if($pkg->expires_at->isPast())
                                            <span class="text-red-500 font-medium">(expired)</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5 text-center">
                                        @php
                                            $badge = match($pkg->status) {
                                                'active'    => 'bg-green-100 text-green-800',
                                                'expired'   => 'bg-gray-100 text-gray-600',
                                                'exhausted' => 'bg-amber-100 text-amber-800',
                                                default     => 'bg-gray-100 text-gray-600',
                                            };
                                        @endphp
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $badge }}">
                                            {{ ucfirst($pkg->status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5 text-right">
                                        <a href="{{ route('read-credits.edit', $pkg) }}"
                                           class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">Edit</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-4 py-8 text-center text-gray-400">No credit packages found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($packages->hasPages())
                    <div class="px-4 py-3 border-t border-gray-100">
                        {{ $packages->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
