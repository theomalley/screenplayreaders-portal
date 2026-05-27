<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Team</h2>
            <div class="flex items-center gap-2">
                @if(auth()->user()->isAdmin())
                    <a href="{{ route('admin.editors.create') }}"
                       class="inline-flex items-center px-4 py-2 bg-indigo-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-600 transition ease-in-out duration-150">
                        + Add Editor
                    </a>
                @endif
                @if(auth()->user()->canManageAssignments())
                    <a href="{{ route('readers.create') }}"
                       class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition ease-in-out duration-150">
                        + Add Reader
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

            @if (session('success'))
                <div class="px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded-md text-sm">
                    {{ session('success') }}
                </div>
            @endif
            @if (session('error'))
                <div class="px-4 py-3 bg-red-50 border border-red-200 text-red-800 rounded-md text-sm">
                    {{ session('error') }}
                </div>
            @endif

            {{-- ── Editors ────────────────────────────────────────────── --}}
            <div>
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Editors</h3>

                @if ($editors->isEmpty())
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8 text-center text-gray-500 text-sm">
                        No editors yet.
                        @if(auth()->user()->isAdmin())
                            <a href="{{ route('admin.editors.create') }}" class="text-indigo-600 hover:underline">Add one.</a>
                        @endif
                    </div>
                @else
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Editor</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Active</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Completed</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Total</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Availability</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PayPal</th>
                                    @if($canEditEditors || $canDeleteEditors)
                                        <th class="px-4 py-3"></th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                @foreach ($editors as $editor)
                                    @php
                                        $profile  = $editor->editorProfile;
                                        $initials = $profile?->initials ?? strtoupper(substr($editor->name, 0, 2));
                                        $photoUrl = $profile?->photo ? asset('storage/' . $profile->photo) : null;
                                    @endphp
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-3">
                                                <span class="relative inline-flex items-center justify-center w-8 h-8 rounded-full bg-indigo-100 text-indigo-700 text-xs font-mono font-semibold shrink-0">
                                                    @if ($photoUrl)
                                                        <span class="absolute inset-0 rounded-full overflow-hidden">
                                                            <img src="{{ $photoUrl }}" alt="{{ $initials }}" class="w-full h-full object-cover" />
                                                        </span>
                                                    @else
                                                        {{ $initials }}
                                                    @endif
                                                    @if($editor->isOnline())
                                                        <span class="absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full bg-green-400 ring-2 ring-white z-10"></span>
                                                    @endif
                                                </span>
                                                <div class="font-medium text-gray-900">
                                                    {{ $profile?->displayName() ?? $editor->name }}
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-gray-500">
                                            <a href="mailto:{{ $editor->email }}" class="hover:text-indigo-600 hover:underline">{{ $editor->email }}</a>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700 tabular-nums">{{ $editor->active_count }}</td>
                                        <td class="px-4 py-3 text-gray-700 tabular-nums">{{ $editor->completed_count }}</td>
                                        <td class="px-4 py-3 text-gray-500 tabular-nums">{{ $editor->total_count }}</td>
                                        <td class="px-4 py-3">
                                            @php $avail = $profile?->availability ?? 'available'; @endphp
                                            <span class="inline-flex items-center gap-1 text-xs font-medium {{ $avail === 'available' ? 'text-green-700' : 'text-red-700' }}">
                                                <span class="w-1.5 h-1.5 rounded-full {{ $avail === 'available' ? 'bg-green-500' : 'bg-red-500' }}"></span>
                                                {{ ucfirst($avail) }}
                                            </span>
                                            @if ($profile?->availability_message)
                                                <div class="mt-0.5 text-[11px] text-gray-400 max-w-[160px] truncate" title="{{ $profile->availability_message }}">
                                                    {{ $profile->availability_message }}
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-gray-500 text-xs">{{ $profile?->paypal_email ?? '—' }}</td>
                                        @if($canEditEditors || $canDeleteEditors)
                                            <td class="px-4 py-3 whitespace-nowrap text-right"
                                                x-data="{ open: false, typed: '' }">
                                                <div class="flex items-center justify-end gap-2">
                                                    @if($canEditEditors)
                                                        <a href="{{ route('admin.editors.edit', $editor) }}"
                                                           class="inline-flex items-center px-2.5 py-1 bg-white border border-gray-300 rounded text-xs font-medium text-gray-700 hover:bg-gray-50 transition">
                                                            Edit
                                                        </a>
                                                    @endif
                                                    @if($canDeleteEditors)
                                                        <form method="POST" action="{{ route('admin.editors.destroy', $editor) }}" x-ref="deleteForm">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="button" @click="open = true; typed = ''"
                                                                    class="inline-flex items-center px-2.5 py-1 bg-white border border-red-300 rounded text-xs font-medium text-red-600 hover:bg-red-50 transition">
                                                                Delete
                                                            </button>
                                                        </form>
                                                        <div x-show="open" x-cloak
                                                             @keydown.escape.window="open = false"
                                                             class="fixed inset-0 z-50 flex items-center justify-center bg-black/60">
                                                            <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4 p-6" @click.stop>
                                                                <h3 class="text-base font-semibold text-gray-900 mb-1">Delete Editor</h3>
                                                                <p class="text-sm text-gray-600 mb-4">
                                                                    This will permanently delete <strong>{{ $profile?->displayName() ?? $editor->name }}</strong>. This cannot be undone.
                                                                </p>
                                                                <p class="text-sm text-gray-700 mb-2">
                                                                    Type <span class="font-mono font-semibold text-red-600">DELETE EDITOR</span> to confirm:
                                                                </p>
                                                                <input type="text" x-model="typed"
                                                                       placeholder="DELETE EDITOR"
                                                                       @keydown.enter="if (typed === 'DELETE EDITOR') $refs.deleteForm.submit()"
                                                                       class="w-full border border-gray-300 rounded px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-red-400 mb-4" />
                                                                <div class="flex justify-end gap-3">
                                                                    <button type="button" @click="open = false; typed = ''"
                                                                            class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition">
                                                                        Cancel
                                                                    </button>
                                                                    <button type="button"
                                                                            :disabled="typed !== 'DELETE EDITOR'"
                                                                            @click="$refs.deleteForm.submit()"
                                                                            class="px-4 py-2 text-sm font-semibold text-white bg-red-600 rounded hover:bg-red-500 disabled:opacity-40 disabled:cursor-not-allowed transition">
                                                                        Delete Editor
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endif
                                                </div>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- ── Readers ────────────────────────────────────────────── --}}
            <div>
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Readers</h3>

                @if ($readers->isEmpty())
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8 text-center text-gray-500 text-sm">
                        No readers yet.
                        @if(auth()->user()->canManageAssignments())
                            <a href="{{ route('readers.create') }}" class="text-indigo-600 hover:underline">Add one.</a>
                        @endif
                    </div>
                @else
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reader</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Active</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Completed</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Total</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Capacity</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Availability</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PayPal</th>
                                    @if($canEditReaders || $canDeleteReaders)
                                        <th class="px-4 py-3"></th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                @foreach ($readers as $reader)
                                    @php
                                        $profile   = $reader->readerProfile;
                                        $initials  = $profile?->initials ?? strtoupper(substr($reader->name, 0, 2));
                                        $max       = $profile?->max_concurrent_assignments ?? 0;
                                        $active    = $reader->active_count;
                                        $atCap     = $max > 0 && $active >= $max;
                                        $photoUrl  = $profile?->photo ? asset('storage/' . $profile->photo) : null;
                                    @endphp
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-3">
                                                <span class="relative inline-flex items-center justify-center w-8 h-8 rounded-full bg-gray-200 text-gray-700 text-xs font-mono font-semibold shrink-0">
                                                    @if ($photoUrl)
                                                        <span class="absolute inset-0 rounded-full overflow-hidden">
                                                            <img src="{{ $photoUrl }}" alt="{{ $initials }}" class="w-full h-full object-cover" />
                                                        </span>
                                                    @else
                                                        {{ $initials }}
                                                    @endif
                                                    @if($reader->isOnline())
                                                        <span class="absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full bg-green-400 ring-2 ring-white z-10"></span>
                                                    @endif
                                                </span>
                                                <div class="font-medium text-gray-900">
                                                    {{ $profile?->displayName() ?? $reader->name }}
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-gray-500">
                                            <a href="mailto:{{ $reader->email }}" class="hover:text-indigo-600 hover:underline">{{ $reader->email }}</a>
                                        </td>
                                        <td class="px-4 py-3 tabular-nums">
                                            <span class="{{ $atCap ? 'text-amber-700 font-semibold' : 'text-gray-700' }}">{{ $active }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700 tabular-nums">{{ $reader->completed_count }}</td>
                                        <td class="px-4 py-3 text-gray-500 tabular-nums">{{ $reader->total_count }}</td>
                                        <td class="px-4 py-3 text-gray-500 tabular-nums">
                                            {{ $active }}/{{ $max ?: '—' }}
                                            @if ($atCap)
                                                <span class="ml-1 text-[10px] font-bold text-amber-600 uppercase">Full</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            @php $avail = $profile?->availability ?? 'available'; @endphp
                                            <span class="inline-flex items-center gap-1 text-xs font-medium {{ $avail === 'available' ? 'text-green-700' : 'text-red-700' }}">
                                                <span class="w-1.5 h-1.5 rounded-full {{ $avail === 'available' ? 'bg-green-500' : 'bg-red-500' }}"></span>
                                                {{ ucfirst($avail) }}
                                            </span>
                                            @if ($profile?->availability_message)
                                                <div class="mt-0.5 text-[11px] text-gray-400 max-w-[160px] truncate" title="{{ $profile->availability_message }}">
                                                    {{ $profile->availability_message }}
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-gray-500 text-xs">{{ $profile?->paypal_email ?? '—' }}</td>
                                        @if($canEditReaders || $canDeleteReaders)
                                            <td class="px-4 py-3 whitespace-nowrap text-right"
                                                x-data="{ open: false, typed: '' }">
                                                <div class="flex items-center justify-end gap-2">
                                                    @if($canEditReaders)
                                                        <a href="{{ route('readers.edit', $reader) }}"
                                                           class="inline-flex items-center px-2.5 py-1 bg-white border border-gray-300 rounded text-xs font-medium text-gray-700 hover:bg-gray-50 transition">
                                                            Edit
                                                        </a>
                                                    @endif
                                                    @if($canDeleteReaders)
                                                        <form method="POST" action="{{ route('readers.destroy', $reader) }}" x-ref="deleteForm">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="button" @click="open = true; typed = ''"
                                                                    class="inline-flex items-center px-2.5 py-1 bg-white border border-red-300 rounded text-xs font-medium text-red-600 hover:bg-red-50 transition">
                                                                Delete
                                                            </button>
                                                        </form>
                                                        <div x-show="open" x-cloak
                                                             @keydown.escape.window="open = false"
                                                             class="fixed inset-0 z-50 flex items-center justify-center bg-black/60">
                                                            <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4 p-6" @click.stop>
                                                                <h3 class="text-base font-semibold text-gray-900 mb-1">Delete Reader</h3>
                                                                <p class="text-sm text-gray-600 mb-4">
                                                                    This will permanently delete <strong>{{ $profile?->displayName() ?? $reader->name }}</strong>. This cannot be undone.
                                                                </p>
                                                                <p class="text-sm text-gray-700 mb-2">
                                                                    Type <span class="font-mono font-semibold text-red-600">DELETE READER</span> to confirm:
                                                                </p>
                                                                <input type="text" x-model="typed"
                                                                       placeholder="DELETE READER"
                                                                       @keydown.enter="if (typed === 'DELETE READER') $refs.deleteForm.submit()"
                                                                       class="w-full border border-gray-300 rounded px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-red-400 mb-4" />
                                                                <div class="flex justify-end gap-3">
                                                                    <button type="button" @click="open = false; typed = ''"
                                                                            class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition">
                                                                        Cancel
                                                                    </button>
                                                                    <button type="button"
                                                                            :disabled="typed !== 'DELETE READER'"
                                                                            @click="$refs.deleteForm.submit()"
                                                                            class="px-4 py-2 text-sm font-semibold text-white bg-red-600 rounded hover:bg-red-500 disabled:opacity-40 disabled:cursor-not-allowed transition">
                                                                        Delete Reader
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endif
                                                </div>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
