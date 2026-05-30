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

            {{-- ── Admins ─────────────────────────────────────────────── --}}
            @if ($admins->isNotEmpty())
            <div>
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Admins</h3>
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Admin</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            @foreach ($admins as $admin)
                                @php
                                    $profile  = $admin->editorProfile;
                                    $initials = $profile?->initials ?? strtoupper(substr($admin->name, 0, 2));
                                    $photoUrl = $profile?->photo ? asset('storage/' . $profile->photo) : null;
                                @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            @if(auth()->user()->isAdmin())
                                                <a href="{{ route('admin.editors.edit', $admin) }}" class="relative inline-flex items-center justify-center w-8 h-8 rounded-full bg-violet-100 text-violet-700 text-xs font-mono font-semibold shrink-0 hover:ring-2 hover:ring-violet-300 transition">
                                            @else
                                                <span class="relative inline-flex items-center justify-center w-8 h-8 rounded-full bg-violet-100 text-violet-700 text-xs font-mono font-semibold shrink-0">
                                            @endif
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
                                            @if(auth()->user()->isAdmin())
                                                </a>
                                            @else
                                                </span>
                                            @endif
                                            <div>
                                                <div class="font-medium text-gray-900">{{ $profile?->displayName() ?? $admin->name }}</div>
                                                <div class="text-[11px] text-violet-500 font-medium">Admin</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-500">
                                        <a href="mailto:{{ $admin->email }}" class="hover:text-indigo-600 hover:underline">{{ $admin->email }}</a>
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
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
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
                                        $profile  = $editor->editorProfile;
                                        $initials = $profile?->initials ?? strtoupper(substr($editor->name, 0, 2));
                                        $photoUrl = $profile?->photo ? asset('storage/' . $profile->photo) : null;
                                        $avail    = $profile?->availability ?? 'available';
                                    @endphp
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-3">
                                                @if($canEditEditors)
                                                    <a href="{{ route('admin.editors.edit', $editor) }}" class="relative inline-flex items-center justify-center w-8 h-8 rounded-full bg-indigo-100 text-indigo-700 text-xs font-mono font-semibold shrink-0 hover:ring-2 hover:ring-indigo-300 transition">
                                                @else
                                                    <span class="relative inline-flex items-center justify-center w-8 h-8 rounded-full bg-indigo-100 text-indigo-700 text-xs font-mono font-semibold shrink-0">
                                                @endif
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
                                                @if($canEditEditors)
                                                    </a>
                                                @else
                                                    </span>
                                                @endif
                                                <div class="font-medium text-gray-900">
                                                    {{ $profile?->displayName() ?? $editor->name }}
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-gray-500 text-sm">
                                            {{ $profile?->title ?? '—' }}
                                        </td>
                                        <td class="px-4 py-3 text-gray-500">
                                            <a href="mailto:{{ $editor->email }}" class="hover:text-indigo-600 hover:underline">{{ $editor->email }}</a>
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
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
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
                                        $profile   = $reader->readerProfile;
                                        $initials  = $profile?->initials ?? strtoupper(substr($reader->name, 0, 2));
                                        $photoUrl  = $profile?->photo ? asset('storage/' . $profile->photo) : null;
                                        $avail     = $profile?->availability ?? 'available';
                                    @endphp
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-3">
                                                @if($canEditReaders)
                                                    <a href="{{ route('readers.edit', $reader) }}" class="relative inline-flex items-center justify-center w-8 h-8 rounded-full bg-gray-200 text-gray-700 text-xs font-mono font-semibold shrink-0 hover:ring-2 hover:ring-gray-400 transition {{ $avail !== 'available' ? 'border-2 border-dashed border-red-300' : '' }}">
                                                @else
                                                    <span class="relative inline-flex items-center justify-center w-8 h-8 rounded-full bg-gray-200 text-gray-700 text-xs font-mono font-semibold shrink-0 {{ $avail !== 'available' ? 'border-2 border-dashed border-red-300' : '' }}">
                                                @endif
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
                                                @if($canEditReaders)
                                                    </a>
                                                @else
                                                    </span>
                                                @endif
                                                <div class="font-medium text-gray-900">
                                                    {{ $profile?->displayName() ?? $reader->name }}
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-gray-500 text-sm">
                                            {{ $profile?->title ?? '—' }}
                                        </td>
                                        <td class="px-4 py-3 text-gray-500">
                                            <a href="mailto:{{ $reader->email }}" class="hover:text-indigo-600 hover:underline">{{ $reader->email }}</a>
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
