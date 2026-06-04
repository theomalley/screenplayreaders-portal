<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Team
                @if (($pendingApprovals ?? 0) > 0)
                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">{{ $pendingApprovals }} pending</span>
                @endif
            </h2>
            <div class="flex items-center gap-2">
                @if(auth()->user()->canManageAssignments())
                    <div x-data="{ loading: false, error: '' }" class="relative">
                        <button type="button"
                                @click="
                                    loading = true; error = '';
                                    fetch('{{ route('settings.email-all-readers') }}', {
                                        method: 'POST',
                                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' }
                                    })
                                    .then(r => r.json())
                                    .then(d => { loading = false; if (d.url) window.open(d.url, '_blank'); else error = d.error ?? 'Unknown error'; })
                                    .catch(e => { loading = false; error = e.message; })
                                "
                                :disabled="loading"
                                :class="loading ? 'opacity-50 cursor-not-allowed' : 'hover:bg-teal-600'"
                                class="inline-flex items-center px-4 py-2 bg-teal-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest transition ease-in-out duration-150">
                            <span x-text="loading ? 'Opening…' : 'Email All Readers'"></span>
                        </button>
                        <p x-show="error" x-cloak x-text="error"
                           class="absolute right-0 top-full mt-1 text-xs text-red-600 bg-white border border-red-200 rounded px-2 py-1 whitespace-nowrap shadow-sm z-10"></p>
                    </div>
                @endif
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

            {{-- ── Admins ─────────────────────────────────────────────── --}}
            @if ($admins->isNotEmpty())
            <div>
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Admins</h3>
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <table class="w-full divide-y divide-gray-200 text-sm" style="table-layout:fixed">
                        <colgroup>
                            <col style="width:32%">
                            <col style="width:20%">
                            <col style="width:28%">
                            <col style="width:20%">
                        </colgroup>
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Admin</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Availability</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            @foreach ($admins as $admin)
                                @php
                                    $profile  = $admin->editorProfile;
                                    $initials = $profile?->initials ?? strtoupper(substr($admin->name, 0, 2));
                                    $photoUrl = $profile?->photo ? asset('storage/' . $profile->photo) : null;
                                    $avail    = $profile?->availability ?? 'available';
                                    $canEdit  = auth()->user()->isAdmin();
                                @endphp
                                <tr class="hover:bg-gray-50 {{ $canEdit ? 'cursor-pointer' : '' }}"
                                    {{ $canEdit ? 'onclick=window.location.href=\''.route('admin.editors.edit', $admin).'\'' : '' }}>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <span class="relative inline-flex items-center justify-center w-8 h-8 rounded-full bg-violet-100 text-violet-700 text-xs font-mono font-semibold shrink-0">
                                                @if ($photoUrl)
                                                    <span class="absolute inset-0 rounded-full overflow-hidden">
                                                        <img src="{{ $photoUrl }}" alt="{{ $initials }}" class="w-full h-full object-cover" />
                                                    </span>
                                                @else
                                                    {{ $initials }}
                                                @endif
                                                @if($admin->isOnline())
                                                    <span class="absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full bg-green-400 ring-2 ring-white z-10"></span>
                                                @endif
                                            </span>
                                            <div>
                                                <div class="font-medium text-gray-900">{{ $profile?->displayName() ?? $admin->name }}</div>
                                                <div class="text-[11px] text-violet-500 font-medium">Admin</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-500 text-sm">
                                        {{ $profile?->title ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-500">
                                        <a href="mailto:{{ $admin->email }}" class="hover:text-indigo-600 hover:underline" onclick="event.stopPropagation()">{{ $admin->email }}</a>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center gap-1 text-xs font-medium {{ $avail === 'available' ? 'text-green-700' : 'text-red-700' }}">
                                            <span class="w-1.5 h-1.5 rounded-full {{ $avail === 'available' ? 'bg-green-500' : 'bg-red-500' }}"></span>
                                            {{ ucfirst($avail) }}
                                        </span>
                                        @if ($profile?->availability_message)
                                            <div class="mt-0.5 text-[11px] text-gray-400 max-w-[160px] truncate" title="{{ $profile->availability_message }}">
                                                {{ $profile->availability_message }}
                                            </div>
                                        @endif
                                        @if($authUser->isAdmin())
                                        <form method="POST" action="{{ route('team.toggle-visibility', $admin) }}" onclick="event.stopPropagation()" class="mt-1.5">
                                            @csrf
                                            <button type="submit" class="text-[10px] px-2 py-0.5 rounded border {{ $admin->hidden_from_staff ? 'border-amber-300 text-amber-600 bg-amber-50 hover:bg-amber-100' : 'border-gray-200 text-gray-400 hover:text-gray-600 hover:border-gray-300' }}">
                                                {{ $admin->hidden_from_staff ? 'Hidden' : 'Visible' }}
                                            </button>
                                        </form>
                                        @endif
                                        <div class="mt-1 text-[10px] text-gray-400">{{ $admin->lastOnlineText() }}</div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
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
                        <table class="w-full divide-y divide-gray-200 text-sm" style="table-layout:fixed">
                            <colgroup>
                                <col style="width:32%">
                                <col style="width:20%">
                                <col style="width:28%">
                                <col style="width:20%">
                            </colgroup>
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Editor</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Availability</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                @foreach ($editors as $editor)
                                    @php
                                        $profile    = $editor->editorProfile;
                                        $initials   = $profile?->initials ?? strtoupper(substr($editor->name, 0, 2));
                                        $photoUrl   = $profile?->photo ? asset('storage/' . $profile->photo) : null;
                                        $avail      = $profile?->availability ?? 'available';
                                        $hasPending = $profile?->bio_pending !== null || (bool) $profile?->photo_pending;
                                    @endphp
                                    <tr class="hover:bg-gray-50 {{ $canEditEditors ? 'cursor-pointer' : '' }}"
                                        {{ $canEditEditors ? 'onclick=window.location.href=\''.route('admin.editors.edit', $editor).'\'' : '' }}>
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
                                                <div class="font-medium text-gray-900 flex items-center gap-1.5">
                                                    {{ $profile?->displayName() ?? $editor->name }}
                                                    @if ($hasPending)
                                                        <span class="inline-block w-2 h-2 rounded-full bg-amber-400 shrink-0" title="Has pending bio or photo changes"></span>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-gray-500 text-sm">
                                            {{ $profile?->title ?? '—' }}
                                        </td>
                                        <td class="px-4 py-3 text-gray-500">
                                            <a href="mailto:{{ $editor->email }}" class="hover:text-indigo-600 hover:underline" onclick="event.stopPropagation()">{{ $editor->email }}</a>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center gap-1 text-xs font-medium {{ $avail === 'available' ? 'text-green-700' : 'text-red-700' }}">
                                                <span class="w-1.5 h-1.5 rounded-full {{ $avail === 'available' ? 'bg-green-500' : 'bg-red-500' }}"></span>
                                                {{ ucfirst($avail) }}
                                            </span>
                                            @if ($profile?->availability_message)
                                                <div class="mt-0.5 text-[11px] text-gray-400 max-w-[160px] truncate" title="{{ $profile->availability_message }}">
                                                    {{ $profile->availability_message }}
                                                </div>
                                            @endif
                                            <div class="mt-1 text-[10px] text-gray-400">{{ $editor->lastOnlineText() }}</div>
                                            @if($authUser->isAdmin())
                                            <form method="POST" action="{{ route('team.toggle-visibility', $editor) }}" onclick="event.stopPropagation()" class="mt-1.5">
                                                @csrf
                                                <button type="submit" class="text-[10px] px-2 py-0.5 rounded border {{ $editor->hidden_from_staff ? 'border-amber-300 text-amber-600 bg-amber-50 hover:bg-amber-100' : 'border-gray-200 text-gray-400 hover:text-gray-600 hover:border-gray-300' }}">
                                                    {{ $editor->hidden_from_staff ? 'Hidden' : 'Visible' }}
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('impersonate.start', $editor) }}" onclick="event.stopPropagation()" class="mt-1">
                                                @csrf
                                                <button type="submit" class="text-[10px] px-2 py-0.5 rounded border border-yellow-300 text-yellow-700 bg-yellow-50 hover:bg-yellow-100 whitespace-nowrap">
                                                    👁 View as
                                                </button>
                                            </form>
                                            @endif
                                        </td>
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
                        <table class="w-full divide-y divide-gray-200 text-sm" style="table-layout:fixed">
                            <colgroup>
                                <col style="width:32%">
                                <col style="width:20%">
                                <col style="width:28%">
                                <col style="width:20%">
                            </colgroup>
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reader</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Availability</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                @foreach ($readers as $reader)
                                    @php
                                        $profile      = $reader->readerProfile;
                                        $initials     = $profile?->initials ?? strtoupper(substr($reader->name, 0, 2));
                                        $photoUrl     = $profile?->photo ? asset('storage/' . $profile->photo) : null;
                                        $avail        = $profile?->availability ?? 'available';
                                        $hasPending   = $profile?->bio_pending !== null || (bool) $profile?->photo_pending;
                                    @endphp
                                    <tr class="hover:bg-gray-50 {{ $canEditReaders ? 'cursor-pointer' : '' }}"
                                        {{ $canEditReaders ? 'onclick=window.location.href=\''.route('readers.edit', $reader).'\'' : '' }}>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-3">
                                                <span class="relative inline-flex items-center justify-center w-8 h-8 rounded-full bg-gray-200 text-gray-700 text-xs font-mono font-semibold shrink-0 {{ $avail !== 'available' ? 'border-2 border-dashed border-red-300' : '' }}">
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
                                                <div class="font-medium text-gray-900 flex items-center gap-1.5">
                                                    {{ $profile?->displayName() ?? $reader->name }}
                                                    @if ($hasPending)
                                                        <span class="inline-block w-2 h-2 rounded-full bg-amber-400 shrink-0" title="Has pending bio or photo changes"></span>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-gray-500 text-sm">
                                            {{ $profile?->title ?? '—' }}
                                        </td>
                                        <td class="px-4 py-3 text-gray-500">
                                            <a href="mailto:{{ $reader->email }}" class="hover:text-indigo-600 hover:underline" onclick="event.stopPropagation()">{{ $reader->email }}</a>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center gap-1 text-xs font-medium {{ $avail === 'available' ? 'text-green-700' : 'text-red-700' }}">
                                                <span class="w-1.5 h-1.5 rounded-full {{ $avail === 'available' ? 'bg-green-500' : 'bg-red-500' }}"></span>
                                                {{ ucfirst($avail) }}
                                            </span>
                                            @if ($profile?->availability_message)
                                                <div class="mt-0.5 text-[11px] text-gray-400 max-w-[160px] truncate" title="{{ $profile->availability_message }}">
                                                    {{ $profile->availability_message }}
                                                </div>
                                            @endif
                                            <div class="mt-1 text-[10px] text-gray-400">{{ $reader->lastOnlineText() }}</div>
                                            @if($authUser->isAdmin())
                                            <form method="POST" action="{{ route('team.toggle-visibility', $reader) }}" onclick="event.stopPropagation()" class="mt-1.5">
                                                @csrf
                                                <button type="submit" class="text-[10px] px-2 py-0.5 rounded border {{ $reader->hidden_from_staff ? 'border-amber-300 text-amber-600 bg-amber-50 hover:bg-amber-100' : 'border-gray-200 text-gray-400 hover:text-gray-600 hover:border-gray-300' }}">
                                                    {{ $reader->hidden_from_staff ? 'Hidden' : 'Visible' }}
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('impersonate.start', $reader) }}" onclick="event.stopPropagation()" class="mt-1">
                                                @csrf
                                                <button type="submit" class="text-[10px] px-2 py-0.5 rounded border border-yellow-300 text-yellow-700 bg-yellow-50 hover:bg-yellow-100 whitespace-nowrap">
                                                    👁 View as
                                                </button>
                                            </form>
                                            @endif
                                        </td>
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
