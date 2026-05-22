<x-app-layout>
    <x-slot name="header">
<div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Assignments</h2>
            </div>
            @can('create', \App\Models\Assignment::class)
                <a href="{{ route('assignments.create') }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition ease-in-out duration-150">
                    + Create Assignment
                </a>
            @endcan
        </div>
    </x-slot>

    <style>
        @keyframes request-pulse {
            0%, 100% { border-left-color: rgb(192, 132, 252); }
            50%       { border-left-color: rgb(233, 213, 255); }
        }
        .request-pulse { animation: request-pulse 2.5s ease-in-out infinite; }
    </style>

    <div class="py-6">
        <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">

            {{-- Flash messages --}}
            @if (session('success'))
                <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded-md text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-800 rounded-md text-sm">
                    {{ session('error') }}
                </div>
            @endif

            {{-- ===== ADMIN / EDITOR VIEW ===== --}}
            @if ($canManage)
            <script>setInterval(() => {
                if (!document.querySelector('.fixed.inset-0.z-50:not([style*="display: none"])')) location.reload();
            }, 30000);</script>

                {{-- Reader list panel --}}
                @if ($readers->isNotEmpty())
                    <div class="mb-5" x-data="{ activeReader: null }">
                        <div class="flex items-center gap-2 flex-wrap">
                            @foreach ($readers as $reader)
                                @php
                                    $rProfile  = $reader->readerProfile;
                                    $rInitials = $rProfile?->initials ?? strtoupper(substr($reader->name, 0, 2));
                                    $rActive   = $reader->assignments->count();
                                    $rMax      = $rProfile?->max_concurrent_assignments ?? 0;
                                    $rFull     = $rMax > 0 && $rActive >= $rMax;
                                    $rPhotoUrl = $rProfile?->photo ? asset('storage/' . $rProfile->photo) : null;
                                @endphp
                                <div class="flex flex-col items-center gap-0.5">
                                    <button
                                        type="button"
                                        @click="activeReader = activeReader === {{ $reader->id }} ? null : {{ $reader->id }}"
                                        :class="activeReader === {{ $reader->id }} ? 'ring-2 ring-offset-1 ring-gray-400' : ''"
                                        class="relative inline-flex items-center justify-center w-9 h-9 rounded-full text-xs font-mono font-semibold transition-all cursor-pointer
                                            {{ $rFull ? 'bg-amber-200 text-amber-800' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}"
                                        title="{{ $rProfile?->displayName() ?? $reader->name }} — {{ $rActive }}/{{ $rMax ?: '?' }} active"
                                    >
                                        @if ($rPhotoUrl)
                                            <span class="absolute inset-0 rounded-full overflow-hidden">
                                                <img src="{{ $rPhotoUrl }}" alt="{{ $rInitials }}" class="w-full h-full object-cover" />
                                            </span>
                                        @else
                                            {{ $rInitials }}
                                        @endif
                                        @if ($rActive > 0)
                                            <span class="absolute -top-0.5 -right-0.5 w-4 h-4 rounded-full text-[9px] leading-none flex items-center justify-center font-bold z-10
                                                {{ $rFull ? 'bg-amber-500 text-white' : 'bg-green-500 text-white' }}">
                                                {{ $rActive }}
                                            </span>
                                        @endif
                                    </button>
                                    <span class="text-[9px] text-gray-400 font-mono leading-none">{{ $rInitials }}</span>
                                </div>
                            @endforeach
                        </div>

                        {{-- Detail panel for the selected reader --}}
                        @foreach ($readers as $reader)
                            @php
                                $rProfile  = $reader->readerProfile;
                                $rInitials = $rProfile?->initials ?? strtoupper(substr($reader->name, 0, 2));
                                $rActive   = $reader->assignments->count();
                                $rMax      = $rProfile?->max_concurrent_assignments ?? 0;
                                $rPhotoUrl = $rProfile?->photo ? asset('storage/' . $rProfile->photo) : null;
                            @endphp
                            <div x-show="activeReader === {{ $reader->id }}" x-cloak
                                 x-transition:enter="transition ease-out duration-150"
                                 x-transition:enter-start="opacity-0 -translate-y-1"
                                 x-transition:enter-end="opacity-100 translate-y-0"
                                 class="mt-3 bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
                                <div class="flex items-start gap-4">
                                    <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center text-sm font-mono font-semibold text-gray-700 shrink-0 overflow-hidden relative">
                                        @if ($rPhotoUrl)
                                            <img src="{{ $rPhotoUrl }}" alt="{{ $rInitials }}" class="absolute inset-0 w-full h-full object-cover" />
                                        @else
                                            {{ $rInitials }}
                                        @endif
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-baseline gap-3">
                                            <span class="font-semibold text-gray-900">{{ $rProfile?->displayName() ?? $reader->name }}</span>
                                            <span class="text-xs text-gray-400">{{ $rActive }} / {{ $rMax ?: '—' }} assignment{{ $rMax === 1 ? '' : 's' }}</span>
                                            @if ($rProfile?->paypal_email)
                                                <span class="text-xs text-gray-400">PayPal: {{ $rProfile->paypal_email }}</span>
                                            @endif
                                            <a href="{{ route('readers.edit', $reader) }}"
                                               class="text-xs text-indigo-500 hover:text-indigo-700 underline ml-auto">Edit Profile</a>
                                        </div>
                                        @if ($reader->assignments->isNotEmpty())
                                            <ul class="mt-2 space-y-1">
                                                @foreach ($reader->assignments as $ra)
                                                    <li class="flex items-center gap-2 text-sm text-gray-700">
                                                        @if ($ra->rush)
                                                            <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-400 text-amber-900 uppercase leading-none">Rush</span>
                                                        @endif
                                                        <span class="font-medium">{{ $ra->script_title }}</span>
                                                        <span class="text-gray-400">{{ $ra->writer_name }}</span>
                                                        <span class="text-gray-400">&middot; {{ $ra->page_count }} pages</span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @else
                                            <p class="mt-1 text-sm text-gray-400">No active assignments.</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ($assignments->isEmpty())
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-12 text-center text-gray-500">
                        No assignments yet.
                    </div>
                @else
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Age</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Order #</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title / Writer</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Pages</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Type</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Turnaround</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Pay</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Request</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Status</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Reader</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                                    <th class="px-3 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                @foreach ($assignments as $assignment)
                                    @php
                                        $diff     = $assignment->created_at ? now()->diff($assignment->created_at) : null;
                                        $ageStr   = $diff
                                            ? ($diff->days >= 1
                                                ? ($diff->days . 'd ' . $diff->h . 'h')
                                                : ($diff->h >= 1 ? ($diff->h . 'h ' . $diff->i . 'm') : (max(0, $diff->i) . 'm')))
                                            : '—';
                                        $ageTitle = $assignment->created_at?->format('M j, Y g:ia') ?? '—';

                                        $statusColor = match($assignment->status) {
                                            'unassigned' => 'bg-amber-100 text-amber-800',
                                            'assigned'   => 'bg-green-100 text-green-800',
                                            'completed'  => 'bg-green-100 text-green-800',
                                            'qc'         => 'bg-blue-100 text-blue-800',
                                            'incoming'   => 'bg-gray-100 text-gray-700',
                                            'cancelled'         => 'bg-red-100 text-red-700',
                                            'on_hold_customer'  => 'bg-red-100 text-red-700',
                                            'on_hold_sr'        => 'bg-red-100 text-red-700',
                                            default      => 'bg-gray-100 text-gray-700',
                                        };

                                        $statusLabel = match($assignment->status) {
                                            'on_hold_customer' => 'On Hold – Customer',
                                            'on_hold_sr'      => 'On Hold – SR',
                                            'qc'      => 'QC',
                                            default   => ucfirst($assignment->status),
                                        };

                                        $reqInitials  = $assignment->requestedReader?->readerProfile?->initials;
                                        $reqPhotoUrl  = $assignment->requestedReader?->readerProfile?->photo
                                            ? asset('storage/' . $assignment->requestedReader->readerProfile->photo)
                                            : null;

                                        $assignedInitials = $assignment->assignedReader?->readerProfile?->initials
                                            ?? ($assignment->assignedReader ? substr($assignment->assignedReader->name, 0, 2) : null);
                                        $assignedPhotoUrl = $assignment->assignedReader?->readerProfile?->photo
                                            ? asset('storage/' . $assignment->assignedReader->readerProfile->photo)
                                            : null;

                                        $viewUrl  = $assignment->drive_script_file_id
                                            ? route('assignments.streamScript', $assignment)
                                            : null;
                                        $rowClass = ($assignment->rush && $assignment->status === 'unassigned')
                                            ? 'border-l-4 border-amber-400'
                                            : '';
                                        $typeLabel = match($assignment->assignment_type) {
                                            'script_coverage'   => 'Script Coverage',
                                            'notes_only'        => 'Notes-Only',
                                            'deep_dive'         => 'Deep-Dive',
                                            'short'             => 'Short',
                                            'budget'            => 'Budget',
                                            'book'              => 'Book',
                                            'coverage'          => 'Coverage',
                                            'development_notes' => 'Dev Notes',
                                            default             => $assignment->assignment_type ?? '—',
                                        };
                                        if ($assignment->vendor === 'wd') {
                                            $typeLabel = 'WD ' . $typeLabel;
                                        }
                                    @endphp
                                    <tr class="hover:bg-gray-50 {{ $rowClass }}">
                                        {{-- Age --}}
                                        <td class="px-3 py-3 whitespace-nowrap text-gray-500 tabular-nums" title="{{ $ageTitle }}">
                                            {{ $ageStr }}
                                        </td>

                                        {{-- Order # --}}
                                        <td class="px-3 py-3 whitespace-nowrap font-mono text-gray-700">
                                            {{ $assignment->order_number }}
                                        </td>

                                        {{-- Title / Writer --}}
                                        <td class="px-3 py-3" x-data='{ open: false, url: @json($viewUrl) }'>
                                            @if($viewUrl)
                                                <button @click="open = true" type="button"
                                                        class="font-medium text-gray-900 hover:text-indigo-600 text-left leading-snug">{{ $assignment->script_title }}</button>
                                                <div x-show="open" x-cloak
                                                     @keydown.escape.window="open = false"
                                                     class="fixed inset-0 z-50 flex flex-col bg-black/80">
                                                    <div class="flex items-center justify-between px-4 py-2 bg-gray-900 shrink-0">
                                                        <span class="text-sm text-gray-200 font-medium truncate">{{ $assignment->drive_script_filename ?? $assignment->script_title }}</span>
                                                        <button @click="open = false" type="button"
                                                                class="text-gray-400 hover:text-white text-2xl leading-none ml-4 px-1">×</button>
                                                    </div>
                                                    <iframe :src="open ? url : ''"
                                                            class="flex-1 w-full border-0"
                                                            allowfullscreen></iframe>
                                                </div>
                                            @else
                                                <div class="font-medium text-gray-900">{{ $assignment->script_title }}</div>
                                            @endif
                                            <div class="text-xs text-gray-500">{{ $assignment->writer_name }}</div>
                                        </td>

                                        {{-- Page count --}}
                                        <td class="px-3 py-3 whitespace-nowrap text-gray-700 tabular-nums">
                                            {{ $assignment->page_count }}
                                        </td>

                                        {{-- Assignment Type --}}
                                        <td class="px-3 py-3 whitespace-nowrap text-gray-600 text-xs">
                                            {{ $typeLabel }}
                                        </td>

                                        {{-- Turnaround --}}
                                        <td class="px-3 py-3 whitespace-nowrap">
                                            @if ($assignment->rush)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-amber-400 text-amber-900 uppercase tracking-wide">Rush</span>
                                            @else
                                                <span class="text-xs text-gray-400">Standard</span>
                                            @endif
                                        </td>

                                        {{-- Pay rate --}}
                                        <td class="px-3 py-3 whitespace-nowrap text-gray-700 tabular-nums">
                                            ${{ number_format($assignment->pay_rate, 2) }}
                                        </td>

                                        {{-- Request --}}
                                        <td class="px-3 py-3 whitespace-nowrap">
                                            @if ($reqInitials)
                                                <div class="flex flex-col items-center gap-0.5">
                                                    <span class="relative inline-flex items-center justify-center w-7 h-7 rounded-full bg-purple-100 text-purple-700 text-xs font-mono font-semibold">
                                                        @if ($reqPhotoUrl)
                                                            <span class="absolute inset-0 rounded-full overflow-hidden">
                                                                <img src="{{ $reqPhotoUrl }}" alt="{{ $reqInitials }}" class="w-full h-full object-cover" />
                                                            </span>
                                                        @else
                                                            {{ $reqInitials }}
                                                        @endif
                                                    </span>
                                                    <span class="text-[9px] text-purple-400 font-mono leading-none">{{ $reqInitials }}</span>
                                                </div>
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>

                                        {{-- Status (inline quick-change) --}}
                                        <td class="px-3 py-3 whitespace-nowrap">
                                            <form method="POST" action="{{ route('assignments.updateStatus', $assignment) }}"
                                                  x-data="{ pendingAssign: false, curStatus: '{{ $assignment->status }}' }">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="assigned_reader_id" x-ref="rInput" value="" />
                                                <select name="status" x-ref="sSel"
                                                    @change="
                                                        if ($event.target.value === 'assigned') {
                                                            pendingAssign = true;
                                                        } else {
                                                            pendingAssign = false;
                                                            $refs.rInput.value = '';
                                                            $event.target.closest('form').submit();
                                                        }
                                                    "
                                                    class="text-xs rounded-full border-0 ring-1 ring-gray-200 py-0.5 pl-2.5 pr-6 cursor-pointer focus:ring-indigo-400 {{ $statusColor }}">
                                                    @foreach ([
                                                        'incoming'   => 'Pending',
                                                        'unassigned' => 'Available',
                                                        'assigned'   => 'Assigned',
                                                        'completed'  => 'Completed',
                                                        'qc'         => 'QC',
                                                        'on_hold_customer' => 'On Hold – Customer',
                                                        'on_hold_sr'      => 'On Hold – SR',
                                                        'cancelled'  => 'Cancelled',
                                                    ] as $value => $label)
                                                        <option value="{{ $value }}" {{ $assignment->status === $value ? 'selected' : '' }}>
                                                            {{ $label }}
                                                        </option>
                                                    @endforeach
                                                </select>

                                                {{-- Reader sub-picker — appears when Assigned is selected --}}
                                                <div x-show="pendingAssign" x-cloak class="mt-1.5 flex items-center gap-1">
                                                    <select
                                                        @change="if ($event.target.value) { $refs.rInput.value = $event.target.value; $event.target.closest('form').submit(); }"
                                                        class="text-xs rounded border border-gray-200 bg-white py-0.5 pl-2 pr-5 cursor-pointer focus:ring-indigo-400">
                                                        <option value="">→ reader</option>
                                                        @foreach ($readers as $reader)
                                                            <option value="{{ $reader->id }}">
                                                                {{ $reader->readerProfile?->initials ?? strtoupper(substr($reader->name, 0, 2)) }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    <button type="button"
                                                            @click="pendingAssign = false; $refs.sSel.value = curStatus"
                                                            class="text-gray-400 hover:text-gray-700 text-base leading-none px-0.5"
                                                            title="Cancel">×</button>
                                                </div>
                                            </form>
                                        </td>

                                        {{-- Assigned reader --}}
                                        <td class="px-3 py-3 whitespace-nowrap">
                                            @if ($assignedInitials)
                                                <div class="flex flex-col items-center gap-0.5">
                                                    <span class="relative inline-flex items-center justify-center w-7 h-7 rounded-full bg-gray-200 text-gray-700 text-xs font-mono font-semibold">
                                                        @if ($assignedPhotoUrl)
                                                            <span class="absolute inset-0 rounded-full overflow-hidden">
                                                                <img src="{{ $assignedPhotoUrl }}" alt="{{ $assignedInitials }}" class="w-full h-full object-cover" />
                                                            </span>
                                                        @else
                                                            {{ $assignedInitials }}
                                                        @endif
                                                    </span>
                                                    <span class="text-[9px] text-gray-400 font-mono leading-none">{{ $assignedInitials }}</span>
                                                </div>
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>

                                        {{-- Notes (hover tooltip + click-to-edit for admin/editor) --}}
                                        <td class="px-3 py-3"
                                            x-data="{
                                                open: false,
                                                hover: false,
                                                tipX: 0,
                                                tipY: 0,
                                                note: @js($assignment->notes ?? ''),
                                                saving: false,
                                                saved: false,
                                                async save() {
                                                    this.saving = true; this.saved = false;
                                                    try {
                                                        const r = await fetch(@js(route('assignments.updateNotes', $assignment)), {
                                                            method: 'PATCH',
                                                            headers: {
                                                                'Content-Type': 'application/json',
                                                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                                                'Accept': 'application/json',
                                                            },
                                                            body: JSON.stringify({ notes: this.note }),
                                                        });
                                                        if (r.ok) this.saved = true;
                                                    } finally { this.saving = false; }
                                                }
                                            }">
                                            @if($assignment->notes)
                                                <div class="inline-block"
                                                     @mouseenter="hover = true; const r = $el.getBoundingClientRect(); tipX = r.left + r.width / 2; tipY = r.top"
                                                     @mouseleave="hover = false">
                                                    <button @click="open = !open" type="button"
                                                            class="text-amber-500 hover:text-amber-600 transition">
                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                                                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/>
                                                        </svg>
                                                    </button>
                                                    <div x-show="hover && !open" x-cloak
                                                         :style="`position:fixed;left:${tipX}px;top:${tipY}px;transform:translate(-50%,calc(-100% - 8px))`"
                                                         class="z-50 w-56 bg-gray-800 text-white text-xs rounded-md px-2.5 py-2 shadow-lg whitespace-pre-wrap pointer-events-none">
                                                        <p x-text="note"></p>
                                                        <div class="absolute top-full left-1/2 -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-l-transparent border-r-transparent border-t-4 border-t-gray-800"></div>
                                                    </div>
                                                </div>
                                                <div x-show="open" x-cloak class="mt-1.5 w-56">
                                                    <textarea x-model="note" rows="3"
                                                              class="w-full text-xs border border-gray-200 rounded p-1.5 resize-none focus:outline-none focus:ring-1 focus:ring-indigo-400"></textarea>
                                                    <div class="flex items-center justify-end gap-1 mt-1">
                                                        <button type="button" @click="open=false"
                                                                class="text-xs text-gray-400 hover:text-gray-600 px-1.5 py-0.5">Close</button>
                                                        <button type="button" :disabled="saving" @click="save()"
                                                                class="text-xs px-2 py-0.5 bg-indigo-600 text-white rounded hover:bg-indigo-500 disabled:opacity-50"
                                                                x-text="saving ? 'Saving…' : 'Save'"></button>
                                                    </div>
                                                    <span x-show="saved" class="text-[10px] text-green-600 block mt-0.5">Saved</span>
                                                </div>
                                            @endif
                                        </td>

                                        {{-- Actions --}}
                                        <td class="px-3 py-3 whitespace-nowrap text-right">
                                            @can('update', $assignment)
                                                <a href="{{ route('assignments.edit', $assignment) }}"
                                                   class="inline-flex items-center px-2.5 py-1 bg-white border border-gray-300 rounded text-xs font-medium text-gray-700 hover:bg-gray-50 transition">
                                                    Edit
                                                </a>
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

            {{-- ===== READER VIEW ===== --}}
            @else
                <div x-data="{ tab: 'all' }"
                     x-init="setInterval(() => {
                         if (tab === 'all' && !document.querySelector('.fixed.inset-0.z-50:not([style*=\"display: none\"])')) location.reload();
                     }, 15000)">

                    {{-- Tabs --}}
                    <div class="flex border-b border-gray-200 mb-4">
                        <button @click="tab = 'all'"
                                :class="tab === 'all' ? 'border-b-2 border-indigo-600 text-indigo-700 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                                class="px-4 py-2 text-sm transition">
                            Assignments
                        </button>
                        <button @click="tab = 'mine'"
                                :class="tab === 'mine' ? 'border-b-2 border-indigo-600 text-indigo-700 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                                class="px-4 py-2 text-sm transition flex items-center gap-1.5">
                            My Assignments
                            @if($mine->isNotEmpty())
                                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-indigo-100 text-indigo-700 text-xs font-bold">{{ $mine->count() }}</span>
                            @endif
                        </button>
                    </div>

                    {{-- ---- Assignments tab (mine + available pool) ---- --}}
                    <div x-show="tab === 'all'">
                        @if($mine->isEmpty() && $available->isEmpty())
                            <div class="bg-white rounded-lg border border-gray-200 p-12 text-center text-gray-400 text-sm">
                                No assignments available right now.
                            </div>
                        @else
                            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                                <table class="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Age</th>
                                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Order #</th>
                                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title / Writer</th>
                                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Pages</th>
                                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Type</th>
                                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Turnaround</th>
                                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Pay</th>
                                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Request</th>
                                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Status</th>
                                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                                            <th class="px-3 py-3"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-100">

                                        {{-- Reader's own assignments first --}}
                                        @foreach($mine as $assignment)
                                            @php
                                                $diff     = $assignment->created_at ? now()->diff($assignment->created_at) : null;
                                                $ageStr   = $diff
                                                    ? ($diff->days >= 1
                                                        ? ($diff->days . 'd ' . $diff->h . 'h')
                                                        : ($diff->h >= 1 ? ($diff->h . 'h ' . $diff->i . 'm') : (max(0, $diff->i) . 'm')))
                                                    : '—';
                                                $ageTitle = $assignment->created_at?->format('M j, Y g:ia') ?? '—';
                                                $reqInitials  = $assignment->requestedReader?->readerProfile?->initials;
                                                $reqPhotoUrl  = $assignment->requestedReader?->readerProfile?->photo
                                                    ? asset('storage/' . $assignment->requestedReader->readerProfile->photo)
                                                    : null;
                                                $isRequestedForMe = $assignment->requested_reader_id === auth()->id();
                                                $statusColor = match($assignment->status) {
                                                    'assigned'  => 'bg-green-100 text-green-800',
                                                    'completed' => 'bg-green-100 text-green-800',
                                                    'qc'        => 'bg-blue-100 text-blue-800',
                                                    default     => 'bg-gray-100 text-gray-700',
                                                };
                                                $statusLabel = $assignment->status === 'assigned' ? 'Assigned to you'
                                                    : ($assignment->status === 'qc' ? 'QC' : ucfirst($assignment->status));
                                                $rowClass = $isRequestedForMe
                                                    ? 'border-l-4 request-pulse'
                                                    : ($assignment->rush ? 'border-l-4 border-amber-400' : '');
                                                $viewUrl  = $assignment->drive_script_file_id
                                                    ? route('assignments.streamScript', $assignment)
                                                    : null;
                                                $typeLabel = match($assignment->assignment_type) {
                                                    'script_coverage'   => 'Script Coverage',
                                                    'notes_only'        => 'Notes-Only',
                                                    'deep_dive'         => 'Deep-Dive',
                                                    'short'             => 'Short',
                                                    'budget'            => 'Budget',
                                                    'book'              => 'Book',
                                                    'coverage'          => 'Coverage',
                                                    'development_notes' => 'Dev Notes',
                                                    default             => $assignment->assignment_type ?? '—',
                                                };
                                                if ($assignment->vendor === 'wd') {
                                                    $typeLabel = 'WD ' . $typeLabel;
                                                }
                                            @endphp
                                            <tr class="hover:bg-gray-50 bg-indigo-50/30 {{ $rowClass }}">
                                                <td class="px-3 py-3 whitespace-nowrap text-gray-500 tabular-nums" title="{{ $ageTitle }}">{{ $ageStr }}</td>
                                                <td class="px-3 py-3 whitespace-nowrap font-mono text-gray-700">{{ $assignment->order_number }}</td>
                                                <td class="px-3 py-3" x-data='{ open: false, url: @json($viewUrl) }'>
                                                    @if($viewUrl)
                                                        <button @click="open = true" type="button"
                                                                class="font-medium text-gray-900 hover:text-indigo-600 text-left leading-snug">{{ $assignment->script_title }}</button>
                                                        <div x-show="open" x-cloak
                                                             @keydown.escape.window="open = false"
                                                             tabindex="-1"
                                                             x-effect="if (open) $nextTick(() => $el.focus())"
                                                             class="fixed inset-0 z-50 flex flex-col bg-black/80">
                                                            <div class="flex items-center justify-between px-4 py-2 bg-gray-900 shrink-0">
                                                                <span class="text-sm text-gray-200 font-medium truncate">{{ $assignment->drive_script_filename ?? $assignment->script_title }}</span>
                                                                <button @click="open = false" type="button"
                                                                        class="text-gray-400 hover:text-white text-2xl leading-none ml-4 px-1">×</button>
                                                            </div>
                                                            <iframe :src="open ? url : ''"
                                                                    class="flex-1 w-full border-0"
                                                                    allowfullscreen></iframe>
                                                        </div>
                                                    @else
                                                        <div class="font-medium text-gray-900">{{ $assignment->script_title }}</div>
                                                    @endif
                                                    <div class="text-xs text-gray-500">{{ $assignment->writer_name }}</div>
                                                </td>
                                                <td class="px-3 py-3 whitespace-nowrap text-gray-700 tabular-nums">{{ $assignment->page_count }}</td>
                                                <td class="px-3 py-3 whitespace-nowrap text-gray-600 text-xs">{{ $typeLabel }}</td>
                                                <td class="px-3 py-3 whitespace-nowrap">
                                                    @if($assignment->rush)
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-amber-400 text-amber-900 uppercase tracking-wide">Rush</span>
                                                    @else
                                                        <span class="text-xs text-gray-400">Standard</span>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-3 whitespace-nowrap text-gray-700 tabular-nums">${{ number_format($assignment->pay_rate, 2) }}</td>
                                                <td class="px-3 py-3 whitespace-nowrap">
                                                    @if($reqInitials)
                                                        <div class="flex flex-col items-center gap-0.5">
                                                            <span class="relative inline-flex items-center justify-center w-7 h-7 rounded-full bg-purple-100 text-purple-700 text-xs font-mono font-semibold">
                                                                @if($reqPhotoUrl)
                                                                    <span class="absolute inset-0 rounded-full overflow-hidden">
                                                                        <img src="{{ $reqPhotoUrl }}" alt="{{ $reqInitials }}" class="w-full h-full object-cover" />
                                                                    </span>
                                                                @else
                                                                    {{ $reqInitials }}
                                                                @endif
                                                            </span>
                                                            <span class="text-[9px] text-purple-400 font-mono leading-none">{{ $reqInitials }}</span>
                                                        </div>
                                                    @else
                                                        <span class="text-gray-300">—</span>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-3 whitespace-nowrap">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColor }}">{{ $statusLabel }}</span>
                                                </td>
                                                {{-- Notes (hover tooltip, read-only for readers) --}}
                                                <td class="px-3 py-3" x-data="{ hover: false, tipX: 0, tipY: 0, note: @js($assignment->notes ?? '') }">
                                                    @if($assignment->notes)
                                                        <div class="inline-block"
                                                             @mouseenter="hover = true; const r = $el.getBoundingClientRect(); tipX = r.left + r.width / 2; tipY = r.top"
                                                             @mouseleave="hover = false">
                                                            <span class="text-amber-500">
                                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                                                                    <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/>
                                                                </svg>
                                                            </span>
                                                            <div x-show="hover" x-cloak
                                                                 :style="`position:fixed;left:${tipX}px;top:${tipY}px;transform:translate(-50%,calc(-100% - 8px))`"
                                                                 class="z-50 w-56 bg-gray-800 text-white text-xs rounded-md px-2.5 py-2 shadow-lg whitespace-pre-wrap pointer-events-none">
                                                                <p x-text="note"></p>
                                                                <div class="absolute top-full left-1/2 -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-l-transparent border-r-transparent border-t-4 border-t-gray-800"></div>
                                                            </div>
                                                        </div>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-3 whitespace-nowrap text-right">
                                                    <div class="flex items-center justify-end gap-2">
                                                        @can('submitCoverage', $assignment)
                                                            <a href="{{ route('coverage.show', $assignment) }}"
                                                               class="inline-flex items-center px-2.5 py-1 bg-indigo-600 border border-transparent rounded text-xs font-semibold text-white hover:bg-indigo-500 transition whitespace-nowrap">
                                                                Write Coverage
                                                            </a>
                                                        @endcan
                                                        @can('cancel', $assignment)
                                                            <form method="POST" action="{{ route('assignments.cancel', $assignment) }}">
                                                                @csrf
                                                                <button type="submit"
                                                                        onclick="return confirm('Return this assignment to the pool?')"
                                                                        class="inline-flex items-center px-2.5 py-1 bg-white border border-gray-300 rounded text-xs font-medium text-gray-700 hover:bg-gray-50 transition">
                                                                    Cancel
                                                                </button>
                                                            </form>
                                                        @endcan
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach

                                        {{-- Separator when both sections have rows --}}
                                        @if($mine->isNotEmpty() && $available->isNotEmpty())
                                            <tr>
                                                <td colspan="11" class="px-3 py-1.5 bg-gray-50 text-xs text-gray-400 uppercase tracking-wider font-medium border-t border-gray-200">
                                                    Available Assignments
                                                </td>
                                            </tr>
                                        @endif

                                        {{-- Available assignments --}}
                                        @foreach($available as $assignment)
                                            @php
                                                $diff     = $assignment->created_at ? now()->diff($assignment->created_at) : null;
                                                $ageStr   = $diff
                                                    ? ($diff->days >= 1
                                                        ? ($diff->days . 'd ' . $diff->h . 'h')
                                                        : ($diff->h >= 1 ? ($diff->h . 'h ' . $diff->i . 'm') : (max(0, $diff->i) . 'm')))
                                                    : '—';
                                                $ageTitle = $assignment->created_at?->format('M j, Y g:ia') ?? '—';
                                                $reqInitials  = $assignment->requestedReader?->readerProfile?->initials;
                                                $reqPhotoUrl  = $assignment->requestedReader?->readerProfile?->photo
                                                    ? asset('storage/' . $assignment->requestedReader->readerProfile->photo)
                                                    : null;
                                                $isRequestedForMe = $assignment->requested_reader_id === auth()->id();
                                                $rowClass = $isRequestedForMe
                                                    ? 'border-l-4 request-pulse'
                                                    : ($assignment->rush ? 'border-l-4 border-amber-400' : '');
                                                $viewUrl  = $assignment->drive_script_file_id
                                                    ? route('assignments.streamScript', $assignment)
                                                    : null;
                                                $typeLabel = match($assignment->assignment_type) {
                                                    'script_coverage'   => 'Script Coverage',
                                                    'notes_only'        => 'Notes-Only',
                                                    'deep_dive'         => 'Deep-Dive',
                                                    'short'             => 'Short',
                                                    'budget'            => 'Budget',
                                                    'book'              => 'Book',
                                                    'coverage'          => 'Coverage',
                                                    'development_notes' => 'Dev Notes',
                                                    default             => $assignment->assignment_type ?? '—',
                                                };
                                                if ($assignment->vendor === 'wd') {
                                                    $typeLabel = 'WD ' . $typeLabel;
                                                }
                                            @endphp
                                            <tr class="hover:bg-gray-50 {{ $rowClass }}">
                                                <td class="px-3 py-3 whitespace-nowrap text-gray-500 tabular-nums" title="{{ $ageTitle }}">{{ $ageStr }}</td>
                                                <td class="px-3 py-3 whitespace-nowrap font-mono text-gray-700">{{ $assignment->order_number }}</td>
                                                <td class="px-3 py-3" x-data='{ open: false, url: @json($viewUrl) }'>
                                                    @if($viewUrl)
                                                        <button @click="open = true" type="button"
                                                                class="font-medium text-gray-900 hover:text-indigo-600 text-left leading-snug flex items-center gap-2">
                                                            {{ $assignment->script_title }}
                                                            @if($isRequestedForMe)
                                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-purple-100 text-purple-700">For you</span>
                                                            @endif
                                                        </button>
                                                        <div x-show="open" x-cloak
                                                             @keydown.escape.window="open = false"
                                                             tabindex="-1"
                                                             x-effect="if (open) $nextTick(() => $el.focus())"
                                                             class="fixed inset-0 z-50 flex flex-col bg-black/80">
                                                            <div class="flex items-center justify-between px-4 py-2 bg-gray-900 shrink-0">
                                                                <span class="text-sm text-gray-200 font-medium truncate">{{ $assignment->drive_script_filename ?? $assignment->script_title }}</span>
                                                                <button @click="open = false" type="button"
                                                                        class="text-gray-400 hover:text-white text-2xl leading-none ml-4 px-1">×</button>
                                                            </div>
                                                            <iframe :src="open ? url : ''"
                                                                    class="flex-1 w-full border-0"
                                                                    allowfullscreen></iframe>
                                                        </div>
                                                    @else
                                                        <div class="font-medium text-gray-900 flex items-center gap-2">
                                                            {{ $assignment->script_title }}
                                                            @if($isRequestedForMe)
                                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-purple-100 text-purple-700">For you</span>
                                                            @endif
                                                        </div>
                                                    @endif
                                                    <div class="text-xs text-gray-500">{{ $assignment->writer_name }}</div>
                                                </td>
                                                <td class="px-3 py-3 whitespace-nowrap text-gray-700 tabular-nums">{{ $assignment->page_count }}</td>
                                                <td class="px-3 py-3 whitespace-nowrap text-gray-600 text-xs">{{ $typeLabel }}</td>
                                                <td class="px-3 py-3 whitespace-nowrap">
                                                    @if($assignment->rush)
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-amber-400 text-amber-900 uppercase tracking-wide">Rush</span>
                                                    @else
                                                        <span class="text-xs text-gray-400">Standard</span>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-3 whitespace-nowrap text-gray-700 tabular-nums">${{ number_format($assignment->pay_rate, 2) }}</td>
                                                <td class="px-3 py-3 whitespace-nowrap">
                                                    @if($reqInitials)
                                                        <div class="flex flex-col items-center gap-0.5">
                                                            <span class="relative inline-flex items-center justify-center w-7 h-7 rounded-full bg-purple-100 text-purple-700 text-xs font-mono font-semibold">
                                                                @if($reqPhotoUrl)
                                                                    <span class="absolute inset-0 rounded-full overflow-hidden">
                                                                        <img src="{{ $reqPhotoUrl }}" alt="{{ $reqInitials }}" class="w-full h-full object-cover" />
                                                                    </span>
                                                                @else
                                                                    {{ $reqInitials }}
                                                                @endif
                                                            </span>
                                                            <span class="text-[9px] text-purple-400 font-mono leading-none">{{ $reqInitials }}</span>
                                                        </div>
                                                    @else
                                                        <span class="text-gray-300">—</span>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-3 whitespace-nowrap">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">Available</span>
                                                </td>
                                                {{-- Notes (hover tooltip, read-only for readers) --}}
                                                <td class="px-3 py-3" x-data="{ hover: false, tipX: 0, tipY: 0, note: @js($assignment->notes ?? '') }">
                                                    @if($assignment->notes)
                                                        <div class="inline-block"
                                                             @mouseenter="hover = true; const r = $el.getBoundingClientRect(); tipX = r.left + r.width / 2; tipY = r.top"
                                                             @mouseleave="hover = false">
                                                            <span class="text-amber-500">
                                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                                                                    <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/>
                                                                </svg>
                                                            </span>
                                                            <div x-show="hover" x-cloak
                                                                 :style="`position:fixed;left:${tipX}px;top:${tipY}px;transform:translate(-50%,calc(-100% - 8px))`"
                                                                 class="z-50 w-56 bg-gray-800 text-white text-xs rounded-md px-2.5 py-2 shadow-lg whitespace-pre-wrap pointer-events-none">
                                                                <p x-text="note"></p>
                                                                <div class="absolute top-full left-1/2 -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-l-transparent border-r-transparent border-t-4 border-t-gray-800"></div>
                                                            </div>
                                                        </div>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-3 whitespace-nowrap text-right"
                                                    x-data="{ accepting: false, error: '' }">
                                                    <span x-show="error" x-cloak
                                                          x-text="error"
                                                          class="text-xs text-red-600 font-medium"></span>
                                                    <button x-show="!error" type="button"
                                                            :disabled="accepting"
                                                            @click="accepting = true; error = '';
                                                                fetch('{{ route('assignments.accept', $assignment) }}', {
                                                                    method: 'POST',
                                                                    headers: {
                                                                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                                                        'Accept': 'application/json',
                                                                    }
                                                                }).then(r => {
                                                                    if (r.ok) {
                                                                        $el.closest('tr').remove();
                                                                        location.reload();
                                                                    } else {
                                                                        r.json().then(d => { accepting = false; error = d.message ?? 'No longer available.'; }).catch(() => { accepting = false; error = 'No longer available.'; });
                                                                    }
                                                                }).catch(() => { accepting = false; error = 'Request failed — try again.'; })"
                                                            :class="accepting ? 'opacity-60 cursor-not-allowed' : 'hover:bg-green-500'"
                                                            class="inline-flex items-center px-3 py-1 bg-green-600 border border-transparent rounded text-xs font-semibold text-white transition">
                                                        <span x-text="accepting ? 'Accepting…' : 'Accept'"></span>
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach

                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                    {{-- ---- My Assignments tab ---- --}}
                    <div x-show="tab === 'mine'">
                        @if($mine->isEmpty())
                            <div class="bg-white rounded-lg border border-gray-200 p-12 text-center text-gray-400 text-sm">
                                You haven't accepted any assignments yet.
                            </div>
                        @else
                            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                                <table class="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Age</th>
                                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Order #</th>
                                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title / Writer</th>
                                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Pages</th>
                                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Type</th>
                                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Turnaround</th>
                                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Pay</th>
                                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Request</th>
                                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Status</th>
                                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                                            <th class="px-3 py-3"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-100">
                                        @foreach($mine as $assignment)
                                            @php
                                                $diff     = $assignment->created_at ? now()->diff($assignment->created_at) : null;
                                                $ageStr   = $diff
                                                    ? ($diff->days >= 1
                                                        ? ($diff->days . 'd ' . $diff->h . 'h')
                                                        : ($diff->h >= 1 ? ($diff->h . 'h ' . $diff->i . 'm') : (max(0, $diff->i) . 'm')))
                                                    : '—';
                                                $ageTitle = $assignment->created_at?->format('M j, Y g:ia') ?? '—';
                                                $reqInitials  = $assignment->requestedReader?->readerProfile?->initials;
                                                $reqPhotoUrl  = $assignment->requestedReader?->readerProfile?->photo
                                                    ? asset('storage/' . $assignment->requestedReader->readerProfile->photo)
                                                    : null;
                                                $statusColor = match($assignment->status) {
                                                    'assigned'  => 'bg-green-100 text-green-800',
                                                    'completed' => 'bg-green-100 text-green-800',
                                                    'qc'        => 'bg-blue-100 text-blue-800',
                                                    default     => 'bg-gray-100 text-gray-700',
                                                };
                                                $statusLabel = $assignment->status === 'assigned' ? 'Assigned to you'
                                                    : ($assignment->status === 'qc' ? 'QC' : ucfirst($assignment->status));
                                                $isRequestedForMe = $assignment->requested_reader_id === auth()->id();
                                                $rowClass = $isRequestedForMe
                                                    ? 'border-l-4 request-pulse'
                                                    : ($assignment->rush ? 'border-l-4 border-amber-400' : '');
                                                $viewUrl  = $assignment->drive_script_file_id
                                                    ? route('assignments.streamScript', $assignment)
                                                    : null;
                                                $typeLabel = match($assignment->assignment_type) {
                                                    'script_coverage'   => 'Script Coverage',
                                                    'notes_only'        => 'Notes-Only',
                                                    'deep_dive'         => 'Deep-Dive',
                                                    'short'             => 'Short',
                                                    'budget'            => 'Budget',
                                                    'book'              => 'Book',
                                                    'coverage'          => 'Coverage',
                                                    'development_notes' => 'Dev Notes',
                                                    default             => $assignment->assignment_type ?? '—',
                                                };
                                                if ($assignment->vendor === 'wd') {
                                                    $typeLabel = 'WD ' . $typeLabel;
                                                }
                                            @endphp
                                            <tr class="hover:bg-gray-50 {{ $rowClass }}">
                                                <td class="px-3 py-3 whitespace-nowrap text-gray-500 tabular-nums" title="{{ $ageTitle }}">{{ $ageStr }}</td>
                                                <td class="px-3 py-3 whitespace-nowrap font-mono text-gray-700">{{ $assignment->order_number }}</td>
                                                <td class="px-3 py-3" x-data='{ open: false, url: @json($viewUrl) }'>
                                                    @if($viewUrl)
                                                        <button @click="open = true" type="button"
                                                                class="font-medium text-gray-900 hover:text-indigo-600 text-left leading-snug">{{ $assignment->script_title }}</button>
                                                        <div x-show="open" x-cloak
                                                             @keydown.escape.window="open = false"
                                                             tabindex="-1"
                                                             x-effect="if (open) $nextTick(() => $el.focus())"
                                                             class="fixed inset-0 z-50 flex flex-col bg-black/80">
                                                            <div class="flex items-center justify-between px-4 py-2 bg-gray-900 shrink-0">
                                                                <span class="text-sm text-gray-200 font-medium truncate">{{ $assignment->drive_script_filename ?? $assignment->script_title }}</span>
                                                                <button @click="open = false" type="button"
                                                                        class="text-gray-400 hover:text-white text-2xl leading-none ml-4 px-1">×</button>
                                                            </div>
                                                            <iframe :src="open ? url : ''"
                                                                    class="flex-1 w-full border-0"
                                                                    allowfullscreen></iframe>
                                                        </div>
                                                    @else
                                                        <div class="font-medium text-gray-900">{{ $assignment->script_title }}</div>
                                                    @endif
                                                    <div class="text-xs text-gray-500">{{ $assignment->writer_name }}</div>
                                                </td>
                                                <td class="px-3 py-3 whitespace-nowrap text-gray-700 tabular-nums">{{ $assignment->page_count }}</td>
                                                <td class="px-3 py-3 whitespace-nowrap text-gray-600 text-xs">{{ $typeLabel }}</td>
                                                <td class="px-3 py-3 whitespace-nowrap">
                                                    @if($assignment->rush)
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-amber-400 text-amber-900 uppercase tracking-wide">Rush</span>
                                                    @else
                                                        <span class="text-xs text-gray-400">Standard</span>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-3 whitespace-nowrap text-gray-700 tabular-nums">${{ number_format($assignment->pay_rate, 2) }}</td>
                                                <td class="px-3 py-3 whitespace-nowrap">
                                                    @if($reqInitials)
                                                        <div class="flex flex-col items-center gap-0.5">
                                                            <span class="relative inline-flex items-center justify-center w-7 h-7 rounded-full bg-purple-100 text-purple-700 text-xs font-mono font-semibold">
                                                                @if($reqPhotoUrl)
                                                                    <span class="absolute inset-0 rounded-full overflow-hidden">
                                                                        <img src="{{ $reqPhotoUrl }}" alt="{{ $reqInitials }}" class="w-full h-full object-cover" />
                                                                    </span>
                                                                @else
                                                                    {{ $reqInitials }}
                                                                @endif
                                                            </span>
                                                            <span class="text-[9px] text-purple-400 font-mono leading-none">{{ $reqInitials }}</span>
                                                        </div>
                                                    @else
                                                        <span class="text-gray-300">—</span>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-3 whitespace-nowrap">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColor }}">{{ $statusLabel }}</span>
                                                </td>
                                                {{-- Notes (hover tooltip, read-only for readers) --}}
                                                <td class="px-3 py-3" x-data="{ hover: false, tipX: 0, tipY: 0, note: @js($assignment->notes ?? '') }">
                                                    @if($assignment->notes)
                                                        <div class="inline-block"
                                                             @mouseenter="hover = true; const r = $el.getBoundingClientRect(); tipX = r.left + r.width / 2; tipY = r.top"
                                                             @mouseleave="hover = false">
                                                            <span class="text-amber-500">
                                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                                                                    <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/>
                                                                </svg>
                                                            </span>
                                                            <div x-show="hover" x-cloak
                                                                 :style="`position:fixed;left:${tipX}px;top:${tipY}px;transform:translate(-50%,calc(-100% - 8px))`"
                                                                 class="z-50 w-56 bg-gray-800 text-white text-xs rounded-md px-2.5 py-2 shadow-lg whitespace-pre-wrap pointer-events-none">
                                                                <p x-text="note"></p>
                                                                <div class="absolute top-full left-1/2 -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-l-transparent border-r-transparent border-t-4 border-t-gray-800"></div>
                                                            </div>
                                                        </div>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-3 whitespace-nowrap text-right">
                                                    <div class="flex items-center justify-end gap-2">
                                                        @can('submitCoverage', $assignment)
                                                            <a href="{{ route('coverage.show', $assignment) }}"
                                                               class="inline-flex items-center px-2.5 py-1 bg-indigo-600 border border-transparent rounded text-xs font-semibold text-white hover:bg-indigo-500 transition whitespace-nowrap">
                                                                Write Coverage
                                                            </a>
                                                        @endcan
                                                        @can('cancel', $assignment)
                                                            <form method="POST" action="{{ route('assignments.cancel', $assignment) }}">
                                                                @csrf
                                                                <button type="submit"
                                                                        onclick="return confirm('Return this assignment to the pool?')"
                                                                        class="inline-flex items-center px-2.5 py-1 bg-white border border-gray-300 rounded text-xs font-medium text-gray-700 hover:bg-gray-50 transition">
                                                                    Cancel
                                                                </button>
                                                            </form>
                                                        @endcan
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                </div>
            @endif

        </div>
    </div>
</x-app-layout>
