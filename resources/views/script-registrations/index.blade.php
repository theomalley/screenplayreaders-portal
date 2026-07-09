<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Script Registrations</h2>
            <div class="flex flex-col sm:flex-row gap-2">
                <form method="GET" action="{{ route('script-registrations.index') }}" class="flex gap-2">
                    <input type="hidden" name="status" value="{{ $status }}">
                    <input type="hidden" name="variation" value="{{ $variation }}">
                    <x-text-input
                        name="q"
                        value="{{ $q }}"
                        placeholder="Email, name, reg ID, order #, title…"
                        class="text-sm h-9 py-1.5 w-64"
                    />
                    <x-primary-button class="h-9 py-1.5 text-sm">Search</x-primary-button>
                    @if($q)
                        <a href="{{ route('script-registrations.index', ['status' => $status, 'variation' => $variation]) }}"
                           class="inline-flex items-center h-9 px-3 text-sm text-gray-500 hover:text-gray-700">
                            Clear
                        </a>
                    @endif
                </form>

                <form method="GET" action="{{ route('script-registrations.index') }}" id="status-form">
                    <input type="hidden" name="q" value="{{ $q }}">
                    <input type="hidden" name="variation" value="{{ $variation }}">
                    <select name="status" onchange="document.getElementById('status-form').submit()"
                        class="h-9 rounded-md border-gray-300 shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="all" @selected($status === 'all')>All Statuses</option>
                        <option value="pending" @selected($status === 'pending')>Pending</option>
                        <option value="completed" @selected($status === 'completed')>Completed</option>
                        <option value="failed" @selected($status === 'failed')>Failed</option>
                    </select>
                </form>

                <form method="GET" action="{{ route('script-registrations.index') }}" id="variation-form">
                    <input type="hidden" name="q" value="{{ $q }}">
                    <input type="hidden" name="status" value="{{ $status }}">
                    <select name="variation" onchange="document.getElementById('variation-form').submit()"
                        class="h-9 rounded-md border-gray-300 shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="all" @selected($variation === 'all')>All Variations</option>
                        @foreach($variationOptions as $opt)
                            <option value="{{ $opt->variation_id }}" @selected($variation == $opt->variation_id)>{{ $opt->variation_label }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
        </div>
    </x-slot>

    @php $isAdmin = auth()->user()?->isAdmin(); @endphp

    <div class="py-8" @if($isAdmin) x-data="{
        selected: [],
        allIds: @js($registrations->pluck('id')->values()),
        get allChecked() { return this.allIds.length > 0 && this.selected.length === this.allIds.length },
        toggleAll() {
            this.selected = this.allChecked ? [] : [...this.allIds];
        },
        toggle(id) {
            const i = this.selected.indexOf(id);
            i === -1 ? this.selected.push(id) : this.selected.splice(i, 1);
        }
    }" @endif>
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-4 px-4 py-3 rounded bg-green-50 border border-green-200 text-green-800 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Bulk action bar --}}
            @if($isAdmin)
            <div x-show="selected.length > 0" x-cloak
                 class="mb-3 flex items-center gap-3 px-4 py-2.5 bg-indigo-50 border border-indigo-200 rounded-lg text-sm">
                <span class="text-indigo-800 font-medium" x-text="selected.length + ' selected'"></span>
                <form method="POST" action="{{ route('script-registrations.bulk-destroy') }}"
                      @submit.prevent="if(confirm('Delete ' + selected.length + ' registration(s)? This cannot be undone.')) { $el.submit() }">
                    @csrf
                    <template x-for="id in selected" :key="id">
                        <input type="hidden" name="ids[]" :value="id">
                    </template>
                    <button type="submit"
                            class="inline-flex items-center gap-1 px-3 py-1.5 bg-red-600 border border-transparent rounded text-xs font-medium text-white hover:bg-red-700 transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        Delete Selected
                    </button>
                </form>
                <button type="button" @click="selected = []" class="text-xs text-indigo-600 hover:text-indigo-800">Clear</button>
            </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="overflow-x-auto">

                    @if($registrations->total() > 0)
                        <div class="px-4 py-2 border-b border-gray-100 text-xs text-gray-500">
                            {{ number_format($registrations->total()) }} registration{{ $registrations->total() === 1 ? '' : 's' }}
                        </div>
                    @endif

                    <table class="min-w-full text-sm divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                @if($isAdmin)
                                <th class="px-3 py-2 w-10">
                                    <input type="checkbox" :checked="allChecked" @click="toggleAll()"
                                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                </th>
                                @endif
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-28">Order #</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-28">Reg ID</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Title</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Author</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Email</th>
                                <th class="px-4 py-2 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider w-24">Variation</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-28">Registered</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-28">Expires</th>
                                <th class="px-4 py-2 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider w-24">Status</th>
                                <th class="px-4 py-2 w-16"></th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            @forelse($registrations as $reg)
                                <tr class="hover:bg-gray-50"
                                    @if($isAdmin) :class="selected.includes({{ $reg->id }}) ? 'bg-indigo-50/50' : ''" @endif>
                                    @if($isAdmin)
                                    <td class="px-3 py-2.5" onclick="event.stopPropagation()">
                                        <input type="checkbox" :checked="selected.includes({{ $reg->id }})" @click="toggle({{ $reg->id }})"
                                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                    </td>
                                    @endif
                                    <td class="px-4 py-2.5 font-mono text-xs text-gray-900">{{ $reg->woo_order_number ?? '—' }}</td>
                                    <td class="px-4 py-2.5 font-mono text-xs text-gray-900">
                                        {{ $reg->registration_id }}
                                        @if($reg->children_count > 0)
                                            <span class="ml-1 text-[10px] text-indigo-500" title="{{ $reg->children_count }} child registration(s)">+{{ $reg->children_count }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5 text-gray-900 max-w-48 truncate">{{ $reg->script_title }}</td>
                                    <td class="px-4 py-2.5 text-gray-700">{{ $reg->author_first }} {{ $reg->author_last }}</td>
                                    <td class="px-4 py-2.5 text-gray-500">{{ $reg->email }}</td>
                                    <td class="px-4 py-2.5 text-center">
                                        @php
                                            $varBadge = match($reg->variation_id) {
                                                \App\Models\ScriptRegistration::VAR_FREE_90  => 'bg-gray-100 text-gray-700',
                                                \App\Models\ScriptRegistration::VAR_5YR      => 'bg-blue-100 text-blue-800',
                                                \App\Models\ScriptRegistration::VAR_10YR     => 'bg-indigo-100 text-indigo-800',
                                                \App\Models\ScriptRegistration::VAR_LIFETIME => 'bg-purple-100 text-purple-800',
                                                default => 'bg-gray-100 text-gray-600',
                                            };
                                        @endphp
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $varBadge }}">
                                            {{ $reg->variation_label }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5 text-xs text-gray-500">{{ $reg->registered_at->format('M j, Y') }}</td>
                                    <td class="px-4 py-2.5 text-xs text-gray-500">
                                        @if($reg->expires_at)
                                            {{ $reg->expires_at->format('M j, Y') }}
                                            @if($reg->isExpired())
                                                <span class="text-red-500 font-medium">(expired)</span>
                                            @endif
                                        @else
                                            <span class="text-purple-600 font-medium">Never</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5 text-center">
                                        @php
                                            $statusBadge = match($reg->status) {
                                                'completed' => 'bg-green-100 text-green-800',
                                                'pending'   => 'bg-amber-100 text-amber-800',
                                                'failed'    => 'bg-red-100 text-red-800',
                                                default     => 'bg-gray-100 text-gray-600',
                                            };
                                        @endphp
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $statusBadge }}">
                                            {{ ucfirst($reg->status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5 text-right">
                                        <a href="{{ route('script-registrations.show', $reg) }}"
                                           class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">View</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $isAdmin ? 11 : 10 }}" class="px-4 py-8 text-center text-gray-400">No script registrations found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($registrations->hasPages())
                    <div class="px-4 py-3 border-t border-gray-100">
                        {{ $registrations->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
