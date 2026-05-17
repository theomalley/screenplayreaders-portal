<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Assignments</h2>
            @can('create', \App\Models\Assignment::class)
                <a href="{{ route('assignments.create') }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition ease-in-out duration-150">
                    + Create Assignment
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">

            {{-- Flash message --}}
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
            @if (auth()->user()->canManageAssignments())

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
                                @endphp
                                <button
                                    type="button"
                                    @click="activeReader = activeReader === {{ $reader->id }} ? null : {{ $reader->id }}"
                                    :class="activeReader === {{ $reader->id }} ? 'ring-2 ring-offset-1 ring-gray-400' : ''"
                                    class="relative inline-flex items-center justify-center w-9 h-9 rounded-full text-xs font-mono font-semibold transition-all cursor-pointer
                                        {{ $rFull ? 'bg-amber-200 text-amber-800' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}"
                                    title="{{ $rProfile?->displayName() ?? $reader->name }} — {{ $rActive }}/{{ $rMax ?: '?' }} active"
                                >
                                    {{ $rInitials }}
                                    @if ($rActive > 0)
                                        <span class="absolute -top-0.5 -right-0.5 w-4 h-4 rounded-full text-[9px] leading-none flex items-center justify-center font-bold
                                            {{ $rFull ? 'bg-amber-500 text-white' : 'bg-green-500 text-white' }}">
                                            {{ $rActive }}
                                        </span>
                                    @endif
                                </button>
                            @endforeach
                        </div>

                        {{-- Detail panel for the selected reader --}}
                        @foreach ($readers as $reader)
                            @php
                                $rProfile  = $reader->readerProfile;
                                $rInitials = $rProfile?->initials ?? strtoupper(substr($reader->name, 0, 2));
                                $rActive   = $reader->assignments->count();
                                $rMax      = $rProfile?->max_concurrent_assignments ?? 0;
                            @endphp
                            <div x-show="activeReader === {{ $reader->id }}" x-cloak
                                 x-transition:enter="transition ease-out duration-150"
                                 x-transition:enter-start="opacity-0 -translate-y-1"
                                 x-transition:enter-end="opacity-100 translate-y-0"
                                 class="mt-3 bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
                                <div class="flex items-start gap-4">
                                    <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center text-sm font-mono font-semibold text-gray-700 shrink-0">
                                        {{ $rInitials }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-baseline gap-3">
                                            <span class="font-semibold text-gray-900">{{ $rProfile?->displayName() ?? $reader->name }}</span>
                                            <span class="text-xs text-gray-400">{{ $rActive }} / {{ $rMax ?: '—' }} assignment{{ $rMax === 1 ? '' : 's' }}</span>
                                            @if ($rProfile?->paypal_email)
                                                <span class="text-xs text-gray-400">PayPal: {{ $rProfile->paypal_email }}</span>
                                            @endif
                                        </div>
                                        @if ($reader->assignments->isNotEmpty())
                                            <ul class="mt-2 space-y-1">
                                                @foreach ($reader->assignments as $ra)
                                                    <li class="flex items-center gap-2 text-sm text-gray-700">
                                                        @if ($ra->rush)
                                                            <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-400 text-amber-900 uppercase leading-none">Rush</span>
                                                        @endif
                                                        <span class="font-medium">{{ $ra->script_title }}</span>
                                                        <span class="text-gray-400">{{ $ra->authorDisplay() }}</span>
                                                        <span class="text-gray-400">&middot; {{ $ra->page_count }}pp</span>
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
                        <a href="{{ route('assignments.create') }}" class="text-gray-800 underline ml-1">Create one.</a>
                    </div>
                @else
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Age</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Order #</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Script / Author</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Pg</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Type</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Pay</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Status</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Reader</th>
                                    <th class="px-3 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                @foreach ($assignments as $assignment)
                                    @php
                                        // Age since unassigned_at
                                        $ageStr = '—';
                                        $ageTitle = '';
                                        if ($assignment->unassigned_at) {
                                            $diff = now()->diff($assignment->unassigned_at);
                                            if ($diff->days >= 1) {
                                                $ageStr = $diff->days . 'd ' . $diff->h . 'h';
                                            } elseif ($diff->h >= 1) {
                                                $ageStr = $diff->h . 'h ' . $diff->i . 'm';
                                            } else {
                                                $ageStr = max(0, $diff->i) . 'm';
                                            }
                                            $ageTitle = $assignment->unassigned_at->format('M j, Y g:ia');
                                        }

                                        // Status badge
                                        $statusColor = match($assignment->status) {
                                            'unassigned'        => 'bg-amber-100 text-amber-800',
                                            'assigned'          => 'bg-green-100 text-green-800',
                                            'completed'         => 'bg-green-100 text-green-800',
                                            'qc'                => 'bg-blue-100 text-blue-800',
                                            'incoming'          => 'bg-gray-100 text-gray-700',
                                            'cancelled'         => 'bg-red-100 text-red-700',
                                            'on_hold'           => 'bg-red-100 text-red-700',
                                            default             => 'bg-gray-100 text-gray-700',
                                        };

                                        $statusLabel = match($assignment->status) {
                                            'on_hold' => 'On Hold',
                                            'qc'      => 'QC',
                                            default   => ucfirst($assignment->status),
                                        };

                                        // Requested reader initials
                                        $reqInitials = $assignment->requestedReader?->readerProfile?->initials;

                                        // Assigned reader initials
                                        $assignedInitials = $assignment->assignedReader?->readerProfile?->initials
                                            ?? ($assignment->assignedReader ? substr($assignment->assignedReader->name, 0, 2) : null);

                                        // Rush row highlight
                                        $rowClass = ($assignment->rush && $assignment->status === 'unassigned')
                                            ? 'border-l-4 border-amber-400'
                                            : '';
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

                                        {{-- Script / Author --}}
                                        <td class="px-3 py-3">
                                            <div class="font-medium text-gray-900">{{ $assignment->script_title }}</div>
                                            <div class="text-xs text-gray-500">{{ $assignment->authorDisplay() }}</div>
                                        </td>

                                        {{-- Page count --}}
                                        <td class="px-3 py-3 whitespace-nowrap text-gray-700 tabular-nums">
                                            {{ $assignment->page_count }}
                                        </td>

                                        {{-- Rush / Regular + requested reader --}}
                                        <td class="px-3 py-3 whitespace-nowrap">
                                            @if ($assignment->rush)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-amber-400 text-amber-900 uppercase tracking-wide">Rush</span>
                                            @else
                                                <span class="text-xs text-gray-400">Regular</span>
                                            @endif
                                            @if ($reqInitials)
                                                <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-xs bg-purple-100 text-purple-700 font-mono" title="Requested reader">{{ $reqInitials }}</span>
                                            @endif
                                        </td>

                                        {{-- Pay rate --}}
                                        <td class="px-3 py-3 whitespace-nowrap text-gray-700 tabular-nums">
                                            ${{ number_format($assignment->pay_rate, 2) }}
                                        </td>

                                        {{-- Notes --}}
                                        <td class="px-3 py-3 max-w-xs">
                                            @if ($assignment->notes)
                                                <span class="text-gray-600 truncate block max-w-[180px]" title="{{ $assignment->notes }}">
                                                    {{ $assignment->notes }}
                                                </span>
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>

                                        {{-- Status (inline quick-change) --}}
                                        <td class="px-3 py-3 whitespace-nowrap">
                                            <form method="POST" action="{{ route('assignments.updateStatus', $assignment) }}">
                                                @csrf
                                                @method('PATCH')
                                                <select name="status" onchange="this.form.submit()"
                                                    class="text-xs rounded-full border-0 ring-1 ring-gray-200 py-0.5 pl-2.5 pr-6 cursor-pointer focus:ring-indigo-400 {{ $statusColor }}">
                                                    @foreach ([
                                                        'incoming'   => 'Incoming',
                                                        'unassigned' => 'Unassigned',
                                                        'assigned'   => 'Assigned',
                                                        'completed'  => 'Completed',
                                                        'qc'         => 'QC',
                                                        'on_hold'    => 'On Hold',
                                                        'cancelled'  => 'Cancelled',
                                                    ] as $value => $label)
                                                        <option value="{{ $value }}" {{ $assignment->status === $value ? 'selected' : '' }}>
                                                            {{ $label }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </form>
                                        </td>

                                        {{-- Assigned reader --}}
                                        <td class="px-3 py-3 whitespace-nowrap">
                                            @if ($assignedInitials)
                                                <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-gray-200 text-gray-700 text-xs font-mono font-semibold">
                                                    {{ $assignedInitials }}
                                                </span>
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>

                                        {{-- Actions --}}
                                        <td class="px-3 py-3 whitespace-nowrap text-right">
                                            <a href="{{ route('assignments.edit', $assignment) }}"
                                               class="text-xs text-gray-500 hover:text-gray-800 underline">Edit</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

            {{-- ===== READER VIEW ===== --}}
            @else
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                    {{-- Available assignments --}}
                    <div>
                        <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wider mb-3">Available</h3>
                        @if ($available->isEmpty())
                            <div class="bg-white rounded-lg border border-gray-200 p-8 text-center text-gray-400 text-sm">
                                No assignments available right now.
                            </div>
                        @else
                            <div class="space-y-2">
                                @foreach ($available as $assignment)
                                    @php
                                        $reqInitials = $assignment->requestedReader?->readerProfile?->initials;
                                        $isRequestedForMe = $assignment->requested_reader_id === auth()->id();
                                    @endphp
                                    <div class="bg-white rounded-lg border {{ $assignment->rush ? 'border-amber-400 border-2' : 'border-gray-200' }} p-4">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <span class="font-semibold text-gray-900">{{ $assignment->script_title }}</span>
                                                    @if ($assignment->rush)
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-amber-400 text-amber-900 uppercase">Rush</span>
                                                    @endif
                                                    @if ($isRequestedForMe)
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-700">Requested for you</span>
                                                    @endif
                                                </div>
                                                <div class="text-sm text-gray-500 mt-0.5">
                                                    {{ $assignment->authorDisplay() }} &middot; {{ $assignment->page_count }} pages &middot; ${{ number_format($assignment->pay_rate, 2) }}
                                                </div>
                                                @if ($assignment->notes)
                                                    <div class="text-xs text-gray-400 mt-1 truncate max-w-sm" title="{{ $assignment->notes }}">{{ $assignment->notes }}</div>
                                                @endif
                                            </div>
                                            <form method="POST" action="{{ route('assignments.accept', $assignment) }}" class="shrink-0">
                                                @csrf
                                                <button type="submit"
                                                        onclick="this.disabled=true; this.form.submit();"
                                                        class="inline-flex items-center px-3 py-1.5 bg-green-600 border border-transparent rounded text-xs font-semibold text-white hover:bg-green-500 transition">
                                                    Accept
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- My assignments --}}
                    <div>
                        <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wider mb-3">My Assignments</h3>
                        @if ($mine->isEmpty())
                            <div class="bg-white rounded-lg border border-gray-200 p-8 text-center text-gray-400 text-sm">
                                You haven't accepted any assignments yet.
                            </div>
                        @else
                            <div class="space-y-2">
                                @foreach ($mine as $assignment)
                                    @php
                                        $statusColor = match($assignment->status) {
                                            'assigned'  => 'bg-green-100 text-green-800',
                                            'completed' => 'bg-green-100 text-green-800',
                                            'qc'        => 'bg-blue-100 text-blue-800',
                                            default     => 'bg-gray-100 text-gray-700',
                                        };
                                        $statusLabel = $assignment->status === 'qc' ? 'QC' : ucfirst($assignment->status);
                                    @endphp
                                    <div class="bg-white rounded-lg border border-gray-200 p-4">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <span class="font-semibold text-gray-900">{{ $assignment->script_title }}</span>
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColor }}">{{ $statusLabel }}</span>
                                                </div>
                                                <div class="text-sm text-gray-500 mt-0.5">
                                                    {{ $assignment->authorDisplay() }} &middot; {{ $assignment->page_count }} pages
                                                </div>
                                            </div>
                                            <div class="shrink-0 flex items-center gap-2">
                                                @can('submitCoverage', $assignment)
                                                    <a href="{{ route('coverage.show', $assignment) }}"
                                                       class="inline-flex items-center px-3 py-1.5 bg-indigo-600 border border-transparent rounded text-xs font-semibold text-white hover:bg-indigo-500 transition">
                                                        Fill Coverage
                                                    </a>
                                                @endcan
                                                @can('cancel', $assignment)
                                                    <form method="POST" action="{{ route('assignments.cancel', $assignment) }}">
                                                        @csrf
                                                        <button type="submit"
                                                                onclick="return confirm('Return this assignment to the pool?')"
                                                                class="inline-flex items-center px-3 py-1.5 bg-white border border-gray-300 rounded text-xs font-medium text-gray-700 hover:bg-gray-50 transition">
                                                            Cancel
                                                        </button>
                                                    </form>
                                                @endcan
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                </div>
            @endif

        </div>
    </div>
</x-app-layout>
