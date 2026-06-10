<x-app-layout>
    <x-slot name="header">
<div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
            <div class="flex items-center gap-3">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Assignments</h2>
            </div>
            @can('create', \App\Models\Assignment::class)
                <a href="{{ route('assignments.create') }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition ease-in-out duration-150">
                    + Create Assignment
                </a>
            @endcan
            @if(!$canManage && isset($periodStart))
                <div class="text-xs text-gray-500 w-full sm:w-auto">
                    Current period: <span class="font-medium text-gray-700">{{ \App\Support\PayPeriod::label($periodStart) }}</span>
                    <span class="text-gray-400">&nbsp;· next payout Sat {{ $periodEnd->addHour()->format('M j') }}</span>
                </div>
            @endif
        </div>
    </x-slot>

    <style>
        @keyframes request-pulse {
            0%, 100% { border-left-color: rgb(192, 132, 252); }
            50%       { border-left-color: rgb(233, 213, 255); }
        }
        .request-pulse { animation: request-pulse 2.5s ease-in-out infinite; }

        .rush-due { color: rgb(234, 88, 12); }

        .rush-countdown { color: rgb(234, 88, 12); }
        .rush-overdue { color: rgb(220, 38, 38); font-weight: 600; }
    </style>
    @include('partials.pdf-text-layer-styles')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('tableSort', (defaultField = 'date', defaultDir = 'desc') => ({
                sortBy: defaultField,
                sortDir: defaultDir,
                init() { this.$nextTick(() => this.sort()); },
                setSort(field) {
                    if (this.sortBy === field) {
                        this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
                    } else {
                        this.sortBy = field;
                        this.sortDir = (field === 'rush' || field === 'rate') ? 'desc' : (field === 'age' ? 'desc' : 'desc');
                    }
                    this.sort();
                },
                sort() {
                    const tbody = this.$refs.sortTbody;
                    if (!tbody) return;
                    const rows = [...tbody.querySelectorAll('tr[data-sort-date]')];
                    if (rows.length < 2) return;
                    const numericFields = new Set(['date', 'age', 'rush', 'rate']);
                    // 'age' sorts by date in the opposite direction
                    const actualField = this.sortBy === 'age' ? 'date' : this.sortBy;
                    const actualDir   = this.sortBy === 'age'
                        ? (this.sortDir === 'asc' ? 'desc' : 'asc')
                        : this.sortDir;
                    rows.sort((a, b) => {
                        const key = 'sort' + actualField.charAt(0).toUpperCase() + actualField.slice(1);
                        let av = a.dataset[key] ?? '';
                        let bv = b.dataset[key] ?? '';
                        if (numericFields.has(actualField)) { av = +av || 0; bv = +bv || 0; }
                        else { av = (av || '').toLowerCase(); bv = (bv || '').toLowerCase(); }
                        if (av < bv) return actualDir === 'asc' ? -1 : 1;
                        if (av > bv) return actualDir === 'asc' ? 1 : -1;
                        return 0;
                    });
                    rows.forEach(r => tbody.appendChild(r));
                }
            }));

            Alpine.data('rushCountdown', (dueAt, dueLabel = '') => ({
                display: '',
                overdue: false,
                dueLabel: dueLabel,
                _iv: null,
                init() {
                    this._update();
                    this._iv = setInterval(() => this._update(), 1000);
                },
                destroy() { clearInterval(this._iv); },
                _update() {
                    const diff = new Date(dueAt) - Date.now();
                    if (diff <= 0) {
                        this.display = 'FAILED. Refund RUSH fee.';
                        this.overdue = true;
                        clearInterval(this._iv);
                        return;
                    }
                    const h = Math.floor(diff / 3600000);
                    const m = Math.floor((diff % 3600000) / 60000);
                    const s = Math.floor((diff % 60000) / 1000);
                    this.display = 'Due in ' + h + 'h ' + m + 'm ' + s + 's';
                }
            }));

            Alpine.data('followupCountdown', (dueAt, dueLabel = '') => ({
                display: '',
                overdue: false,
                dueLabel: dueLabel,
                _iv: null,
                init() {
                    this._update();
                    this._iv = setInterval(() => this._update(), 60000);
                },
                destroy() { clearInterval(this._iv); },
                _update() {
                    const diff = new Date(dueAt) - Date.now();
                    if (diff <= 0) {
                        this.display = 'Overdue';
                        this.overdue = true;
                        clearInterval(this._iv);
                        return;
                    }
                    const d = Math.floor(diff / 86400000);
                    const h = Math.floor((diff % 86400000) / 3600000);
                    const m = Math.floor((diff % 3600000) / 60000);
                    this.display = 'Due in ' + d + 'd ' + h + 'h ' + m + 'm';
                }
            }));
        });
    </script>

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

                {{-- ---- Assignment Notes panel ---- --}}
                @if (($assignmentNotes ?? collect())->isNotEmpty())
                <div class="mb-5">
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2 flex items-center gap-2">
                        Reader Notes
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">{{ $assignmentNotes->count() }}</span>
                    </h3>
                    <div class="space-y-2">
                        @foreach ($assignmentNotes as $aNote)
                            @php
                                $aNoteAssignment = $aNote->assignment;
                                $aNoteAuthor     = $aNote->author;
                                $aNoteInitials   = $aNoteAuthor?->readerProfile?->initials
                                    ?? ($aNoteAuthor ? strtoupper(substr($aNoteAuthor->name, 0, 2)) : '??');
                                $aNotePhotoRaw   = $aNoteAuthor?->readerProfile?->photo ?? $aNoteAuthor?->editorProfile?->photo;
                                $aNotePhotoUrl   = $aNotePhotoRaw ? asset('storage/' . $aNotePhotoRaw) : null;
                            @endphp
                            <div x-data="{ open: false }"
                                 class="border rounded-lg bg-blue-50 border-blue-200"
                                 id="anote-{{ $aNote->id }}">
                                <div @click="open = !open"
                                     class="flex items-center gap-3 flex-wrap px-4 py-3 cursor-pointer">
                                    <span class="relative inline-flex items-center justify-center w-7 h-7 rounded-full bg-gray-200 text-gray-700 text-xs font-mono font-semibold shrink-0">
                                        @if ($aNotePhotoUrl)
                                            <span class="absolute inset-0 rounded-full overflow-hidden">
                                                <img src="{{ $aNotePhotoUrl }}" alt="{{ $aNoteInitials }}" class="w-full h-full object-cover" />
                                            </span>
                                        @else
                                            {{ $aNoteInitials }}
                                        @endif
                                    </span>
                                    <span class="text-xs font-mono text-gray-500">{{ $aNoteAssignment?->order_number }}</span>
                                    <span class="text-sm font-medium text-gray-800">{{ $aNoteAssignment?->script_title }}</span>
                                    <span class="text-[10px] text-gray-400">{{ $aNote->created_at->setTimezone($appTimezone)->format('M j, g:ia') }}</span>
                                    <span class="ml-auto flex items-center gap-2">
                                        <button type="button"
                                                @click.stop
                                                x-data
                                                @click="fetch('{{ route('assignment-notes.dismiss', $aNote) }}', {
                                                    method: 'POST',
                                                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' }
                                                }).then(() => document.getElementById('anote-{{ $aNote->id }}').remove())"
                                                class="text-blue-300 hover:text-blue-600 text-sm leading-none transition"
                                                title="Dismiss">✕</button>
                                        <button type="button" @click.stop="open = !open"
                                                class="text-xs text-indigo-500 hover:text-indigo-700 underline">
                                            <span x-text="open ? 'Hide' : 'Reply / View'"></span>
                                        </button>
                                    </span>
                                </div>

                                <div x-show="open" x-cloak class="border-t border-blue-200 px-4 py-3 space-y-3">
                                    <div class="text-sm text-gray-800 whitespace-pre-wrap bg-white border border-blue-100 rounded px-3 py-2">{{ $aNote->body }}</div>

                                    @foreach ($aNote->replies as $aNoteReply)
                                        <div class="ml-4 border-l-2 border-indigo-200 pl-3">
                                            <div class="text-[10px] text-gray-400 mb-0.5">
                                                {{ $aNoteReply->author?->name }} · {{ $aNoteReply->created_at->setTimezone($appTimezone)->format('M j, g:ia') }}
                                            </div>
                                            <div class="text-sm text-gray-700 whitespace-pre-wrap">{{ $aNoteReply->body }}</div>
                                        </div>
                                    @endforeach

                                    <form method="POST" action="{{ route('assignment-notes.reply', $aNote) }}" class="flex gap-2 pt-1">
                                        @csrf
                                        <textarea name="body" rows="2" maxlength="1000" required
                                                  placeholder="Reply to reader…"
                                                  class="flex-1 text-sm border border-gray-300 rounded px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-indigo-400 resize-y"></textarea>
                                        <button type="submit" class="self-end px-3 py-1.5 text-xs bg-indigo-600 text-white rounded hover:bg-indigo-700 whitespace-nowrap">Send Reply</button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- ---- Pending Profile Approvals panel ---- --}}
                @if (($pendingApprovals ?? collect())->isNotEmpty())
                <div class="mb-5">
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2 flex items-center gap-2">
                        Profile Approvals Needed
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800">{{ $pendingApprovals->count() }}</span>
                    </h3>
                    <div class="space-y-2">
                        @foreach ($pendingApprovals as $pa)
                            @php
                                $paProfile  = $pa->isReader() ? $pa->readerProfile : $pa->editorProfile;
                                $paInitials = $paProfile?->initials ?? strtoupper(substr($pa->name, 0, 2));
                                $paEditUrl  = $pa->isReader()
                                    ? route('readers.edit', $pa)
                                    : route('admin.editors.edit', $pa);
                                $paItems = collect([
                                    $paProfile?->photo_pending       ? 'Reader icon'    : null,
                                    $paProfile?->about_photo_pending ? 'About photo'    : null,
                                    $paProfile?->bio_pending         ? 'Bio'            : null,
                                ])->filter()->implode(', ');
                            @endphp
                            <a href="{{ $paEditUrl }}" class="flex items-center gap-3 px-4 py-3 border border-indigo-200 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition-colors">
                                <div class="w-8 h-8 rounded-full bg-indigo-200 flex items-center justify-center text-xs font-mono font-semibold text-indigo-700 shrink-0">
                                    {{ $paInitials }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <span class="text-sm font-medium text-gray-800">{{ $paProfile?->displayName() ?? $pa->name }}</span>
                                    <span class="text-xs text-gray-500 ml-2">({{ ucfirst($pa->role) }})</span>
                                </div>
                                <span class="text-xs text-indigo-700 font-medium shrink-0">{{ $paItems }} pending</span>
                                <svg class="w-4 h-4 text-indigo-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </a>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- ---- Followup Questions panel (pending only) ---- --}}
                @php $followupsTop = ($followups ?? collect())->whereIn('status', ['pending', 'answered']); @endphp
                @if ($followupsTop->isNotEmpty())
                <div class="mb-5">
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2 flex items-center gap-2">
                        Followup Questions
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">{{ $followupsTop->count() }}</span>
                    </h3>
                    <div class="space-y-2">
                        @foreach ($followupsTop as $fq)
                            @php
                                $fqAssignment = $fq->assignment;
                                $fqReader     = $fqAssignment?->assignedReader;
                                $fqInitials   = $fqReader?->readerProfile?->initials ?? ($fqReader ? strtoupper(substr($fqReader->name, 0, 2)) : '??');
                                $fqDeadline   = $fq->deadlineAt();
                                $fqStatus     = $fq->status;
                                $fqRowColor   = $fqStatus === 'answered' ? 'border-green-200 bg-green-50' : 'border-amber-200 bg-amber-50';
                            @endphp
                            <div x-data="{ open: false }" class="border rounded-lg {{ $fqRowColor }}">
                                <div @click="open = !open" class="flex items-center gap-3 flex-wrap px-4 py-3 cursor-pointer">
                                    <span class="text-xs font-mono text-gray-500">{{ $fqAssignment?->order_number }}</span>
                                    <span class="text-sm font-medium text-gray-800">{{ $fqAssignment?->script_title }}</span>
                                    <span class="text-xs text-gray-500">· Reader {{ $fqInitials }}</span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                        {{ $fqStatus === 'pending' ? 'bg-gray-100 text-gray-600' : ($fqStatus === 'answered' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700') }}">
                                        {{ ucfirst($fqStatus) }}
                                    </span>
                                    @if ($fqDeadline && $fqStatus === 'unanswered')
                                        <div x-data="followupCountdown('{{ $fqDeadline->utc()->toIso8601String() }}', @js($fqDeadline->setTimezone($appTimezone)->format('M j, g:ia')))"
                                             x-text="display" :class="overdue ? 'rush-overdue' : 'text-amber-600'" class="text-xs" @click.stop></div>
                                    @endif
                                    <span class="ml-auto flex items-center gap-2">
                                        @if ($fqStatus === 'pending')
                                            <form method="POST" action="{{ route('followups.destroy', $fq) }}"
                                                  onsubmit="return confirm('Delete this reader\'s followup question?')"
                                                  @click.stop>
                                                @csrf @method('DELETE')
                                                <button type="submit" title="Delete this reader's question"
                                                        class="text-red-300 hover:text-red-600 text-sm leading-none transition">✕</button>
                                            </form>
                                        @endif
                                        <button type="button" @click.stop="open = !open"
                                                class="text-xs text-indigo-500 hover:text-indigo-700 underline">
                                            <span x-text="open ? 'Hide' : 'Edit / Review'"></span>
                                        </button>
                                    </span>
                                </div>

                                <div x-show="open" x-cloak class="mt-3 space-y-3 border-t border-gray-200 pt-3 px-4 pb-3">
                                    <form method="POST" action="{{ route('followups.update', $fq) }}" class="space-y-3">
                                        @csrf @method('PATCH')

                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1">Customer's questions</label>
                                            <div class="text-xs text-gray-500 italic bg-white border border-gray-200 rounded px-2 py-1.5">{{ $fq->customer_questions ?? '—' }}</div>
                                        </div>

                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1">Edited questions shown to reader</label>
                                            <textarea name="edited_questions" rows="3"
                                                      class="w-full text-sm border border-gray-300 rounded px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-indigo-400">{{ old('edited_questions', $fq->edited_questions ?? $fq->customer_questions) }}</textarea>
                                        </div>

                                        @if ($fqStatus === 'answered')
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1">Reader's response</label>
                                            <div class="text-xs text-gray-500 italic bg-white border border-gray-200 rounded px-2 py-1.5">{{ $fq->reader_response ?? '—' }}</div>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1">Edited response sent to customer</label>
                                            <textarea name="edited_response" rows="4"
                                                      class="w-full text-sm border border-gray-300 rounded px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-indigo-400">{{ old('edited_response', $fq->edited_response ?? $fq->reader_response) }}</textarea>
                                        </div>
                                        @endif

                                        <div class="flex items-center gap-2 flex-wrap">
                                            <select name="status" class="text-xs border border-gray-300 rounded px-2 py-1">
                                                @foreach (['pending' => 'Pending', 'unanswered' => 'Send to reader', 'answered' => 'Answered'] as $val => $label)
                                                    <option value="{{ $val }}" {{ $fq->status === $val ? 'selected' : '' }}>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="px-3 py-1 text-xs bg-indigo-600 text-white rounded hover:bg-indigo-700">Save</button>
                                        </div>
                                    </form>

                                    @if ($fqStatus === 'answered')
                                    @php
                                        $fqHsId = $fqAssignment?->helpscout_ticket_number
                                            ?: $fqAssignment?->helpscoutConversation?->helpscout_conversation_id;
                                    @endphp
                                    <form method="POST" action="{{ route('followups.complete', $fq) }}">
                                        @csrf
                                        <button type="submit"
                                                class="px-3 py-1 text-xs bg-green-600 text-white rounded hover:bg-green-700"
                                                @if ($fqHsId) onclick="window.open('https://secure.helpscout.net/conversation/{{ $fqHsId }}/', '_blank')" @endif>
                                            Mark Complete &amp; Create HelpScout Draft
                                        </button>
                                    </form>
                                    @endif

                                    <form method="POST" action="{{ route('followups.destroy', $fq) }}"
                                          onsubmit="return confirm('Delete this followup question?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="px-3 py-1 text-xs bg-red-100 text-red-700 border border-red-300 rounded hover:bg-red-200">Delete</button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Staff panel: editors first, then readers --}}
                @if ($editors->isNotEmpty() || $readers->isNotEmpty())
                    <div class="mb-5" x-data="{ activeStaff: null }">
                        <div class="flex items-center gap-2 flex-wrap">

                            {{-- Editors --}}
                            @foreach ($editors as $editor)
                                @php
                                    $eProfile   = $editor->editorProfile;
                                    $eInitials  = $eProfile?->initials ?? strtoupper(substr($editor->name, 0, 2));
                                    $eActive    = $editor->assignments->count();
                                    $ePhotoUrl  = $eProfile?->photo ? asset('storage/' . $eProfile->photo) : null;
                                    $eOnline    = $editor->isOnline();
                                @endphp
                                <div class="flex flex-col items-center gap-0.5">
                                    <button type="button"
                                        @click="activeStaff = activeStaff === 'e{{ $editor->id }}' ? null : 'e{{ $editor->id }}'"
                                        :class="activeStaff === 'e{{ $editor->id }}' ? 'ring-2 ring-offset-1 ring-indigo-400' : ''"
                                        class="relative inline-flex items-center justify-center w-9 h-9 rounded-full text-xs font-mono font-semibold transition-all cursor-pointer bg-indigo-100 text-indigo-700 hover:bg-indigo-200"
                                        title="{{ $eProfile?->displayName() ?? $editor->name }}{{ $eProfile?->title ? ' · ' . $eProfile->title : '' }} (Editor){{ $eOnline ? ' · Online' : '' }} — {{ $eActive }} active"
                                    >
                                        @if ($ePhotoUrl)
                                            <span class="absolute inset-0 rounded-full overflow-hidden">
                                                <img src="{{ $ePhotoUrl }}" alt="{{ $eInitials }}" class="w-full h-full object-cover" />
                                            </span>
                                        @else
                                            {{ $eInitials }}
                                        @endif
                                        @if ($eActive > 0)
                                            <span class="absolute -top-0.5 -right-0.5 w-4 h-4 rounded-full text-[9px] leading-none flex items-center justify-center font-bold z-10 bg-indigo-500 text-white">
                                                {{ $eActive }}
                                            </span>
                                        @endif
                                        @if ($eOnline)
                                            <span class="absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full bg-green-400 ring-2 ring-white z-20"></span>
                                        @endif
                                    </button>
                                    <span class="text-[9px] text-indigo-400 font-mono leading-none">{{ $eInitials }}</span>
                                </div>
                            @endforeach

                            {{-- Divider between editors and readers --}}
                            @if ($editors->isNotEmpty() && $readers->isNotEmpty())
                                <div class="w-px h-8 bg-gray-200 mx-1 self-center"></div>
                            @endif

                            {{-- Readers --}}
                            @foreach ($readers as $reader)
                                @php
                                    $rProfile  = $reader->readerProfile;
                                    $rInitials = $rProfile?->initials ?? strtoupper(substr($reader->name, 0, 2));
                                    $rActive   = $reader->assignments->count();
                                    $rMax      = $capacityOverride > 0 ? $capacityOverride : ($rProfile?->max_concurrent_assignments ?? 0);
                                    $rFull     = $rMax > 0 && $rActive >= $rMax;
                                    $rPhotoUrl    = $rProfile?->photo ? asset('storage/' . $rProfile->photo) : null;
                                    $rOnline      = $reader->isOnline();
                                    $rUnavailable = $rProfile?->availability === 'unavailable';
                                @endphp
                                <div class="flex flex-col items-center gap-0.5">
                                    <button type="button"
                                        @click="activeStaff = activeStaff === 'r{{ $reader->id }}' ? null : 'r{{ $reader->id }}'"
                                        :class="activeStaff === 'r{{ $reader->id }}' ? 'ring-2 ring-offset-1 ring-gray-400' : ''"
                                        class="relative inline-flex items-center justify-center w-9 h-9 rounded-full text-xs font-mono font-semibold transition-all cursor-pointer
                                            {{ $rFull ? 'bg-amber-200 text-amber-800' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}
                                            {{ $rUnavailable ? 'outline outline-2 outline-dashed outline-red-400 outline-offset-1' : '' }}"
                                        title="{{ $rProfile?->displayName() ?? $reader->name }}{{ $rProfile?->title ? ' · ' . $rProfile->title : '' }}{{ $rOnline ? ' · Online' : '' }} — {{ $rActive }}/{{ $rMax ?: '?' }} active"
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
                                        @if ($rOnline)
                                            <span class="absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full bg-green-400 ring-2 ring-white z-20"></span>
                                        @endif
                                    </button>
                                    <span class="text-[9px] text-gray-400 font-mono leading-none">{{ $rInitials }}</span>
                                </div>
                            @endforeach
                        </div>

                        {{-- Detail panels --}}
                        @foreach ($editors as $editor)
                            @php
                                $eProfile  = $editor->editorProfile;
                                $eInitials = $eProfile?->initials ?? strtoupper(substr($editor->name, 0, 2));
                                $eActive   = $editor->assignments->count();
                                $ePhotoUrl = $eProfile?->photo ? asset('storage/' . $eProfile->photo) : null;
                                $eOnline   = $editor->isOnline();
                            @endphp
                            <div x-show="activeStaff === 'e{{ $editor->id }}'" x-cloak
                                 x-transition:enter="transition ease-out duration-150"
                                 x-transition:enter-start="opacity-0 -translate-y-1"
                                 x-transition:enter-end="opacity-100 translate-y-0"
                                 class="mt-3 bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
                                <div class="flex items-start gap-4">
                                    <div class="relative w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center text-sm font-mono font-semibold text-indigo-700 shrink-0 overflow-hidden">
                                        @if ($ePhotoUrl)
                                            <img src="{{ $ePhotoUrl }}" alt="{{ $eInitials }}" class="absolute inset-0 w-full h-full object-cover" />
                                        @else
                                            {{ $eInitials }}
                                        @endif
                                        @if ($eOnline)
                                            <span class="absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full bg-green-400 ring-2 ring-white z-10"></span>
                                        @endif
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-baseline gap-3 flex-wrap">
                                            <span class="font-semibold text-gray-900">{{ $eProfile?->displayName() ?? $editor->name }}</span>
                                            <span class="text-xs text-indigo-500 font-medium">Editor</span>
                                            @if ($eOnline)
                                                <span class="text-xs text-green-600 font-medium">● Online</span>
                                            @endif
                                            <span class="text-xs text-gray-400">{{ $eActive }} active assignment{{ $eActive === 1 ? '' : 's' }}</span>
                                            @if ($eProfile?->paypal_email)
                                                <span class="text-xs text-gray-400">PayPal: {{ $eProfile->paypal_email }}</span>
                                            @endif
                                            <div class="ml-auto flex items-center gap-3">
                                                <a href="{{ route('staff.draft-email', $editor) }}" target="_blank"
                                                   class="text-xs text-gray-400 hover:text-indigo-600 transition"
                                                   title="Create HelpScout draft to {{ $editor->email }}">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="inline w-3.5 h-3.5 mr-0.5 -mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>Email
                                                </a>
                                                <a href="{{ route('admin.editors.edit', $editor) }}"
                                                   class="text-xs text-indigo-500 hover:text-indigo-700 underline">Edit Profile</a>
                                            </div>
                                        </div>
                                        @if ($eProfile?->title)
                                            <div class="text-xs text-gray-400 mt-0.5">{{ $eProfile->title }}</div>
                                        @endif
                                        @if ($eProfile?->custom_message)
                                            <p class="mt-1.5 text-xs font-semibold text-indigo-600 leading-relaxed" style="white-space:pre-line">{{ $eProfile->custom_message }}</p>
                                        @endif
                                        @if ($editor->assignments->isNotEmpty())
                                            <ul class="mt-2 space-y-1">
                                                @foreach ($editor->assignments as $ra)
                                                    <li class="flex items-center gap-2 text-sm text-gray-700">
                                                        @if ($ra->rush)
                                                            <span x-data="rushCountdown('{{ $ra->created_at->copy()->addHours(23)->utc()->toIso8601String() }}', @js($ra->created_at->copy()->addHours(23)->setTimezone($appTimezone ?? 'UTC')->format('M j, g:ia')))"><span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-400 text-amber-900 uppercase leading-none">Rush</span><span class="rush-due text-[9px] ml-1" x-text="(overdue ? 'Was due by ' : 'Due by ') + dueLabel"></span><span x-text="display" :class="overdue ? 'rush-overdue' : 'rush-countdown'" class="text-[9px] ml-1"></span></span>
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

                        @foreach ($readers as $reader)
                            @php
                                $rProfile  = $reader->readerProfile;
                                $rInitials = $rProfile?->initials ?? strtoupper(substr($reader->name, 0, 2));
                                $rActive   = $reader->assignments->count();
                                $rMax      = $capacityOverride > 0 ? $capacityOverride : ($rProfile?->max_concurrent_assignments ?? 0);
                                $rPhotoUrl = $rProfile?->photo ? asset('storage/' . $rProfile->photo) : null;
                                $rOnline   = $reader->isOnline();
                                $rStats    = $readerWeekStats[$reader->id] ?? null;
                            @endphp
                            <div x-show="activeStaff === 'r{{ $reader->id }}'" x-cloak
                                 x-transition:enter="transition ease-out duration-150"
                                 x-transition:enter-start="opacity-0 -translate-y-1"
                                 x-transition:enter-end="opacity-100 translate-y-0"
                                 class="mt-3 bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
                                <div class="flex items-start gap-4">
                                    <div class="relative w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center text-sm font-mono font-semibold text-gray-700 shrink-0 overflow-hidden">
                                        @if ($rPhotoUrl)
                                            <img src="{{ $rPhotoUrl }}" alt="{{ $rInitials }}" class="absolute inset-0 w-full h-full object-cover" />
                                        @else
                                            {{ $rInitials }}
                                        @endif
                                        @if ($rOnline)
                                            <span class="absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full bg-green-400 ring-2 ring-white z-10"></span>
                                        @endif
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-baseline gap-3 flex-wrap">
                                            <span class="font-semibold text-gray-900">{{ $rProfile?->displayName() ?? $reader->name }}</span>
                                            @if ($rOnline)
                                                <span class="text-xs text-green-600 font-medium">● Online</span>
                                            @endif
                                            <span class="text-xs text-gray-400">{{ $rActive }} / {{ $rMax ?: '—' }} active</span>
                                            @if ($rProfile?->paypal_email)
                                                <span class="text-xs text-gray-400">PayPal: {{ $rProfile->paypal_email }}</span>
                                            @endif
                                            <div class="ml-auto flex items-center gap-3">
                                                <a href="{{ route('staff.draft-email', $reader) }}" target="_blank"
                                                   class="text-xs text-gray-400 hover:text-indigo-600 transition"
                                                   title="Create HelpScout draft to {{ $reader->email }}">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="inline w-3.5 h-3.5 mr-0.5 -mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>Email
                                                </a>
                                                <a href="{{ route('readers.edit', $reader) }}"
                                                   class="text-xs text-indigo-500 hover:text-indigo-700 underline">Edit Profile</a>
                                            </div>
                                        </div>

                                        @if ($rProfile?->title)
                                            <div class="text-xs text-gray-400 mt-0.5">{{ $rProfile->title }}</div>
                                        @endif
                                        @if ($rProfile?->custom_message)
                                            <p class="mt-1.5 text-xs font-semibold text-indigo-600 leading-relaxed" style="white-space:pre-line">{{ $rProfile->custom_message }}</p>
                                        @endif

                                        {{-- Weekly pay stats --}}
                                        @if ($rStats)
                                            <div class="mt-2 flex gap-4">
                                                <div class="text-xs">
                                                    <span class="text-gray-400">This week</span>
                                                    <span class="ml-1 font-medium text-gray-500">({{ $rStats['this_label'] }})</span>
                                                    <span class="ml-2 font-semibold text-green-700">${{ number_format($rStats['this_pay'], 2) }}</span>
                                                    <span class="ml-1 text-gray-400">· {{ $rStats['this_count'] }} completed</span>
                                                </div>
                                                <div class="text-xs border-l border-gray-200 pl-4">
                                                    <span class="text-gray-400">Last week</span>
                                                    <span class="ml-1 font-medium text-gray-500">({{ $rStats['last_label'] }})</span>
                                                    <span class="ml-2 font-semibold text-gray-700">${{ number_format($rStats['last_pay'], 2) }}</span>
                                                    <span class="ml-1 text-gray-400">· {{ $rStats['last_count'] }} completed</span>
                                                </div>
                                            </div>
                                        @endif

                                        {{-- Active assignments --}}
                                        @if ($reader->assignments->isNotEmpty())
                                            <ul class="mt-2 space-y-1">
                                                @foreach ($reader->assignments as $ra)
                                                    <li class="flex items-center gap-2 text-sm text-gray-700">
                                                        @if ($ra->rush)
                                                            <span x-data="rushCountdown('{{ $ra->created_at->copy()->addHours(23)->utc()->toIso8601String() }}', @js($ra->created_at->copy()->addHours(23)->setTimezone($appTimezone ?? 'UTC')->format('M j, g:ia')))"><span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-400 text-amber-900 uppercase leading-none">Rush</span><span class="rush-due text-[9px] ml-1" x-text="(overdue ? 'Was due by ' : 'Due by ') + dueLabel"></span><span x-text="display" :class="overdue ? 'rush-overdue' : 'rush-countdown'" class="text-[9px] ml-1"></span></span>
                                                        @endif
                                                        <span class="font-medium">{{ $ra->script_title }}</span>
                                                        <span class="text-gray-400">{{ $ra->writer_name }}</span>
                                                        <span class="text-gray-400">&middot; {{ $ra->page_count }} pages</span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @else
                                            <p class="mt-2 text-sm text-gray-400">No active assignments.</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div x-data="{ search: '' }">

                {{-- Search --}}
                <div class="mb-3 flex items-center gap-2">
                    <div class="relative flex-1 max-w-sm">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 111 11a6 6 0 0116 0z"/>
                        </svg>
                        <input type="text" x-model="search"
                               placeholder="Search order #, title, writer, reader…"
                               class="w-full pl-9 pr-8 py-1.5 text-sm border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 bg-white" />
                        <button x-show="search" @click="search = ''"
                                class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 text-lg leading-none">&times;</button>
                    </div>
                </div>

                @php
                    $hasTier1 = $tier1Assignments->isNotEmpty();
                    $hasTier2 = $tier2Assignments->isNotEmpty();
                @endphp
                @if (!$hasTier1 && !$hasTier2)
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-12 text-center text-gray-500">
                        No assignments yet.
                    </div>
                @else
                    @if ($hasTier1)
                    <div class="{{ $hasTier2 ? 'mb-6' : '' }}">
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2 px-1">Tier 1 Assignments</h3>
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden" x-data="tableSort()">
                            <div class="flex flex-wrap items-center gap-1.5 px-4 py-2 bg-gray-50 border-b border-gray-200">
                                <span class="text-[10px] font-medium text-gray-500 uppercase tracking-wide mr-1">Sort:</span>
                            @foreach (['date' => 'Date', 'age' => 'Age', 'rush' => 'Rush', 'type' => 'Type', 'rate' => 'Rate', 'status' => 'Status', 'acceptedby' => 'Accepted by'] as $sf => $sl)
                                <button type="button" @click="setSort('{{ $sf }}')"
                                        :class="sortBy === '{{ $sf }}' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-500 border-gray-300 hover:bg-gray-100'"
                                        class="inline-flex items-center px-2 py-0.5 rounded border text-[11px] transition-colors whitespace-nowrap">
                                    {{ $sl }}<span x-show="sortBy === '{{ $sf }}'" x-text="sortDir === 'asc' ? ' ↑' : ' ↓'" class="ml-0.5"></span>
                                </button>
                            @endforeach
                            </div>
                            <div class="overflow-x-auto">
                            <table class="w-full min-w-[600px] divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap w-52">Order Details</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assignment</th>
                                    <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap w-44">Accepted by</th>
                                </tr>
                            </thead>
                                <tbody class="bg-white divide-y divide-gray-100" x-ref="sortTbody">
                                    @foreach ($tier1Assignments as $assignment)
                                        @include('assignments.partials.admin-assignment-row')
                                    @endforeach
                                </tbody>
                            </table>
                            </div>
                        </div>
                    </div>
                    @endif

                    @if ($hasTier2)
                    <div>
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2 px-1">Tier 2 Assignments</h3>
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden" x-data="tableSort()">
                            <div class="flex flex-wrap items-center gap-1.5 px-4 py-2 bg-gray-50 border-b border-gray-200">
                                <span class="text-[10px] font-medium text-gray-500 uppercase tracking-wide mr-1">Sort:</span>
                            @foreach (['date' => 'Date', 'age' => 'Age', 'rush' => 'Rush', 'type' => 'Type', 'rate' => 'Rate', 'status' => 'Status', 'acceptedby' => 'Accepted by'] as $sf => $sl)
                                <button type="button" @click="setSort('{{ $sf }}')"
                                        :class="sortBy === '{{ $sf }}' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-500 border-gray-300 hover:bg-gray-100'"
                                        class="inline-flex items-center px-2 py-0.5 rounded border text-[11px] transition-colors whitespace-nowrap">
                                    {{ $sl }}<span x-show="sortBy === '{{ $sf }}'" x-text="sortDir === 'asc' ? ' ↑' : ' ↓'" class="ml-0.5"></span>
                                </button>
                            @endforeach
                            </div>
                            <div class="overflow-x-auto">
                            <table class="w-full min-w-[600px] divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap w-52">Order Details</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assignment</th>
                                    <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap w-44">Accepted by</th>
                                </tr>
                            </thead>
                                <tbody class="bg-white divide-y divide-gray-100" x-ref="sortTbody">
                                    @foreach ($tier2Assignments as $assignment)
                                        @include('assignments.partials.admin-assignment-row')
                                    @endforeach
                                </tbody>
                            </table>
                            </div>
                        </div>
                    </div>
                    @endif
                @endif

            {{-- ===== FORMATTING / PROOFREADING SECTION (admin only) ===== --}}
            @if ($formatting->isNotEmpty())
                <div class="mt-6">
                    <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2 px-1">Formatting / Proofreading</h3>
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                        <div class="overflow-x-auto">
                        <table class="w-full min-w-[600px] divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap w-52">Order Details</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assignment</th>
                                    <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap w-44">Accepted by</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                @foreach ($formatting as $assignment)
                                    @php
                                        $diff        = $assignment->created_at ? now()->diff($assignment->created_at) : null;
                                        $ageStr      = $diff
                                            ? ($diff->days >= 1
                                                ? ($diff->days . 'd ' . $diff->h . 'h')
                                                : ($diff->h >= 1 ? ($diff->h . 'h ' . $diff->i . 'm') : (max(0, $diff->i) . 'm')))
                                            : '—';
                                        $ageTitle    = $assignment->created_at?->copy()->setTimezone($appTimezone ?? 'UTC')->format('D M j, Y g:ia T') ?? '—';
                                        $ageHours    = $diff ? ($diff->days * 24 + $diff->h) : 0;
                                        $ageT        = $ageThresholds[$assignment->assignment_type] ?? ['yellow' => 96, 'orange' => 192, 'red' => 336];
                                        $ageColor    = match(true) {
                                            $ageHours >= $ageT['red']    => 'text-red-600 font-semibold',
                                            $ageHours >= $ageT['orange'] => 'text-orange-500 font-semibold',
                                            $ageHours >= $ageT['yellow'] => 'text-yellow-600',
                                            default                     => 'text-green-600',
                                        };
                                        $accDiff     = $assignment->accepted_at ? now()->diff($assignment->accepted_at) : null;
                                        $accStr      = $accDiff
                                            ? ($accDiff->days >= 1
                                                ? ($accDiff->days . 'd ' . $accDiff->h . 'h')
                                                : ($accDiff->h >= 1 ? ($accDiff->h . 'h ' . $accDiff->i . 'm') : (max(0, $accDiff->i) . 'm')))
                                            : null;
                                        $accTitle    = $assignment->accepted_at?->copy()->setTimezone($appTimezone ?? 'UTC')->format('D M j, Y g:ia T') ?? null;
                                        $typeLabel   = $assignment->assignment_type === 'formatting' ? 'Formatting' : 'Proofreading';
                                        $downloadUrl = ($assignment->hasCloudScript() && \App\Support\Permission::check('script.download'))
                                            ? route('assignments.downloadScript', $assignment)
                                            : null;

                                        $assignedInitials = $assignment->assignedReader?->readerProfile?->initials
                                            ?? $assignment->assignedReader?->editorProfile?->initials
                                            ?? ($assignment->assignedReader ? strtoupper(substr($assignment->assignedReader->name, 0, 2)) : null);
                                        $assignedPhoto    = $assignment->assignedReader?->readerProfile?->photo
                                            ?? $assignment->assignedReader?->editorProfile?->photo;
                                        $assignedPhotoUrl = $assignedPhoto ? asset('storage/' . $assignedPhoto) : null;

                                        $statusColor = match($assignment->status) {
                                            'unassigned'       => 'bg-amber-100 text-amber-800',
                                            'assigned'         => 'bg-green-100 text-green-800',
                                            'completed'        => 'bg-green-100 text-green-800',
                                            'qc'               => 'bg-blue-100 text-blue-800',
                                            'incoming'         => 'bg-gray-100 text-gray-700',
                                            'cancelled'        => 'bg-red-100 text-red-700',
                                            'on_hold_customer' => 'bg-red-100 text-red-700',
                                            'on_hold_sr'       => 'bg-red-100 text-red-700',
                                            'needs_attention'  => 'bg-orange-100 text-orange-800',
                                            default            => 'bg-gray-100 text-gray-700',
                                        };

                                        $rowClass = ($assignment->rush && $assignment->status === 'unassigned')
                                            ? 'border-l-4 border-amber-400'
                                            : '';

                                        $searchStr = strtolower(implode(' ', array_filter([
                                            $assignment->order_number,
                                            $assignment->script_title,
                                            $assignment->writer_name,
                                        ])));
                                    @endphp
                                    <tr class="hover:bg-gray-50 {{ $rowClass }} cursor-pointer"
                                        x-show="!search || $el.dataset.search.includes(search.toLowerCase())"
                                        data-search="{{ $searchStr }}"
                                        @click="if (!$event.target.closest('a, button, select, textarea, input, form')) window.location = @js(route('assignments.edit', $assignment))">
                                        {{-- Order Details (first): portal link, age, HelpScout --}}
                                        @php
                                            $hsId = $assignment->helpscout_ticket_number ?: $assignment->helpscoutConversation?->helpscout_conversation_id;
                                            $wooOrderUrl = ($assignment->vendor === 'sr' && is_numeric($assignment->order_number))
                                                ? route('woo-orders.show', $assignment->order_number)
                                                : null;
                                        @endphp
                                        <td class="px-3 py-3 whitespace-nowrap">
                                            @if ($wooOrderUrl)
                                                <a href="{{ $wooOrderUrl }}" class="font-mono text-gray-700 hover:text-indigo-600 hover:underline">{{ $assignment->order_number }}</a>
                                            @else
                                                <span class="font-mono text-gray-700">{{ $assignment->order_number }}</span>
                                            @endif
                                            <div class="mt-1 text-[10px] text-gray-400 tabular-nums">{{ $assignment->created_at?->copy()->setTimezone($appTimezone ?? 'UTC')->format('D M j, Y g:ia') }}</div>
                                            <div class="text-xs tabular-nums {{ $ageColor }}" title="{{ $ageTitle }}">
                                                Age: {{ $ageStr }}
                                                @if ($assignment->rush)
                                                    <div x-data="rushCountdown('{{ $assignment->created_at->copy()->addHours(23)->utc()->toIso8601String() }}', @js($assignment->created_at->copy()->addHours(23)->setTimezone($appTimezone ?? 'UTC')->format('M j, g:ia')))"><div class="mt-0.5"><span class="inline-flex px-1 py-px rounded text-[9px] font-bold bg-amber-400 text-amber-900 uppercase leading-none">Rush</span> <span class="rush-due text-[9px]" x-text="(overdue ? 'Was due by ' : 'Due by ') + dueLabel"></span></div><div x-text="display" :class="overdue ? 'rush-overdue' : 'rush-countdown'" class="text-[9px]"></div></div>
                                                @endif
                                            </div>
                                            @if ($hsId)
                                                <div class="mt-1 flex items-center gap-1.5">
                                                    <a href="https://secure.helpscout.net/conversation/{{ $hsId }}/"
                                                       target="_blank" rel="noopener noreferrer"
                                                       title="HelpScout ticket"
                                                       class="inline-flex text-gray-400 hover:text-indigo-600">
                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3.5 h-3.5">
                                                            <path fill-rule="evenodd" d="M2 5a2 2 0 012-2h12a2 2 0 012 2v7a2 2 0 01-2 2H6l-4 4V5z" clip-rule="evenodd"/>
                                                        </svg>
                                                    </a>
                                                    @if ($assignment->status === 'incoming' && $assignment->page_count > 120)
                                                        <span x-data="{ busy: false, sent: false, err: '' }">
                                                            <button type="button"
                                                                    :disabled="busy || sent"
                                                                    @click.stop="
                                                                        busy = true; err = '';
                                                                        fetch(@js(route('assignments.over-120', $assignment)), {
                                                                            method: 'POST',
                                                                            headers: {
                                                                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                                                                'Accept': 'application/json',
                                                                            }
                                                                        })
                                                                        .then(r => r.json())
                                                                        .then(d => {
                                                                            busy = false;
                                                                            if (d.error) { err = d.error; }
                                                                            else { sent = true; window.open(d.url, '_blank'); }
                                                                        })
                                                                        .catch(() => { busy = false; err = 'Failed'; })
                                                                    "
                                                                    class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold transition-colors disabled:opacity-50 whitespace-nowrap"
                                                                    :class="sent ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700 hover:bg-red-200'"
                                                                    x-text="busy ? '…' : (sent ? '✓ Drafted' : 'Over 120')">
                                                            </button>
                                                            <span x-show="err" x-text="err" class="text-[10px] text-red-500 ml-0.5"></span>
                                                        </span>
                                                    @endif
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-3 py-3">
                                            <div class="text-[10px] text-gray-400 uppercase tracking-wide mb-0.5">{{ $typeLabel }}</div>
                                            @if ($downloadUrl)
                                                <a href="{{ $downloadUrl }}"
                                                   class="font-medium text-gray-900 hover:text-indigo-600 block max-w-xs">{{ $assignment->script_title }}</a>
                                            @else
                                                <span class="font-medium text-gray-400 block max-w-xs" title="File upload pending">{{ $assignment->script_title }}</span>
                                            @endif
                                            <div class="text-xs text-gray-500">{{ $assignment->writer_name }}</div>
                                            <div class="text-[10px] text-gray-400 tabular-nums">{{ $assignment->page_count }}p · ${{ number_format($assignment->pay_rate, 2) }}</div>
                                            <div class="mt-1.5">
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
                                                            'incoming'        => 'Pending',
                                                            'unassigned'      => 'Available',
                                                            'assigned'        => 'Assigned',
                                                            'completed'       => 'Completed',
                                                            'qc'              => 'QC',
                                                            'needs_attention' => 'Needs Attention',
                                                            'on_hold_customer' => 'On Hold – Customer',
                                                            'on_hold_sr'      => 'On Hold – SR',
                                                            'cancelled'       => 'Cancelled',
                                                        ] as $value => $label)
                                                            <option value="{{ $value }}" {{ $assignment->status === $value ? 'selected' : '' }}>
                                                                {{ $label }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    <div x-show="pendingAssign" x-cloak class="mt-1 flex items-center gap-1">
                                                        <select
                                                            @change="if ($event.target.value) { $refs.rInput.value = $event.target.value; $event.target.closest('form').submit(); }"
                                                            class="text-xs rounded border border-gray-200 bg-white py-0.5 pl-2 pr-5 cursor-pointer focus:ring-indigo-400">
                                                            <option value="">→ assign to</option>
                                                            @foreach ($assignableUsers as $aUser)
                                                                <option value="{{ $aUser->id }}">
                                                                    {{ $aUser->readerProfile?->initials ?? $aUser->editorProfile?->initials ?? strtoupper(substr($aUser->name, 0, 2)) }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                        <button type="button"
                                                                @click="pendingAssign = false; $refs.sSel.value = curStatus"
                                                                class="text-gray-400 hover:text-gray-700 text-base leading-none px-0.5"
                                                                title="Cancel">×</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </td>
                                        <td class="px-3 py-3 whitespace-nowrap text-center">
                                            @if ($assignment->assignedReader)
                                                <div class="flex flex-col items-center gap-0.5 mb-1">
                                                    <x-staff-icon :user="$assignment->assignedReader" size="sm" />
                                                    <span class="text-[9px] text-gray-400 font-mono leading-none">{{ $assignedInitials }}</span>
                                                </div>
                                            @endif
                                            @if ($assignment->accepted_at)
                                                <div class="text-[9px] text-gray-500 tabular-nums leading-none mb-0.5">{{ $assignment->accepted_at->copy()->setTimezone($appTimezone ?? 'UTC')->format('D M j, Y g:ia') }}</div>
                                            @endif
                                            <div class="text-gray-500 tabular-nums text-xs leading-none">{{ $accStr ?? '—' }}</div>
                                            @if ($accStr)
                                                <div class="text-[9px] text-gray-400 leading-none mt-0.5">ago</div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        </div>{{-- /overflow-x-auto --}}
                    </div>
                </div>
            @endif

                {{-- Archive search results — only visible when a search term is active --}}
                @if ($archivedAll->isNotEmpty())
                <div x-show="search" x-cloak class="mt-6">
                    <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2 px-1">Archive</h3>
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Order #</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title / Writer</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Type</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Reader</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Completed</th>
                                    <th class="px-3 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                @foreach ($archivedAll as $arc)
                                    @php
                                        $arcType = match($arc->assignment_type) {
                                            'script_coverage'   => 'Script Coverage',
                                            'notes_only'        => 'Notes-Only',
                                            'deep_dive'         => 'Deep-Dive',
                                            'short'             => 'Short',
                                            'budget'            => 'Budget Coverage',
                                            'book'              => 'Book',
                                            'coverage'          => 'Coverage',
                                            'development_notes' => 'Dev Notes',
                                            'formatting'        => 'Formatting',
                                            'proofreading'      => 'Proofreading',
                                            default             => $arc->assignment_type ?? '—',
                                        };
                                        if ($arc->vendor === 'wd') $arcType = 'WD ' . $arcType;
                                        $arcReader  = $arc->assignedReader?->readerProfile?->displayName() ?? $arc->assignedReader?->name ?? '—';
                                        $arcSearch  = strtolower(implode(' ', array_filter([
                                            $arc->order_number, $arc->script_title, $arc->writer_name, $arcReader,
                                        ])));
                                    @endphp
                                    <tr class="hover:bg-gray-50"
                                        x-show="$el.dataset.search.includes(search.toLowerCase())"
                                        data-search="{{ $arcSearch }}">
                                        <td class="px-3 py-2 whitespace-nowrap font-mono text-gray-700">
                                            <a href="{{ route('assignments.show', $arc) }}" class="hover:text-indigo-600">{{ $arc->order_number }}</a>
                                        </td>
                                        <td class="px-3 py-2">
                                            <div class="font-medium text-gray-900 max-w-xs">{{ $arc->script_title }}</div>
                                            <div class="text-xs text-gray-500">{{ $arc->writer_name }}</div>
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap text-gray-600 text-xs">{{ $arcType }}</td>
                                        <td class="px-3 py-2 whitespace-nowrap text-gray-600 text-xs">{{ $arcReader }}</td>
                                        <td class="px-3 py-2 whitespace-nowrap text-gray-500 text-xs">{{ $arc->completed_at?->format('M j, Y') ?? '—' }}</td>
                                        <td class="px-3 py-2 whitespace-nowrap text-right">
                                            <button type="button"
                                                    x-data="{ copied: false }"
                                                    @click="
                                                        fetch('{{ route('assignments.followup-token', $arc) }}', {
                                                            method: 'POST',
                                                            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' }
                                                        }).then(r => r.json()).then(d => {
                                                            navigator.clipboard.writeText(d.url);
                                                            copied = true;
                                                            setTimeout(() => copied = false, 2000);
                                                        })
                                                    "
                                                    :title="copied ? 'Copied!' : 'Copy followup URL'"
                                                    class="text-[10px] text-indigo-400 hover:text-indigo-600 transition">
                                                <span x-text="copied ? '✓ Copied' : 'Followup URL'"></span>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        </div>{{-- /overflow-x-auto --}}
                    </div>
                </div>
                @endif

                </div> {{-- /x-data search wrapper --}}

                {{-- ---- Followup Questions panel (unanswered / answered) ---- --}}
                @php $followupsUnanswered = ($followups ?? collect())->where('status', 'unanswered'); @endphp
                @if ($followupsUnanswered->isNotEmpty())
                <div class="mt-5 mb-5">
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2 flex items-center gap-2">
                        Followup Questions — Awaiting Reader Response
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">{{ $followupsUnanswered->count() }}</span>
                    </h3>
                    <div class="space-y-2">
                        @foreach ($followupsUnanswered as $fq)
                            @php
                                $fqAssignment = $fq->assignment;
                                $fqReader     = $fqAssignment?->assignedReader;
                                $fqInitials   = $fqReader?->readerProfile?->initials ?? ($fqReader ? strtoupper(substr($fqReader->name, 0, 2)) : '??');
                                $fqDeadline   = $fq->deadlineAt();
                                $fqStatus     = $fq->status;
                                $fqRowColor   = $fqStatus === 'answered' ? 'border-green-200 bg-green-50' : 'border-amber-200 bg-amber-50';
                            @endphp
                            <div x-data="{ open: false }" class="border rounded-lg {{ $fqRowColor }}">
                                <div @click="open = !open" class="flex items-center gap-3 flex-wrap px-4 py-3 cursor-pointer">
                                    <span class="text-xs font-mono text-gray-500">{{ $fqAssignment?->order_number }}</span>
                                    <span class="text-sm font-medium text-gray-800">{{ $fqAssignment?->script_title }}</span>
                                    <span class="text-xs text-gray-500">· Reader {{ $fqInitials }}</span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                        {{ $fqStatus === 'answered' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                                        {{ ucfirst($fqStatus) }}
                                    </span>
                                    @if ($fqDeadline && $fqStatus === 'unanswered')
                                        <div x-data="followupCountdown('{{ $fqDeadline->utc()->toIso8601String() }}', @js($fqDeadline->setTimezone($appTimezone)->format('M j, g:ia')))"
                                             x-text="display" :class="overdue ? 'rush-overdue' : 'text-amber-600'" class="text-xs" @click.stop></div>
                                    @endif
                                    <button type="button" @click.stop="open = !open"
                                            class="ml-auto text-xs text-indigo-500 hover:text-indigo-700 underline">
                                        <span x-text="open ? 'Hide' : 'Edit / Review'"></span>
                                    </button>
                                </div>

                                <div x-show="open" x-cloak class="mt-3 space-y-3 border-t border-gray-200 pt-3 px-4 pb-3">
                                    <form method="POST" action="{{ route('followups.update', $fq) }}" class="space-y-3">
                                        @csrf @method('PATCH')

                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1">Customer's questions</label>
                                            <div class="text-xs text-gray-500 italic bg-white border border-gray-200 rounded px-2 py-1.5">{{ $fq->customer_questions ?? '—' }}</div>
                                        </div>

                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1">Edited questions shown to reader</label>
                                            <textarea name="edited_questions" rows="3"
                                                      class="w-full text-sm border border-gray-300 rounded px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-indigo-400">{{ old('edited_questions', $fq->edited_questions ?? $fq->customer_questions) }}</textarea>
                                        </div>

                                        @if ($fqStatus === 'answered')
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1">Reader's response</label>
                                            <div class="text-xs text-gray-500 italic bg-white border border-gray-200 rounded px-2 py-1.5">{{ $fq->reader_response ?? '—' }}</div>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1">Edited response sent to customer</label>
                                            <textarea name="edited_response" rows="4"
                                                      class="w-full text-sm border border-gray-300 rounded px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-indigo-400">{{ old('edited_response', $fq->edited_response ?? $fq->reader_response) }}</textarea>
                                        </div>
                                        @endif

                                        <div class="flex items-center gap-2 flex-wrap">
                                            <select name="status" class="text-xs border border-gray-300 rounded px-2 py-1">
                                                @foreach (['pending' => 'Pending', 'unanswered' => 'Send to reader', 'answered' => 'Answered'] as $val => $label)
                                                    <option value="{{ $val }}" {{ $fq->status === $val ? 'selected' : '' }}>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="px-3 py-1 text-xs bg-indigo-600 text-white rounded hover:bg-indigo-700">Save</button>
                                        </div>
                                    </form>

                                    @if ($fqStatus === 'answered')
                                    @php
                                        $fqHsId = $fqAssignment?->helpscout_ticket_number
                                            ?: $fqAssignment?->helpscoutConversation?->helpscout_conversation_id;
                                    @endphp
                                    <form method="POST" action="{{ route('followups.complete', $fq) }}">
                                        @csrf
                                        <button type="submit"
                                                class="px-3 py-1 text-xs bg-green-600 text-white rounded hover:bg-green-700"
                                                @if ($fqHsId) onclick="window.open('https://secure.helpscout.net/conversation/{{ $fqHsId }}/', '_blank')" @endif>
                                            Mark Complete &amp; Create HelpScout Draft
                                        </button>
                                    </form>
                                    @endif

                                    <form method="POST" action="{{ route('followups.destroy', $fq) }}"
                                          onsubmit="return confirm('Delete this followup question?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="px-3 py-1 text-xs bg-red-100 text-red-700 border border-red-300 rounded hover:bg-red-200">Delete</button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                @endif

            {{-- ===== MY ASSIGNMENTS (admin/editor writing coverage) ===== --}}
            @if ($myAssignments->isNotEmpty())
                @php
                    $meUser      = auth()->user();
                    $meProfile   = $meUser->editorProfile;
                    $meInitials  = $meProfile?->initials ?? strtoupper(substr($meUser->name, 0, 2));
                    $mePhotoRaw  = $meProfile?->photo;
                    $mePhotoUrl  = $mePhotoRaw ? asset('storage/' . $mePhotoRaw) : null;
                    $meAvatarBg  = 'bg-indigo-100 text-indigo-700';
                    $meLabelClr  = 'text-indigo-400';
                @endphp
                <div class="mt-8">
                    <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2 px-1">
                        My Assignments
                        <span class="ml-1.5 inline-flex items-center justify-center w-5 h-5 rounded-full bg-indigo-100 text-indigo-700 text-xs font-bold normal-case">{{ $myAssignments->count() }}</span>
                    </h3>
                    <div class="bg-white rounded-lg shadow-sm border border-indigo-200 overflow-hidden" x-data>
                        <div class="overflow-x-auto">
                        <table class="w-full min-w-[640px] divide-y divide-gray-200 text-sm">
                            <thead class="bg-indigo-50">
                                <tr>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-indigo-500 uppercase tracking-wider whitespace-nowrap w-52">Order Details</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-indigo-500 uppercase tracking-wider">Assignment</th>
                                    <th class="px-3 py-3 text-center text-xs font-medium text-indigo-500 uppercase tracking-wider whitespace-nowrap w-44">Accepted by</th>
                                    <th class="px-3 py-3 w-36"></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                @foreach ($myAssignments as $assignment)
                                    @php
                                        $diff     = $assignment->created_at ? now()->diff($assignment->created_at) : null;
                                        $ageStr   = $diff
                                            ? ($diff->days >= 1
                                                ? ($diff->days . 'd ' . $diff->h . 'h')
                                                : ($diff->h >= 1 ? ($diff->h . 'h ' . $diff->i . 'm') : (max(0, $diff->i) . 'm')))
                                            : '—';
                                        $ageTitle = $assignment->created_at?->copy()->setTimezone($appTimezone ?? 'UTC')->format('D M j, Y g:ia T') ?? '—';
                                        $ageHours = $diff ? ($diff->days * 24 + $diff->h) : 0;
                                        $ageT     = $ageThresholds[$assignment->assignment_type] ?? ['yellow' => 96, 'orange' => 192, 'red' => 336];
                                        $ageColor = match(true) {
                                            $ageHours >= $ageT['red']    => 'text-red-600 font-semibold',
                                            $ageHours >= $ageT['orange'] => 'text-orange-500 font-semibold',
                                            $ageHours >= $ageT['yellow'] => 'text-yellow-600',
                                            default                     => 'text-green-600',
                                        };
                                        $statusColor = match($assignment->status) {
                                            'assigned'        => 'bg-green-100 text-green-800',
                                            'qc'              => 'bg-blue-100 text-blue-800',
                                            'needs_attention' => 'bg-orange-100 text-orange-800',
                                            default           => 'bg-gray-100 text-gray-700',
                                        };
                                        $statusLabel = match($assignment->status) {
                                            'assigned'        => 'Assigned to you',
                                            'qc'              => 'QC',
                                            'needs_attention' => 'Needs Attention',
                                            default           => ucfirst($assignment->status),
                                        };
                                        $typeLabel = match($assignment->assignment_type) {
                                            'script_coverage'   => 'Script Coverage',
                                            'notes_only'        => 'Notes-Only',
                                            'deep_dive'         => 'Deep-Dive',
                                            'short'             => 'Short',
                                            'budget'            => 'Budget Coverage',
                                            'book'              => 'Book',
                                            'coverage'          => 'Coverage',
                                            'development_notes' => 'Dev Notes',
                                            default             => $assignment->assignment_type ?? '—',
                                        };
                                        if ($assignment->vendor === 'wd') $typeLabel = 'WD ' . $typeLabel;
                                        $rowClass = $assignment->status === 'needs_attention'
                                            ? 'border-l-4 border-orange-400'
                                            : ($assignment->rush ? 'border-l-4 border-amber-400' : '');
                                        $accDiff  = $assignment->accepted_at ? now()->diff($assignment->accepted_at) : null;
                                        $accStr   = $accDiff
                                            ? ($accDiff->days >= 1
                                                ? ($accDiff->days . 'd ' . $accDiff->h . 'h')
                                                : ($accDiff->h >= 1 ? ($accDiff->h . 'h ' . $accDiff->i . 'm') : (max(0, $accDiff->i) . 'm')))
                                            : null;
                                        $accTitle = $assignment->accepted_at?->format('D M j, Y g:ia') ?? null;
                                        $viewUrl = $assignment->hasCloudScript()
                                            ? route('assignments.streamScript', $assignment)
                                            : null;
                                        $hsId = $assignment->helpscout_ticket_number ?: $assignment->helpscoutConversation?->helpscout_conversation_id;
                                        $wooOrderUrl = ($assignment->vendor === 'sr' && is_numeric($assignment->order_number))
                                            ? route('woo-orders.show', $assignment->order_number)
                                            : null;
                                    @endphp
                                    <tr class="hover:bg-gray-50 {{ $rowClass }} cursor-pointer"
                                        @click="if (!$event.target.closest('a, button, select, textarea, input, form')) window.location = @js(route('assignments.edit', $assignment))">
                                        <td class="px-3 py-3 whitespace-nowrap">
                                            @if ($wooOrderUrl)
                                                <a href="{{ $wooOrderUrl }}" class="font-mono text-gray-700 hover:text-indigo-600 hover:underline">{{ $assignment->order_number }}</a>
                                            @else
                                                <span class="font-mono text-gray-700">{{ $assignment->order_number }}</span>
                                            @endif
                                            <div class="mt-1 text-xs tabular-nums {{ $ageColor }}" title="{{ $ageTitle }}">
                                                {{ $ageStr }}
                                                @if ($assignment->rush)
                                                    <div x-data="rushCountdown('{{ $assignment->created_at->copy()->addHours(23)->utc()->toIso8601String() }}', @js($assignment->created_at->copy()->addHours(23)->setTimezone($appTimezone ?? 'UTC')->format('M j, g:ia')))"><div class="mt-0.5"><span class="inline-flex px-1 py-px rounded text-[9px] font-bold bg-amber-400 text-amber-900 uppercase leading-none">Rush</span> <span class="rush-due text-[9px]" x-text="(overdue ? 'Was due by ' : 'Due by ') + dueLabel"></span></div><div x-text="display" :class="overdue ? 'rush-overdue' : 'rush-countdown'" class="text-[9px]"></div></div>
                                                @endif
                                            </div>
                                            @can('update', $assignment)
                                                <div class="mt-0.5">
                                                    <a href="{{ route('assignments.edit', $assignment) }}"
                                                       class="text-xs text-indigo-500 hover:text-indigo-700 hover:underline">Edit</a>
                                                </div>
                                            @endcan
                                            @if ($hsId)
                                                <div class="mt-0.5">
                                                    <a href="https://secure.helpscout.net/conversation/{{ $hsId }}/"
                                                       target="_blank" rel="noopener noreferrer"
                                                       title="HelpScout ticket"
                                                       class="inline-flex text-gray-400 hover:text-indigo-600">
                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3.5 h-3.5">
                                                            <path fill-rule="evenodd" d="M2 5a2 2 0 012-2h12a2 2 0 012 2v7a2 2 0 01-2 2H6l-4 4V5z" clip-rule="evenodd"/>
                                                        </svg>
                                                    </a>
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-3 py-3" x-data="pdfViewer(@js($viewUrl ?? ''))">
                                            <div class="text-[10px] text-gray-400 uppercase tracking-wide mb-0.5">{{ $typeLabel }}</div>
                                            @if ($viewUrl)
                                                <button @click="openViewer()" type="button"
                                                        class="font-medium text-gray-900 hover:text-indigo-600 text-left leading-snug max-w-xs block">{{ $assignment->script_title }}</button>
                                                <div x-show="open" x-cloak x-ref="modal"
                                                     @keydown.escape.window="open = false"
                                                     tabindex="-1"
                                                     class="fixed inset-0 z-50 flex flex-col bg-black/80">
                                                    <div class="flex items-center justify-between px-4 py-2 bg-gray-900 shrink-0 gap-2">
                                                        <span class="text-sm text-gray-200 font-medium truncate min-w-0">{{ $assignment->drive_script_filename ?? $assignment->script_title }}</span>
                                                        <div class="flex items-center gap-2 shrink-0">
                                                            @if (\App\Support\Permission::check('script.download'))
                                                                <a href="{{ route('assignments.downloadScript', $assignment) }}"
                                                                   class="px-2 py-1 bg-gray-700 hover:bg-gray-600 rounded text-xs text-white whitespace-nowrap">Download</a>
                                                            @endif
                                                            @if (\App\Support\Permission::check('script.print'))
                                                                <a href="{{ route('assignments.streamScript', $assignment) }}" target="_blank" rel="noopener"
                                                                   class="px-2 py-1 bg-gray-700 hover:bg-gray-600 rounded text-xs text-white whitespace-nowrap">Print</a>
                                                            @endif
                                                            @if ($assignment->hasCloudScript() && $meUser->isReader() && $assignment->assigned_reader_id === $meUser->id())
                                                                <span x-data="{ busy: false, err: '' }">
                                                                    <button type="button" :disabled="busy"
                                                                            @click="
                                                                                busy = true; err = '';
                                                                                fetch(@js(route('script-downloads.store', $assignment)), {
                                                                                    method: 'POST',
                                                                                    headers: {
                                                                                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                                                                        'Accept': 'application/json',
                                                                                    }
                                                                                })
                                                                                .then(r => r.json())
                                                                                .then(d => {
                                                                                    busy = false;
                                                                                    if (d.url) { window.location = d.url; }
                                                                                    else { err = d.message || 'Failed'; }
                                                                                })
                                                                                .catch(() => { busy = false; err = 'Failed'; })
                                                                            "
                                                                            class="px-2 py-1 bg-gray-700 hover:bg-gray-600 disabled:opacity-50 rounded text-xs text-white whitespace-nowrap">
                                                                        <span x-text="busy ? 'Preparing…' : 'Download'"></span>
                                                                    </button>
                                                                    <span x-show="err" x-text="err" class="text-[10px] text-red-400 ml-1"></span>
                                                                </span>
                                                            @endif
                                                            <button @click="open = false" type="button"
                                                                    class="text-gray-400 hover:text-white text-2xl leading-none px-1">×</button>
                                                        </div>
                                                    </div>
                                                    <div class="flex items-center justify-center gap-3 px-4 py-1.5 bg-gray-800 shrink-0 border-t border-gray-700">
                                                        <span x-show="loading" x-text="totalPages > 0 ? 'Rendering ' + currentPage + ' of ' + totalPages + '…' : 'Loading…'" class="text-xs text-gray-400"></span>
                                                        <span x-show="!loading && totalPages > 0" class="flex items-center gap-1.5 text-xs text-gray-400">
                                                            Go to page
                                                            <input type="number" min="1" :max="totalPages"
                                                                   @change="scrollToPage($event.target.value)"
                                                                   @keydown.enter.prevent="scrollToPage($event.target.value)"
                                                                   class="w-14 text-center bg-gray-700 border border-gray-600 rounded text-xs text-gray-200 px-1 py-0.5" />
                                                            / <span x-text="totalPages"></span>
                                                        </span>
                                                    </div>
                                                    <div x-ref="canvasWrap" class="flex-1 overflow-auto flex flex-col items-center gap-4 bg-gray-800 py-6 px-4">
                                                        <div x-show="loading && totalPages === 0" class="text-gray-400 text-sm mt-8">Loading…</div>
                                                    </div>
                                                </div>
                                            @else
                                                <div class="font-medium text-gray-900 max-w-xs">{{ $assignment->script_title }}</div>
                                            @endif
                                            <div class="text-xs text-gray-500">{{ $assignment->writer_name }}</div>
                                            <div class="text-[10px] text-gray-400 tabular-nums">{{ $assignment->page_count }}p · ${{ $meUser->isAdmin() ? '0.00' : number_format($assignment->pay_rate, 2) }}</div>
                                            <div class="mt-1.5">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColor }}">{{ $statusLabel }}</span>
                                            </div>
                                        </td>
                                        <td class="px-3 py-3 whitespace-nowrap text-center" title="{{ $accStr ? 'Accepted ' . $accTitle : '' }}">
                                            <div class="flex flex-col items-center gap-0.5 mb-1">
                                                <span class="relative inline-flex items-center justify-center w-7 h-7 rounded-full {{ $meAvatarBg }} text-xs font-mono font-semibold">
                                                    @if ($mePhotoUrl)
                                                        <span class="absolute inset-0 rounded-full overflow-hidden">
                                                            <img src="{{ $mePhotoUrl }}" alt="{{ $meInitials }}" class="w-full h-full object-cover" />
                                                        </span>
                                                    @else
                                                        {{ $meInitials }}
                                                    @endif
                                                </span>
                                                <span class="text-[9px] {{ $meLabelClr }} font-mono leading-none">{{ $meInitials }}</span>
                                            </div>
                                            @if ($assignment->accepted_at)
                                                <div class="text-[9px] text-gray-500 tabular-nums leading-none mb-0.5">{{ $assignment->accepted_at->copy()->setTimezone($appTimezone ?? 'UTC')->format('D M j, Y g:ia') }}</div>
                                            @endif
                                            <div class="text-gray-500 tabular-nums text-xs leading-none">{{ $accStr ?? '—' }}</div>
                                            @if ($accStr)
                                                <div class="text-[9px] text-gray-400 leading-none mt-0.5">ago</div>
                                            @endif
                                        </td>
                                        <td class="px-3 py-3 whitespace-nowrap text-right">
                                            @can('submitCoverage', $assignment)
                                                <a href="{{ route('coverage.show', $assignment) }}"
                                                   class="inline-flex items-center px-2.5 py-1 bg-indigo-600 border border-transparent rounded text-xs font-semibold text-white hover:bg-indigo-500 transition whitespace-nowrap">
                                                    {{ $assignment->coverageSubmission ? 'Continue Coverage' : 'Write Coverage' }}
                                                </a>
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- ===== READER VIEW ===== --}}
            @else
                @php $needsAttentionCount = $mine->where('status', 'needs_attention')->count(); @endphp
                @if($needsAttentionCount > 0)
                    <div class="mb-4 flex items-center gap-3 px-4 py-3 bg-orange-50 border border-orange-300 rounded-lg text-sm text-orange-800">
                        <svg class="w-5 h-5 text-orange-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <span><strong>You have {{ $needsAttentionCount === 1 ? 'an assignment' : $needsAttentionCount . ' assignments' }} that need{{ $needsAttentionCount === 1 ? 's' : '' }} attention.</strong> Check the Needs Attention tab below.</span>
                    </div>
                @endif

                @if($readerMax > 0)
                    <div class="mb-4 text-sm text-gray-500">
                        <span class="font-medium text-gray-700">Current Maximum Assignments:</span> {{ $readerMax }}
                        <span class="text-xs text-gray-400 ml-1">Rush orders and Reader Requests do not count toward this maximum.</span>
                    </div>
                @endif

                {{-- Staff panel — all non-hidden editors/admins and readers; green dot for online; click for bio card --}}
                @if ($staffEditors->isNotEmpty() || $staffReaders->isNotEmpty())
                <div class="mb-5 flex items-center gap-2 flex-wrap">

                    {{-- Editors / Admins --}}
                    @foreach ($staffEditors as $editor)
                        @php
                            $eProfile   = $editor->editorProfile;
                            $eInitials  = $eProfile?->initials ?? strtoupper(substr($editor->name, 0, 2));
                            $ePhotoUrl  = $eProfile?->photo ? asset('storage/' . $eProfile->photo) : null;
                            $eOnline    = $editor->isOnline();
                            $eCardUrl   = route('staff.reader-card', $editor);
                            $eHasLogline = !empty($eProfile?->custom_message);
                        @endphp
                        <div class="flex flex-col items-center gap-0.5">
                            <button type="button"
                                    @if ($eHasLogline) onclick="srStaffCard.toggle(event, {{ $editor->id }}, '{{ addslashes($eCardUrl) }}', this)" @endif
                                    title="{{ $editor->lastOnlineText() }}"
                                    class="relative inline-flex items-center justify-center w-9 h-9 rounded-full text-xs font-mono font-semibold bg-indigo-100 text-indigo-700 {{ $eHasLogline ? 'cursor-pointer hover:bg-indigo-200' : 'cursor-default' }} focus:outline-none transition-colors">
                                @if ($ePhotoUrl)
                                    <span class="absolute inset-0 rounded-full overflow-hidden">
                                        <img src="{{ $ePhotoUrl }}" alt="{{ $eInitials }}" class="w-full h-full object-cover" />
                                    </span>
                                @else
                                    {{ $eInitials }}
                                @endif
                                @if ($eOnline)
                                    <span class="absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full bg-green-400 ring-2 ring-white z-20"></span>
                                @endif
                            </button>
                            <span class="text-[9px] text-indigo-400 font-mono leading-none">{{ $eInitials }}</span>
                        </div>
                    @endforeach

                    {{-- Divider between editors and readers --}}
                    @if ($staffEditors->isNotEmpty() && $staffReaders->isNotEmpty())
                        <div class="w-px h-8 bg-gray-200 mx-1 self-center"></div>
                    @endif

                    {{-- Readers (all non-hidden; click opens bio card) --}}
                    @foreach ($staffReaders as $reader)
                        @php
                            $rProfile     = $reader->readerProfile;
                            $rInitials    = $rProfile?->initials ?? strtoupper(substr($reader->name, 0, 2));
                            $rActive      = $reader->assignments->count();
                            $rMax         = $rProfile?->max_concurrent_assignments ?? 0;
                            $rFull        = $rMax > 0 && $rActive >= $rMax;
                            $rPhotoUrl    = $rProfile?->photo ? asset('storage/' . $rProfile->photo) : null;
                            $rUnavailable = $rProfile?->availability === 'unavailable';
                            $rOnline      = $reader->isOnline();
                            $rIsSelf      = $reader->id === auth()->id();
                            $rCardUrl     = route('staff.reader-card', $reader);
                            $rHasLogline  = !empty($rProfile?->custom_message);
                        @endphp
                        <div class="flex flex-col items-center gap-0.5">
                            <button type="button"
                                    @if ($rHasLogline) onclick="srStaffCard.toggle(event, {{ $reader->id }}, '{{ addslashes($rCardUrl) }}', this)" @endif
                                    title="{{ $reader->lastOnlineText() }}"
                                    class="relative inline-flex items-center justify-center w-9 h-9 rounded-full text-xs font-mono font-semibold focus-visible:outline-none transition-colors
                                        {{ $rHasLogline ? ($rFull ? 'cursor-pointer hover:bg-amber-300' : 'cursor-pointer hover:bg-gray-300') : 'cursor-default' }}
                                        {{ $rFull ? 'bg-amber-200 text-amber-800' : 'bg-gray-200 text-gray-700' }}
                                        {{ $rUnavailable ? 'outline outline-2 outline-dashed outline-red-400 outline-offset-1' : '' }}">
                                @if ($rPhotoUrl)
                                    <span class="absolute inset-0 rounded-full overflow-hidden">
                                        <img src="{{ $rPhotoUrl }}" alt="{{ $rInitials }}" class="w-full h-full object-cover" />
                                    </span>
                                @else
                                    {{ $rInitials }}
                                @endif
                                @if ($rActive > 0 && $rIsSelf)
                                    <span class="absolute -top-0.5 -right-0.5 w-4 h-4 rounded-full text-[9px] leading-none flex items-center justify-center font-bold z-10
                                        {{ $rFull ? 'bg-amber-500 text-white' : 'bg-green-500 text-white' }}">
                                        {{ $rActive }}
                                    </span>
                                @endif
                                @if ($rOnline)
                                    <span class="absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full bg-green-400 ring-2 ring-white z-20"></span>
                                @endif
                            </button>
                            <span class="text-[9px] text-gray-400 font-mono leading-none">{{ $rInitials }}</span>
                        </div>
                    @endforeach

                </div>
                @endif

                <div x-data="{ tab: (location.hash.startsWith('#tab-') ? location.hash.slice(5) : 'mine') }"
                     x-init="
                         $watch('tab', val => history.replaceState(null, '', '#tab-' + val));
                         setInterval(() => {
                             if (tab === 'all' && !document.querySelector('.fixed.inset-0.z-50:not([style*=\'display: none\'])')) location.reload();
                         }, 300000)">

                    {{-- Tabs --}}
                    <div class="flex border-b border-gray-200 mb-4">
                        <button @click="tab = 'mine'"
                                :class="tab === 'mine' ? 'border-b-2 border-indigo-600 text-indigo-700 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                                class="px-4 py-2 text-sm transition flex items-center gap-1.5">
                            My Assignments
                            @php
                                $mineActiveCount = $mine->filter(fn($a) => in_array($a->status, ['assigned', 'qc']))->count();
                                $mineCompletedCount = $mine->filter(fn($a) =>
                                    $a->status === 'completed' &&
                                    $a->reader_paid_at === null &&
                                    ($a->completed_at === null ||
                                        ($a->completed_at->gte($periodStart) && $a->completed_at->lte($periodEnd)))
                                )->count();
                            @endphp
                            @if($mineActiveCount > 0)
                                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-indigo-100 text-indigo-700 text-xs font-bold">{{ $mineActiveCount }}</span>
                            @endif
                        </button>
                        <button @click="tab = 'all'"
                                :class="tab === 'all' ? 'border-b-2 border-indigo-600 text-indigo-700 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                                class="px-4 py-2 text-sm transition">
                            Available Assignments
                        </button>
                        <button @click="tab = 'week'"
                                :class="tab === 'week' ? 'border-b-2 border-green-600 text-green-700 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                                class="px-4 py-2 text-sm transition flex items-center gap-1.5">
                            Completed This Week
                            @if($mineCompletedCount > 0)
                                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-green-100 text-green-700 text-xs font-bold">{{ $mineCompletedCount }}</span>
                            @endif
                        </button>
                        <button @click="tab = 'archived'"
                                :class="tab === 'archived' ? 'border-b-2 border-indigo-600 text-indigo-700 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                                class="px-4 py-2 text-sm transition">
                            Archived
                        </button>
                        @if($needsAttentionCount > 0)
                            <button @click="tab = 'attention'"
                                    :class="tab === 'attention' ? 'border-b-2 border-orange-600 text-orange-700 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                                    class="px-4 py-2 text-sm transition flex items-center gap-1.5">
                                Needs Attention
                                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-orange-100 text-orange-700 text-xs font-bold">{{ $needsAttentionCount }}</span>
                            </button>
                        @endif
                    </div>

                    {{-- ---- Available Assignments tab ---- --}}
                    <div x-show="tab === 'all'">

                        {{-- Replies to this reader's notes --}}
                        @foreach (($myNoteReplies ?? collect()) as $nrItem)
                            @php
                                $nrNote       = $nrItem['note'];
                                $nrAssignment = $nrNote->assignment;
                            @endphp
                            @foreach ($nrItem['replies'] as $nrReply)
                            <div class="mb-3 rounded-lg border-2 border-blue-300 bg-blue-50"
                                 id="nreply-{{ $nrReply->id }}">
                                <div class="flex items-center gap-3 px-4 py-3 flex-wrap">
                                    <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold bg-blue-400 text-blue-900 uppercase leading-none">Editor Reply</span>
                                    <span class="text-sm font-medium text-gray-800">{{ $nrAssignment?->script_title ?? '—' }}</span>
                                    <span class="text-xs text-gray-400">{{ $nrReply->created_at->setTimezone($appTimezone)->format('M j, g:ia') }}</span>
                                    <button type="button"
                                            x-data
                                            @click="fetch('{{ route('assignment-note-replies.dismiss', $nrReply) }}', {
                                                method: 'POST',
                                                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' }
                                            }).then(() => document.getElementById('nreply-{{ $nrReply->id }}').remove())"
                                            class="ml-auto text-blue-300 hover:text-blue-600 text-sm leading-none transition"
                                            title="Dismiss">✕</button>
                                </div>
                                <div class="border-t border-blue-200 px-4 py-3 space-y-2">
                                    <div>
                                        <p class="text-[10px] text-gray-400 mb-0.5">Your note:</p>
                                        <div class="text-xs text-gray-600 italic whitespace-pre-wrap bg-white border border-blue-100 rounded px-2 py-1.5">{{ $nrNote->body }}</div>
                                    </div>
                                    <div>
                                        <p class="text-[10px] text-gray-400 mb-0.5">Reply from team:</p>
                                        <div class="text-sm text-gray-800 whitespace-pre-wrap bg-white border border-blue-200 rounded px-2 py-1.5">{{ $nrReply->body }}</div>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        @endforeach

                        {{-- Followup questions waiting for this reader --}}
                        @foreach (($myFollowups ?? collect())->where('status', 'unanswered') as $fq)
                            @php
                                $fqA        = $fq->assignment;
                                $fqDeadline = $fq->deadlineAt();
                            @endphp
                            @include('partials.reader-followup-row', ['fq' => $fq, 'fqA' => $fqA, 'fqDeadline' => $fqDeadline, 'appTimezone' => $appTimezone])
                        @endforeach

                        @if($available->isEmpty())
                            <div class="bg-white rounded-lg border border-gray-200 p-12 text-center text-gray-400 text-sm">
                                No assignments available right now.
                            </div>
                        @else
                            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden" x-data="tableSort('date', 'asc')">
                                <div class="flex flex-wrap items-center gap-1.5 px-4 py-2 bg-gray-50 border-b border-gray-200">
                                    <span class="text-[10px] font-medium text-gray-500 uppercase tracking-wide mr-1">Sort:</span>
                                    @foreach (['date' => 'Date', 'age' => 'Age', 'rush' => 'Rush', 'type' => 'Type', 'rate' => 'Rate', 'status' => 'Status'] as $sf => $sl)
                                        <button type="button" @click="setSort('{{ $sf }}')"
                                                :class="sortBy === '{{ $sf }}' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-500 border-gray-300 hover:bg-gray-100'"
                                                class="inline-flex items-center px-2 py-0.5 rounded border text-[11px] transition-colors whitespace-nowrap">
                                            {{ $sl }}<span x-show="sortBy === '{{ $sf }}'" x-text="sortDir === 'asc' ? ' ↑' : ' ↓'" class="ml-0.5"></span>
                                        </button>
                                    @endforeach
                                </div>
                                <div class="overflow-x-auto">
                                <table class="w-full min-w-[520px] divide-y divide-gray-200 text-sm">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Order Details</th>
                                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assignment</th>
                                            <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Accepted by</th>
                                            <th class="px-3 py-3"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-100" x-ref="sortTbody">

                                        @foreach($available as $assignment)
                                            @php
                                                $diff     = $assignment->created_at ? now()->diff($assignment->created_at) : null;
                                                $ageStr   = $diff
                                                    ? ($diff->days >= 1
                                                        ? ($diff->days . 'd ' . $diff->h . 'h')
                                                        : ($diff->h >= 1 ? ($diff->h . 'h ' . $diff->i . 'm') : (max(0, $diff->i) . 'm')))
                                                    : '—';
                                                $ageTitle = $assignment->created_at?->copy()->setTimezone($appTimezone ?? 'UTC')->format('D M j, Y g:ia T') ?? '—';
                                                $ageHours = $diff ? ($diff->days * 24 + $diff->h) : 0;
                                                $ageT     = $ageThresholds[$assignment->assignment_type] ?? ['yellow' => 96, 'orange' => 192, 'red' => 336];
                                                $ageColor = match(true) {
                                                    $ageHours >= $ageT['red']    => 'text-red-600 font-semibold',
                                                    $ageHours >= $ageT['orange'] => 'text-orange-500 font-semibold',
                                                    $ageHours >= $ageT['yellow'] => 'text-yellow-600',
                                                    default                     => 'text-green-600',
                                                };
                                                $accDiff  = $assignment->accepted_at ? now()->diff($assignment->accepted_at) : null;
                                                $accStr   = $accDiff
                                                    ? ($accDiff->days >= 1
                                                        ? ($accDiff->days . 'd ' . $accDiff->h . 'h')
                                                        : ($accDiff->h >= 1 ? ($accDiff->h . 'h ' . $accDiff->i . 'm') : (max(0, $accDiff->i) . 'm')))
                                                    : null;
                                                $accTitle = $assignment->accepted_at?->format('D M j, Y g:ia') ?? null;
                                                $reqInitials  = $assignment->requestedReader?->readerProfile?->initials;
                                                $reqPhotoUrl  = $assignment->requestedReader?->readerProfile?->photo
                                                    ? asset('storage/' . $assignment->requestedReader->readerProfile->photo)
                                                    : null;
                                                $isRequestedForMe = $assignment->requested_reader_id === auth()->id();
                                                $rowClass = $isRequestedForMe
                                                    ? 'border-l-4 request-pulse'
                                                    : ($assignment->rush ? 'border-l-4 border-amber-400' : '');
                                                $viewUrl  = $assignment->hasCloudScript()
                                                    ? route('assignments.streamScript', $assignment)
                                                    : null;
                                                $typeLabel = match($assignment->assignment_type) {
                                                    'script_coverage'   => 'Script Coverage',
                                                    'notes_only'        => 'Notes-Only',
                                                    'deep_dive'         => 'Deep-Dive',
                                                    'short'             => 'Short',
                                                    'budget'            => 'Budget Coverage',
                                                    'book'              => 'Book',
                                                    'coverage'          => 'Coverage',
                                                    'development_notes' => 'Dev Notes',
                                                    default             => $assignment->assignment_type ?? '—',
                                                };
                                                if ($assignment->vendor === 'wd') {
                                                    $typeLabel = 'WD ' . $typeLabel;
                                                }
                                            @endphp
                                            <tr class="hover:bg-gray-50 {{ $rowClass }}"
                                                data-sort-date="{{ $assignment->created_at?->timestamp ?? 0 }}"
                                                data-sort-rush="{{ $assignment->rush ? 1 : 0 }}"
                                                data-sort-type="{{ $assignment->assignment_type ?? '' }}"
                                                data-sort-rate="{{ $assignment->pay_rate ?? 0 }}"
                                                data-sort-status="{{ $assignment->status ?? '' }}">
                                                <td class="px-3 py-3 whitespace-nowrap">
                                                    <span class="font-mono text-gray-700">{{ $assignment->order_number }}</span>
                                                    <div class="mt-1 text-[10px] text-gray-400 tabular-nums">{{ $assignment->created_at?->copy()->setTimezone($appTimezone ?? 'UTC')->format('D M j, Y g:ia') }}</div>
                                                    <div class="text-xs tabular-nums {{ $ageColor }}" title="{{ $ageTitle }}">
                                                        Age: {{ $ageStr }}
                                                        @if ($assignment->rush)
                                                            <div x-data="rushCountdown('{{ $assignment->created_at->copy()->addHours(23)->utc()->toIso8601String() }}', @js($assignment->created_at->copy()->addHours(23)->setTimezone($appTimezone ?? 'UTC')->format('M j, g:ia')))"><div class="mt-0.5"><span class="inline-flex px-1 py-px rounded text-[9px] font-bold bg-amber-400 text-amber-900 uppercase leading-none">Rush</span> <span class="rush-due text-[9px]" x-text="(overdue ? 'Was due by ' : 'Due by ') + dueLabel"></span></div><div x-text="display" :class="overdue ? 'rush-overdue' : 'rush-countdown'" class="text-[9px]"></div></div>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td class="px-3 py-3" x-data="pdfViewer(@js($viewUrl))">
                                                    <div class="text-[10px] text-gray-400 uppercase tracking-wide mb-0.5">{{ $typeLabel }}</div>
                                                    @if($viewUrl)
                                                        <button @click="openViewer()" type="button"
                                                                class="font-medium text-gray-900 hover:text-indigo-600 text-left leading-snug max-w-xs block">{{ $assignment->script_title }}</button>
                                                        <div x-show="open" x-cloak
                                                             @keydown.escape.window="open = false"
                                                             x-ref="modal"
                                                             tabindex="-1"
                                                             class="fixed inset-0 z-50 flex flex-col bg-black/80">
                                                            <div class="flex items-center justify-between px-4 py-2 bg-gray-900 shrink-0 gap-4">
                                                                <span class="text-sm text-gray-200 font-medium truncate min-w-0">{{ $assignment->drive_script_filename ?? $assignment->script_title }}</span>
                                                                <div class="flex items-center gap-3 shrink-0">
                                                                    <div class="flex items-center gap-2 text-xs text-gray-400">
                                                                        <span x-show="loading" x-text="totalPages > 0 ? 'Rendering ' + currentPage + ' of ' + totalPages + '…' : 'Loading…'"></span>
                                                                        <span x-show="!loading && totalPages > 0" class="flex items-center gap-1.5">
                                                                            Go to page
                                                                            <input type="number" min="1" :max="totalPages"
                                                                                   @change="scrollToPage($event.target.value)"
                                                                                   @keydown.enter.prevent="scrollToPage($event.target.value)"
                                                                                   class="w-14 text-center bg-gray-700 border border-gray-600 rounded text-xs text-gray-200 px-1 py-0.5" />
                                                                            / <span x-text="totalPages"></span>
                                                                        </span>
                                                                    </div>
                                                                    <button @click="open = false" type="button"
                                                                            class="text-gray-400 hover:text-white text-2xl leading-none px-1">×</button>
                                                                </div>
                                                            </div>
                                                            <div x-ref="canvasWrap" class="flex-1 overflow-auto flex flex-col items-center gap-4 bg-gray-800 py-6 px-4">
                                                                <div x-show="loading && totalPages === 0" class="text-gray-400 text-sm mt-8">Loading…</div>
                                                            </div>
                                                        </div>
                                                    @else
                                                        <div class="font-medium text-gray-900 max-w-xs">{{ $assignment->script_title }}</div>
                                                    @endif
                                                    <div class="text-xs text-gray-500">{{ $assignment->writer_name }}</div>
                                                    <div class="text-[10px] text-gray-400 tabular-nums">{{ $assignment->page_count }}p · ${{ number_format($assignment->pay_rate, 2) }}</div>
                                                    @if ($isRequestedForMe)
                                                        <div class="flex items-center gap-1 mt-1">
                                                            <span class="relative inline-flex items-center justify-center w-5 h-5 rounded-full bg-purple-100 text-purple-700 text-[9px] font-mono font-semibold shrink-0">
                                                                @if ($reqPhotoUrl)
                                                                    <span class="absolute inset-0 rounded-full overflow-hidden">
                                                                        <img src="{{ $reqPhotoUrl }}" alt="{{ $reqInitials }}" class="w-full h-full object-cover" />
                                                                    </span>
                                                                @else
                                                                    {{ $reqInitials }}
                                                                @endif
                                                            </span>
                                                            <span class="text-[9px] text-purple-400 font-mono leading-none">Request</span>
                                                        </div>
                                                    @endif
                                                    <div class="mt-1.5">
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">Available</span>
                                                    </div>
                                                    @php $availNoteCount = ($myNotesByAssignment ?? collect())->get($assignment->id, 0); @endphp
                                                    @if ($availNoteCount < 3)
                                                    <div x-data="{ open: false }" class="mt-2">
                                                        <button type="button" @click="open = !open"
                                                                class="text-[10px] text-blue-400 hover:text-blue-600 transition">
                                                            <span x-text="open ? 'Cancel' : '+ Note to editor'"></span>
                                                        </button>
                                                        <div x-show="open" x-cloak class="mt-1.5">
                                                            <form method="POST" action="{{ route('assignment-notes.store', $assignment) }}"
                                                                  x-data="{ body: '' }" class="flex flex-col gap-1">
                                                                @csrf
                                                                <textarea name="body" x-model="body" rows="2" maxlength="1000" required
                                                                          placeholder="Note to editor…"
                                                                          class="w-full text-xs border border-gray-300 rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-blue-400 resize-y"></textarea>
                                                                <button type="submit"
                                                                        :disabled="body.trim() === ''"
                                                                        class="self-end px-2 py-1 text-[10px] bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50">
                                                                    Send
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-3 whitespace-nowrap text-center" title="{{ $accStr ? 'Accepted ' . $accTitle : '' }}">
                                                    <div class="text-gray-500 tabular-nums text-xs leading-none">{{ $accStr ?? '—' }}</div>
                                                    @if ($accStr)
                                                        <div class="text-[9px] text-gray-400 leading-none mt-0.5">ago</div>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-3 whitespace-nowrap text-right"
                                                    x-data="{ accepting: false, declining: false, error: '' }">
                                                    <span x-show="error" x-cloak
                                                          x-text="error"
                                                          class="text-xs text-red-600 font-medium"></span>
                                                    <div x-show="!error" class="flex flex-col items-end gap-1">
                                                        <button type="button"
                                                                :disabled="accepting || declining"
                                                                @click="accepting = true; error = '';
                                                                    fetch('{{ route('assignments.accept', $assignment) }}', {
                                                                        method: 'POST',
                                                                        headers: {
                                                                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                                                            'Accept': 'application/json',
                                                                        }
                                                                    }).then(r => {
                                                                        if (r.ok) {
                                                                            window.dispatchEvent(new CustomEvent('assignment-accepted', { detail: @js($assignment->script_title) }));
                                                                        } else {
                                                                            r.json().then(d => { accepting = false; error = d.message ?? 'No longer available.'; }).catch(() => { accepting = false; error = 'No longer available.'; });
                                                                        }
                                                                    }).catch(() => { accepting = false; error = 'Request failed — try again.'; })"
                                                                :class="(accepting || declining) ? 'opacity-60 cursor-not-allowed' : 'hover:bg-green-500'"
                                                                class="inline-flex items-center px-3 py-1 bg-green-600 border border-transparent rounded text-xs font-semibold text-white transition">
                                                            <span x-text="accepting ? 'Accepting…' : 'Accept'"></span>
                                                        </button>
                                                        @if ($isRequestedForMe)
                                                        <button type="button"
                                                                :disabled="accepting || declining"
                                                                @click="declining = true; error = '';
                                                                    fetch('{{ route('assignments.decline', $assignment) }}', {
                                                                        method: 'POST',
                                                                        headers: {
                                                                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                                                            'Accept': 'application/json',
                                                                        }
                                                                    }).then(r => {
                                                                        if (r.ok) {
                                                                            $el.closest('tr').remove();
                                                                        } else {
                                                                            r.json().then(d => { declining = false; error = d.message ?? 'Could not decline.'; }).catch(() => { declining = false; error = 'Could not decline.'; });
                                                                        }
                                                                    }).catch(() => { declining = false; error = 'Request failed — try again.'; })"
                                                                :class="(accepting || declining) ? 'opacity-60 cursor-not-allowed' : 'hover:bg-red-100'"
                                                                class="inline-flex items-center px-3 py-1 bg-white border border-red-300 rounded text-xs font-semibold text-red-500 transition">
                                                            <span x-text="declining ? 'Declining…' : 'No can do'"></span>
                                                        </button>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach

                                    </tbody>
                                </table>
                                </div>{{-- /overflow-x-auto --}}
                            </div>
                        @endif
                    </div>

                    {{-- ---- My Assignments tab ---- --}}
                    <div x-show="tab === 'mine'">

                        {{-- Replies to this reader's notes --}}
                        @foreach (($myNoteReplies ?? collect()) as $nrItem)
                            @php $nrNote = $nrItem['note']; $nrAssignment = $nrNote->assignment; @endphp
                            @foreach ($nrItem['replies'] as $nrReply)
                            <div class="mb-3 rounded-lg border-2 border-blue-300 bg-blue-50"
                                 id="nreply-mine-{{ $nrReply->id }}">
                                <div class="flex items-center gap-3 px-4 py-3 flex-wrap">
                                    <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold bg-blue-400 text-blue-900 uppercase leading-none">Editor Reply</span>
                                    <span class="text-sm font-medium text-gray-800">{{ $nrAssignment?->script_title ?? '—' }}</span>
                                    <span class="text-xs text-gray-400">{{ $nrReply->created_at->setTimezone($appTimezone)->format('M j, g:ia') }}</span>
                                    <button type="button"
                                            x-data
                                            @click="fetch('{{ route('assignment-note-replies.dismiss', $nrReply) }}', {
                                                method: 'POST',
                                                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' }
                                            }).then(() => { document.getElementById('nreply-mine-{{ $nrReply->id }}')?.remove(); document.getElementById('nreply-{{ $nrReply->id }}')?.remove(); })"
                                            class="ml-auto text-blue-300 hover:text-blue-600 text-sm leading-none transition"
                                            title="Dismiss">✕</button>
                                </div>
                                <div class="border-t border-blue-200 px-4 py-3 space-y-2">
                                    <div>
                                        <p class="text-[10px] text-gray-400 mb-0.5">Your note:</p>
                                        <div class="text-xs text-gray-600 italic whitespace-pre-wrap bg-white border border-blue-100 rounded px-2 py-1.5">{{ $nrNote->body }}</div>
                                    </div>
                                    <div>
                                        <p class="text-[10px] text-gray-400 mb-0.5">Reply from team:</p>
                                        <div class="text-sm text-gray-800 whitespace-pre-wrap bg-white border border-blue-200 rounded px-2 py-1.5">{{ $nrReply->body }}</div>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        @endforeach

                        {{-- Followup questions active for this reader --}}
                        @foreach (($myFollowups ?? collect()) as $fq)
                            @php
                                $fqA        = $fq->assignment;
                                $fqDeadline = $fq->deadlineAt();
                            @endphp
                            @include('partials.reader-followup-row', ['fq' => $fq, 'fqA' => $fqA, 'fqDeadline' => $fqDeadline, 'appTimezone' => $appTimezone])
                        @endforeach

                        @php
                            $mineCurrent   = $mine->filter(fn($a) => in_array($a->status, ['assigned', 'qc']));
                            $mineCompleted = $mine->filter(fn($a) =>
                                $a->status === 'completed' &&
                                $a->reader_paid_at === null &&
                                ($a->completed_at === null ||
                                    ($a->completed_at->gte($periodStart) && $a->completed_at->lte($periodEnd)))
                            );
                            $weekPayTotal  = $mineCompleted->sum(fn($a) => (float) $a->pay_rate);
                            $mineForTab    = $mineCurrent->merge($mineCompleted);
                        @endphp
                        @if($mine->isEmpty())
                            <div class="bg-white rounded-lg border border-gray-200 p-12 text-center text-gray-400 text-sm">
                                You haven't accepted any assignments yet.
                            </div>
                        @else
                            @if($mineCurrent->isEmpty())
                                <div class="bg-white rounded-lg border border-gray-200 p-8 text-center text-gray-400 text-sm">
                                    No active assignments right now.
                                </div>
                            @else
                                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden" x-data="tableSort()">
                                    <div class="flex flex-wrap items-center gap-1.5 px-4 py-2 bg-gray-50 border-b border-gray-200">
                                        <span class="text-[10px] font-medium text-gray-500 uppercase tracking-wide mr-1">Sort:</span>
                                        @foreach (['date' => 'Date', 'age' => 'Age', 'rush' => 'Rush', 'type' => 'Type', 'rate' => 'Rate', 'status' => 'Status'] as $sf => $sl)
                                            <button type="button" @click="setSort('{{ $sf }}')"
                                                    :class="sortBy === '{{ $sf }}' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-500 border-gray-300 hover:bg-gray-100'"
                                                    class="inline-flex items-center px-2 py-0.5 rounded border text-[11px] transition-colors whitespace-nowrap">
                                                {{ $sl }}<span x-show="sortBy === '{{ $sf }}'" x-text="sortDir === 'asc' ? ' ↑' : ' ↓'" class="ml-0.5"></span>
                                            </button>
                                        @endforeach
                                    </div>
                                    <div class="overflow-x-auto">
                                    <table class="w-full min-w-[520px] divide-y divide-gray-200 text-sm">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Order Details</th>
                                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assignment</th>
                                                <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Accepted by</th>
                                                <th class="px-3 py-3"></th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-100" x-ref="sortTbody">
                                            @php
                                                $rMe        = auth()->user();
                                                $rMeProfile = $rMe->readerProfile;
                                                $rMeInitials = $rMeProfile?->initials ?? strtoupper(substr($rMe->name, 0, 2));
                                                $rMePhotoUrl = $rMeProfile?->photo ? asset('storage/' . $rMeProfile->photo) : null;
                                            @endphp
                                            @foreach($mineCurrent as $assignment)
                                            @php
                                                $diff     = $assignment->created_at ? now()->diff($assignment->created_at) : null;
                                                $ageStr   = $diff
                                                    ? ($diff->days >= 1
                                                        ? ($diff->days . 'd ' . $diff->h . 'h')
                                                        : ($diff->h >= 1 ? ($diff->h . 'h ' . $diff->i . 'm') : (max(0, $diff->i) . 'm')))
                                                    : '—';
                                                $ageTitle = $assignment->created_at?->copy()->setTimezone($appTimezone ?? 'UTC')->format('D M j, Y g:ia T') ?? '—';
                                                $ageHours = $diff ? ($diff->days * 24 + $diff->h) : 0;
                                                $ageT     = $ageThresholds[$assignment->assignment_type] ?? ['yellow' => 96, 'orange' => 192, 'red' => 336];
                                                $ageColor = match(true) {
                                                    $ageHours >= $ageT['red']    => 'text-red-600 font-semibold',
                                                    $ageHours >= $ageT['orange'] => 'text-orange-500 font-semibold',
                                                    $ageHours >= $ageT['yellow'] => 'text-yellow-600',
                                                    default                     => 'text-green-600',
                                                };
                                                $accDiff  = $assignment->accepted_at ? now()->diff($assignment->accepted_at) : null;
                                                $accStr   = $accDiff
                                                    ? ($accDiff->days >= 1
                                                        ? ($accDiff->days . 'd ' . $accDiff->h . 'h')
                                                        : ($accDiff->h >= 1 ? ($accDiff->h . 'h ' . $accDiff->i . 'm') : (max(0, $accDiff->i) . 'm')))
                                                    : null;
                                                $accTitle = $assignment->accepted_at?->format('D M j, Y g:ia') ?? null;
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
                                                $viewUrl  = $assignment->hasCloudScript()
                                                    ? route('assignments.streamScript', $assignment)
                                                    : null;
                                                $typeLabel = match($assignment->assignment_type) {
                                                    'script_coverage'   => 'Script Coverage',
                                                    'notes_only'        => 'Notes-Only',
                                                    'deep_dive'         => 'Deep-Dive',
                                                    'short'             => 'Short',
                                                    'budget'            => 'Budget Coverage',
                                                    'book'              => 'Book',
                                                    'coverage'          => 'Coverage',
                                                    'development_notes' => 'Dev Notes',
                                                    default             => $assignment->assignment_type ?? '—',
                                                };
                                                if ($assignment->vendor === 'wd') {
                                                    $typeLabel = 'WD ' . $typeLabel;
                                                }
                                            @endphp
                                            <tr class="hover:bg-gray-50 {{ $rowClass }}"
                                                data-sort-date="{{ $assignment->created_at?->timestamp ?? 0 }}"
                                                data-sort-rush="{{ $assignment->rush ? 1 : 0 }}"
                                                data-sort-type="{{ $assignment->assignment_type ?? '' }}"
                                                data-sort-rate="{{ $assignment->pay_rate ?? 0 }}"
                                                data-sort-status="{{ $assignment->status ?? '' }}">
                                                <td class="px-3 py-3 whitespace-nowrap">
                                                    <span class="font-mono text-gray-700">{{ $assignment->order_number }}</span>
                                                    <div class="mt-1 text-[10px] text-gray-400 tabular-nums">{{ $assignment->created_at?->copy()->setTimezone($appTimezone ?? 'UTC')->format('D M j, Y g:ia') }}</div>
                                                    <div class="text-xs tabular-nums {{ $ageColor }}" title="{{ $ageTitle }}">
                                                        Age: {{ $ageStr }}
                                                        @if ($assignment->rush)
                                                            <div x-data="rushCountdown('{{ $assignment->created_at->copy()->addHours(23)->utc()->toIso8601String() }}', @js($assignment->created_at->copy()->addHours(23)->setTimezone($appTimezone ?? 'UTC')->format('M j, g:ia')))"><div class="mt-0.5"><span class="inline-flex px-1 py-px rounded text-[9px] font-bold bg-amber-400 text-amber-900 uppercase leading-none">Rush</span> <span class="rush-due text-[9px]" x-text="(overdue ? 'Was due by ' : 'Due by ') + dueLabel"></span></div><div x-text="display" :class="overdue ? 'rush-overdue' : 'rush-countdown'" class="text-[9px]"></div></div>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td class="px-3 py-3" x-data="readerPdfViewer(@js($viewUrl), @js($assignment->id), @js(csrf_token()))">
                                                    <div class="text-[10px] text-gray-400 uppercase tracking-wide mb-0.5">{{ $typeLabel }}</div>
                                                    @if($viewUrl)
                                                        <button @click="openViewer()" type="button"
                                                                class="font-medium text-gray-900 hover:text-indigo-600 text-left leading-snug max-w-xs block">{{ $assignment->script_title }}</button>
                                                        <div x-show="open" x-cloak
                                                             @keydown.escape.window="open = false"
                                                             x-ref="modal"
                                                             tabindex="-1"
                                                             class="fixed inset-0 z-50 flex flex-col bg-black/80">
                                                            @include('partials.reader-pdf-viewer', ['assignment' => $assignment])
                                                        </div>
                                                    @else
                                                        <div class="font-medium text-gray-900 max-w-xs">{{ $assignment->script_title }}</div>
                                                    @endif
                                                    <div class="text-xs text-gray-500">{{ $assignment->writer_name }}</div>
                                                    <div class="text-[10px] text-gray-400 tabular-nums">{{ $assignment->page_count }}p · ${{ number_format($assignment->pay_rate, 2) }}</div>
                                                    <div class="mt-1.5">
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColor }}">{{ $statusLabel }}</span>
                                                    </div>
                                                    @php $mineNoteCount = ($myNotesByAssignment ?? collect())->get($assignment->id, 0); @endphp
                                                    @if ($mineNoteCount < 3)
                                                    <div x-data="{ open: false }" class="mt-2">
                                                        <button type="button" @click="open = !open"
                                                                class="text-[10px] text-blue-400 hover:text-blue-600 transition">
                                                            <span x-text="open ? 'Cancel' : '+ Note to editor'"></span>
                                                        </button>
                                                        <div x-show="open" x-cloak class="mt-1.5">
                                                            <form method="POST" action="{{ route('assignment-notes.store', $assignment) }}"
                                                                  x-data="{ body: '' }" class="flex flex-col gap-1">
                                                                @csrf
                                                                <textarea name="body" x-model="body" rows="2" maxlength="1000" required
                                                                          placeholder="Note to editor…"
                                                                          class="w-full text-xs border border-gray-300 rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-blue-400 resize-y"></textarea>
                                                                <button type="submit"
                                                                        :disabled="body.trim() === ''"
                                                                        class="self-end px-2 py-1 text-[10px] bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50">
                                                                    Send
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-3 whitespace-nowrap text-center" title="{{ $accStr ? 'Accepted ' . $accTitle : '' }}">
                                                    <div class="flex flex-col items-center gap-0.5 mb-1">
                                                        <span class="text-[9px] text-gray-400 font-mono leading-none">Me</span>
                                                        <span class="relative inline-flex items-center justify-center w-7 h-7 rounded-full bg-gray-200 text-gray-700 text-xs font-mono font-semibold">
                                                            @if ($rMePhotoUrl)
                                                                <span class="absolute inset-0 rounded-full overflow-hidden">
                                                                    <img src="{{ $rMePhotoUrl }}" alt="{{ $rMeInitials }}" class="w-full h-full object-cover" />
                                                                </span>
                                                            @else
                                                                {{ $rMeInitials }}
                                                            @endif
                                                        </span>
                                                        <span class="text-[9px] text-gray-400 font-mono leading-none">{{ $rMeInitials }}</span>
                                                    </div>
                                                    @if ($assignment->accepted_at)
                                                        <div class="text-[9px] text-gray-500 tabular-nums leading-none mb-0.5">{{ $assignment->accepted_at->copy()->setTimezone($appTimezone ?? 'UTC')->format('D M j, Y g:ia') }}</div>
                                                    @endif
                                                    <div class="text-gray-500 tabular-nums text-xs leading-none">{{ $accStr ?? '—' }}</div>
                                                    @if ($accStr)
                                                        <div class="text-[9px] text-gray-400 leading-none mt-0.5">ago</div>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-3 whitespace-nowrap text-right">
                                                    <div class="flex items-center justify-end gap-2">
                                                        @can('submitCoverage', $assignment)
                                                            <a href="{{ route('coverage.show', $assignment) }}"
                                                               class="inline-flex items-center px-2.5 py-1 bg-indigo-600 border border-transparent rounded text-xs font-semibold text-white hover:bg-indigo-500 transition whitespace-nowrap">
                                                                {{ $assignment->coverageSubmission ? 'Continue Coverage' : 'Write Coverage' }}
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
                                    </div>{{-- /overflow-x-auto --}}
                                </div>
                            @endif

                        @endif
                    </div>

                    {{-- ---- Completed This Week tab ---- --}}
                    <div x-show="tab === 'week'">
                        @if($mineCompleted->isEmpty())
                            <div class="bg-white rounded-lg border border-gray-200 p-12 text-center text-gray-400 text-sm">
                                No completed assignments this week yet.
                            </div>
                        @else
                        <div class="flex items-baseline justify-between mb-3 px-1">
                            <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider">
                                Completed This Week
                                <span class="ml-1 normal-case font-normal text-gray-400">({{ \App\Support\PayPeriod::label($periodStart) }})</span>
                            </h3>
                            <span class="text-sm font-semibold text-green-700">${{ number_format($weekPayTotal, 2) }}</span>
                        </div>
                                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden" x-data="tableSort()">
                                    <div class="flex flex-wrap items-center gap-1.5 px-4 py-2 bg-gray-50 border-b border-gray-200">
                                        <span class="text-[10px] font-medium text-gray-500 uppercase tracking-wide mr-1">Sort:</span>
                                        @foreach (['date' => 'Date', 'age' => 'Age', 'rush' => 'Rush', 'type' => 'Type', 'rate' => 'Rate', 'status' => 'Status'] as $sf => $sl)
                                            <button type="button" @click="setSort('{{ $sf }}')"
                                                    :class="sortBy === '{{ $sf }}' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-500 border-gray-300 hover:bg-gray-100'"
                                                    class="inline-flex items-center px-2 py-0.5 rounded border text-[11px] transition-colors whitespace-nowrap">
                                                {{ $sl }}<span x-show="sortBy === '{{ $sf }}'" x-text="sortDir === 'asc' ? ' ↑' : ' ↓'" class="ml-0.5"></span>
                                            </button>
                                        @endforeach
                                    </div>
                                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Order Details</th>
                                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assignment</th>
                                                <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Completed by</th>
                                                <th class="px-3 py-3"></th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-100" x-ref="sortTbody">
                                            @foreach($mineCompleted as $assignment)
                                                @php
                                                    // Overall turnaround: created_at → completed_at
                                                    $overallDiff  = ($assignment->created_at && $assignment->completed_at)
                                                        ? $assignment->created_at->diff($assignment->completed_at) : null;
                                                    $overallStr   = $overallDiff
                                                        ? ($overallDiff->days >= 1
                                                            ? ($overallDiff->days . 'd ' . $overallDiff->h . 'h')
                                                            : ($overallDiff->h >= 1 ? ($overallDiff->h . 'h ' . $overallDiff->i . 'm') : (max(0, $overallDiff->i) . 'm')))
                                                        : '—';
                                                    $overallTitle = ($assignment->created_at && $assignment->completed_at)
                                                        ? ($assignment->created_at->format('M j g:ia') . ' → ' . $assignment->completed_at->format('M j g:ia'))
                                                        : '—';
                                                    // Assignment turnaround: accepted_at (or created_at) → completed_at
                                                    $assignStart  = $assignment->accepted_at ?? $assignment->created_at;
                                                    $assignDiff   = ($assignStart && $assignment->completed_at)
                                                        ? $assignStart->diff($assignment->completed_at) : null;
                                                    $assignStr    = $assignDiff
                                                        ? ($assignDiff->days >= 1
                                                            ? ($assignDiff->days . 'd ' . $assignDiff->h . 'h')
                                                            : ($assignDiff->h >= 1 ? ($assignDiff->h . 'h ' . $assignDiff->i . 'm') : (max(0, $assignDiff->i) . 'm')))
                                                        : '—';
                                                    $assignTitle  = ($assignStart && $assignment->completed_at)
                                                        ? ($assignStart->format('M j g:ia') . ' → ' . $assignment->completed_at->format('M j g:ia'))
                                                        : '—';
                                                    $reqInitials  = $assignment->requestedReader?->readerProfile?->initials;
                                                    $reqPhotoUrl  = $assignment->requestedReader?->readerProfile?->photo
                                                        ? asset('storage/' . $assignment->requestedReader->readerProfile->photo)
                                                        : null;
                                                    $isRequestedForMe = $assignment->requested_reader_id === auth()->id();
                                                    $rowClass = $assignment->rush ? 'border-l-4 border-amber-400' : '';
                                                    $viewUrl  = $assignment->hasCloudScript()
                                                        ? route('assignments.streamScript', $assignment)
                                                        : null;
                                                    $mePhotoUrl   = auth()->user()->readerProfile?->photo
                                                        ? asset('storage/' . auth()->user()->readerProfile->photo)
                                                        : null;
                                                    $meInitials   = auth()->user()->readerProfile?->initials ?? 'Me';
                                                    $typeLabel = match($assignment->assignment_type) {
                                                        'script_coverage'   => 'Script Coverage',
                                                        'notes_only'        => 'Notes-Only',
                                                        'deep_dive'         => 'Deep-Dive',
                                                        'short'             => 'Short',
                                                        'budget'            => 'Budget Coverage',
                                                        'book'              => 'Book',
                                                        'coverage'          => 'Coverage',
                                                        'development_notes' => 'Dev Notes',
                                                        default             => $assignment->assignment_type ?? '—',
                                                    };
                                                    if ($assignment->vendor === 'wd') {
                                                        $typeLabel = 'WD ' . $typeLabel;
                                                    }
                                                @endphp
                                                <tr class="hover:bg-gray-50 {{ $rowClass }}"
                                                    data-sort-date="{{ $assignment->created_at?->timestamp ?? 0 }}"
                                                    data-sort-rush="{{ $assignment->rush ? 1 : 0 }}"
                                                    data-sort-type="{{ $assignment->assignment_type ?? '' }}"
                                                    data-sort-rate="{{ $assignment->pay_rate ?? 0 }}"
                                                    data-sort-status="{{ $assignment->status ?? '' }}">
                                                    <td class="px-3 py-3 whitespace-nowrap">
                                                        <span class="font-mono text-gray-700">{{ $assignment->order_number }}</span>
                                                        @if ($assignment->rush)
                                                            <div x-data="rushCountdown('{{ $assignment->created_at->copy()->addHours(23)->utc()->toIso8601String() }}', @js($assignment->created_at->copy()->addHours(23)->setTimezone($appTimezone ?? 'UTC')->format('M j, g:ia')))"><div class="mt-0.5"><span class="inline-flex px-1 py-px rounded text-[9px] font-bold bg-amber-400 text-amber-900 uppercase leading-none">Rush</span> <span class="rush-due text-[9px]" x-text="(overdue ? 'Was due by ' : 'Due by ') + dueLabel"></span></div><div x-text="display" :class="overdue ? 'rush-overdue' : 'rush-countdown'" class="text-[9px]"></div></div>
                                                        @endif
                                                        <div class="mt-1.5">
                                                            <div class="text-[9px] text-gray-400 uppercase tracking-wide leading-none mb-0.5">Overall Turnaround</div>
                                                            <div class="text-xs tabular-nums text-gray-600" title="{{ $overallTitle }}">{{ $overallStr }}</div>
                                                        </div>
                                                        <div class="mt-1">
                                                            <div class="text-[9px] text-gray-400 uppercase tracking-wide leading-none mb-0.5">Assignment Turnaround</div>
                                                            <div class="text-xs tabular-nums text-gray-600" title="{{ $assignTitle }}">{{ $assignStr }}</div>
                                                        </div>
                                                    </td>
                                                    <td class="px-3 py-3" x-data="{ textOpen: false }">
                                                        <div class="text-[10px] text-gray-400 uppercase tracking-wide mb-0.5">{{ $typeLabel }}</div>
                                                        <div class="font-medium text-gray-900 max-w-xs">{{ $assignment->script_title }}</div>
                                                        <div class="text-xs text-gray-500">{{ $assignment->writer_name }}</div>
                                                        <div class="text-[10px] text-gray-400 tabular-nums">{{ $assignment->page_count }}p · ${{ number_format($assignment->pay_rate, 2) }}</div>
                                                        @if($assignment->coverageSubmission)
                                                            <div class="mt-1">
                                                                <button @click="textOpen = true" type="button"
                                                                        class="text-[10px] text-indigo-500 hover:text-indigo-700 hover:underline">View Coverage</button>
                                                            </div>
                                                            <div x-show="textOpen" x-cloak
                                                                 @keydown.escape.window="textOpen = false"
                                                                 class="fixed inset-0 z-50 flex flex-col bg-black/80">
                                                                <div class="flex items-center justify-between px-4 py-2 bg-gray-900 shrink-0 gap-2 flex-wrap">
                                                                    <span class="text-sm text-gray-200 font-medium truncate min-w-0">
                                                                        {{ $assignment->script_title }} — Coverage
                                                                    </span>
                                                                    <button @click="textOpen = false" type="button"
                                                                            class="text-gray-400 hover:text-white text-2xl leading-none px-1">×</button>
                                                                </div>
                                                                <iframe :src="textOpen ? @js(route('coverage.preview', $assignment)) : ''"
                                                                        class="flex-1 w-full border-0 bg-white"></iframe>
                                                            </div>
                                                        @endif
                                                        <div class="mt-1.5">
                                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Completed</span>
                                                        </div>
                                                    </td>
                                                    <td class="px-3 py-3 whitespace-nowrap text-center">
                                                        <div class="flex flex-col items-center gap-1">
                                                            @if($mePhotoUrl)
                                                                <img src="{{ $mePhotoUrl }}" alt="{{ $meInitials }}"
                                                                     class="w-7 h-7 rounded-full object-cover ring-1 ring-gray-200">
                                                            @else
                                                                <div class="w-7 h-7 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-[10px] font-semibold ring-1 ring-gray-200">{{ $meInitials }}</div>
                                                            @endif
                                                            <span class="text-xs text-gray-500">Me</span>
                                                            @if($assignment->completed_at)
                                                                <span class="text-[10px] text-gray-400 tabular-nums leading-tight">on {{ $assignment->completed_at->copy()->setTimezone($appTimezone ?? 'UTC')->format('M j, Y g:ia') }}</span>
                                                            @endif
                                                        </div>
                                                    </td>
                                                    <td class="px-3 py-3"></td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                        @endif
                    </div>

                    {{-- ---- Needs Attention tab ---- --}}
                    @if($needsAttentionCount > 0)
                    <div x-show="tab === 'attention'">
                        @php $mineAttention = $mine->where('status', 'needs_attention'); @endphp
                        <div class="bg-white rounded-lg shadow-sm border border-orange-200 overflow-hidden">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-orange-50">
                                    <tr>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-orange-600 uppercase tracking-wider whitespace-nowrap">Order Details</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-orange-600 uppercase tracking-wider">Assignment</th>
                                        <th class="px-3 py-3 text-center text-xs font-medium text-orange-600 uppercase tracking-wider whitespace-nowrap">Accepted by</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-orange-600 uppercase tracking-wider">Notes from Editor</th>
                                        <th class="px-3 py-3"></th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-100">
                                    @foreach($mineAttention as $assignment)
                                        @php
                                            $diff     = $assignment->created_at ? now()->diff($assignment->created_at) : null;
                                            $ageStr   = $diff
                                                ? ($diff->days >= 1
                                                    ? ($diff->days . 'd ' . $diff->h . 'h')
                                                    : ($diff->h >= 1 ? ($diff->h . 'h ' . $diff->i . 'm') : (max(0, $diff->i) . 'm')))
                                                : '—';
                                            $ageTitle = $assignment->created_at?->copy()->setTimezone($appTimezone ?? 'UTC')->format('D M j, Y g:ia T') ?? '—';
                                            $ageHours = $diff ? ($diff->days * 24 + $diff->h) : 0;
                                            $ageT     = $ageThresholds[$assignment->assignment_type] ?? ['yellow' => 96, 'orange' => 192, 'red' => 336];
                                            $ageColor = match(true) {
                                                $ageHours >= $ageT['red']    => 'text-red-600 font-semibold',
                                                $ageHours >= $ageT['orange'] => 'text-orange-500 font-semibold',
                                                $ageHours >= $ageT['yellow'] => 'text-yellow-600',
                                                default                     => 'text-green-600',
                                            };
                                            $accDiff  = $assignment->accepted_at ? now()->diff($assignment->accepted_at) : null;
                                            $accStr   = $accDiff
                                                ? ($accDiff->days >= 1
                                                    ? ($accDiff->days . 'd ' . $accDiff->h . 'h')
                                                    : ($accDiff->h >= 1 ? ($accDiff->h . 'h ' . $accDiff->i . 'm') : (max(0, $accDiff->i) . 'm')))
                                                : null;
                                            $accTitle = $assignment->accepted_at?->format('D M j, Y g:ia') ?? null;
                                            $typeLabel = match($assignment->assignment_type) {
                                                'script_coverage'   => 'Script Coverage',
                                                'notes_only'        => 'Notes-Only',
                                                'deep_dive'         => 'Deep-Dive',
                                                'short'             => 'Short',
                                                'budget'            => 'Budget Coverage',
                                                'book'              => 'Book',
                                                'coverage'          => 'Coverage',
                                                'development_notes' => 'Dev Notes',
                                                default             => $assignment->assignment_type ?? '—',
                                            };
                                            if ($assignment->vendor === 'wd') {
                                                $typeLabel = 'WD ' . $typeLabel;
                                            }
                                        @endphp
                                        <tr class="hover:bg-orange-50 border-l-4 border-orange-400">
                                            <td class="px-3 py-3 whitespace-nowrap">
                                                <span class="font-mono text-gray-700">{{ $assignment->order_number }}</span>
                                                <div class="mt-1 text-xs tabular-nums {{ $ageColor }}" title="{{ $ageTitle }}">
                                                    {{ $ageStr }}
                                                    @if ($assignment->rush)
                                                        <div x-data="rushCountdown('{{ $assignment->created_at->copy()->addHours(23)->utc()->toIso8601String() }}', @js($assignment->created_at->copy()->addHours(23)->setTimezone($appTimezone ?? 'UTC')->format('M j, g:ia')))"><div class="mt-0.5"><span class="inline-flex px-1 py-px rounded text-[9px] font-bold bg-amber-400 text-amber-900 uppercase leading-none">Rush</span> <span class="rush-due text-[9px]" x-text="(overdue ? 'Was due by ' : 'Due by ') + dueLabel"></span></div><div x-text="display" :class="overdue ? 'rush-overdue' : 'rush-countdown'" class="text-[9px]"></div></div>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="px-3 py-3">
                                                <div class="text-[10px] text-gray-400 uppercase tracking-wide mb-0.5">{{ $typeLabel }}</div>
                                                <div class="font-medium text-gray-900 max-w-xs">{{ $assignment->script_title }}</div>
                                                <div class="text-xs text-gray-500">{{ $assignment->writer_name }}</div>
                                                <div class="text-[10px] text-gray-400 tabular-nums">{{ $assignment->page_count }}p · ${{ number_format($assignment->pay_rate, 2) }}</div>
                                            </td>
                                            <td class="px-3 py-3 whitespace-nowrap text-center" title="{{ $accStr ? 'Accepted ' . $accTitle : '' }}">
                                                <div class="text-gray-500 tabular-nums text-xs leading-none">{{ $accStr ?? '—' }}</div>
                                                @if ($accStr)
                                                    <div class="text-[9px] text-gray-400 leading-none mt-0.5">ago</div>
                                                @endif
                                            </td>
                                            <td class="px-3 py-3 text-sm text-gray-700 max-w-xs">
                                                @if($assignment->needs_attention_notes)
                                                    <p class="whitespace-pre-line">{{ $assignment->needs_attention_notes }}</p>
                                                @else
                                                    <span class="text-gray-400 italic">No notes provided.</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-3 whitespace-nowrap text-right">
                                                @can('submitCoverage', $assignment)
                                                    <a href="{{ route('coverage.show', $assignment) }}"
                                                       class="inline-flex items-center px-2.5 py-1 bg-orange-500 border border-transparent rounded text-xs font-semibold text-white hover:bg-orange-600 transition whitespace-nowrap">
                                                        Fix Coverage
                                                    </a>
                                                @endcan
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @endif

                    {{-- ---- Archived tab ---- --}}
                    <div x-show="tab === 'archived'" x-data="{ q: '' }">
                        <div class="mb-4">
                            <input type="text" x-model="q"
                                   placeholder="Search by title, order #, or writer…"
                                   class="text-sm border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 w-72" />
                        </div>

                        @if($archivedByPeriod->isEmpty())
                            <div class="bg-white rounded-lg border border-gray-200 p-12 text-center text-gray-400 text-sm">
                                No archived assignments yet.
                            </div>
                        @else
                            @foreach($archivedByPeriod as $periodKey => $periodAssignments)
                                @php
                                    $pStart  = \Carbon\Carbon::parse($periodKey, \App\Support\PayPeriod::TZ);
                                    $pLabel  = \App\Support\PayPeriod::label($pStart);
                                    $pTotal  = $periodAssignments->sum(fn($a) => (float) $a->pay_rate);
                                    $isPaid  = $periodAssignments->every(fn($a) => $a->reader_paid_at !== null);
                                    $paidAt  = $isPaid ? $periodAssignments->first()->reader_paid_at : null;
                                @endphp
                                <div class="mb-6">
                                    <div class="flex items-center justify-between mb-2 px-1">
                                        <div class="flex items-center gap-2">
                                            <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider">{{ $pLabel }}</h3>
                                            @if($isPaid)
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-green-100 text-green-700"
                                                      title="Paid {{ $paidAt?->format('M j, Y') }}">Paid</span>
                                            @endif
                                        </div>
                                        <span class="text-xs font-semibold text-gray-500">${{ number_format($pTotal, 2) }}</span>
                                    </div>
                                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                                            <tbody class="bg-white divide-y divide-gray-100">
                                                @foreach($periodAssignments as $assignment)
                                                    @php
                                                        $typeLabel = match($assignment->assignment_type) {
                                                            'script_coverage'   => 'Script Coverage',
                                                            'notes_only'        => 'Notes-Only',
                                                            'deep_dive'         => 'Deep-Dive',
                                                            'short'             => 'Short',
                                                            'budget'            => 'Budget Coverage',
                                                            'book'              => 'Book',
                                                            'coverage'          => 'Coverage',
                                                            'development_notes' => 'Dev Notes',
                                                            default             => $assignment->assignment_type ?? '—',
                                                        };
                                                        if ($assignment->vendor === 'wd') {
                                                            $typeLabel = 'WD ' . $typeLabel;
                                                        }
                                                        $rtaDiff = ($assignment->accepted_at && $assignment->submitted_at)
                                                            ? $assignment->accepted_at->diff($assignment->submitted_at)
                                                            : null;
                                                        $rtaStr = $rtaDiff
                                                            ? ($rtaDiff->days >= 1
                                                                ? ($rtaDiff->days . 'd ' . $rtaDiff->h . 'h ' . $rtaDiff->i . 'm')
                                                                : ($rtaDiff->h >= 1
                                                                    ? ($rtaDiff->h . 'h ' . $rtaDiff->i . 'm')
                                                                    : ($rtaDiff->i . 'm')))
                                                            : null;
                                                    @endphp
                                                    <tr class="hover:bg-gray-50"
                                                        x-show="q === '' ||
                                                            $el.dataset.title.includes(q.toLowerCase()) ||
                                                            $el.dataset.order.includes(q.toLowerCase()) ||
                                                            $el.dataset.writer.includes(q.toLowerCase())"
                                                        data-title="{{ strtolower($assignment->script_title ?? '') }}"
                                                        data-order="{{ strtolower($assignment->order_number ?? '') }}"
                                                        data-writer="{{ strtolower($assignment->writer_name ?? '') }}">
                                                        <td class="px-3 py-2" x-data="{ textOpen: false }">
                                                            <div class="text-[10px] font-mono text-gray-500 mb-0.5">{{ $assignment->order_number }}</div>
                                                            <div class="text-[10px] text-gray-400 uppercase tracking-wide mb-0.5">{{ $typeLabel }}</div>
                                                            <div class="font-medium text-gray-900 text-xs">{{ $assignment->script_title }}</div>
                                                            <div class="text-xs text-gray-500">{{ $assignment->writer_name }}</div>
                                                            <div class="text-[10px] text-gray-400 tabular-nums">{{ $assignment->page_count }}p · <span class="font-bold text-gray-600">${{ number_format($assignment->pay_rate, 2) }}</span></div>
                                                            <div class="text-[10px] text-gray-400 tabular-nums mt-0.5">Completed {{ $assignment->completed_at?->copy()->setTimezone($appTimezone ?? 'UTC')->format('D M j, Y g:ia') ?? '—' }}</div>
                                                            @if($rtaStr)
                                                                <div class="text-[10px] text-gray-400 tabular-nums">Reader turnaround time: {{ $rtaStr }}</div>
                                                            @endif
                                                            @php
                                                                $fqComplete = \App\Models\FollowupQuestion::where('assignment_id', $assignment->id)
                                                                    ->where('status', \App\Models\FollowupQuestion::STATUS_COMPLETE)
                                                                    ->exists();
                                                                $fqPending = \App\Models\FollowupQuestion::where('assignment_id', $assignment->id)
                                                                    ->whereIn('status', ['unanswered', 'answered'])
                                                                    ->exists();
                                                            @endphp
                                                            @if($fqComplete)
                                                                <div class="text-[10px] text-green-600 font-medium mt-0.5">✓ Followup questions answered</div>
                                                            @elseif($fqPending)
                                                                <div class="text-[10px] text-amber-600 font-medium mt-0.5">Followup questions pending</div>
                                                            @endif
                                                            @if($assignment->coverageSubmission)
                                                                <button @click="textOpen = true" type="button"
                                                                        class="text-[10px] text-indigo-500 hover:text-indigo-700 hover:underline mt-0.5 leading-none">View coverage</button>
                                                                <div x-show="textOpen" x-cloak
                                                                     @keydown.escape.window="textOpen = false"
                                                                     class="fixed inset-0 z-50 flex flex-col bg-black/80">
                                                                    <div class="flex items-center justify-between px-4 py-2 bg-gray-900 shrink-0 gap-2">
                                                                        <span class="text-sm text-gray-200 font-medium truncate min-w-0">{{ $assignment->script_title }} — Coverage</span>
                                                                        <button @click="textOpen = false" type="button"
                                                                                class="text-gray-400 hover:text-white text-2xl leading-none px-1">×</button>
                                                                    </div>
                                                                    <iframe :src="textOpen ? @js(route('coverage.preview', $assignment)) : ''"
                                                                            class="flex-1 w-full border-0 bg-white"></iframe>
                                                                </div>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    </div>

                </div>
            @endif

        </div>
    </div>

    @once
        @push('scripts')
        <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('acceptedCelebration', () => ({
                show: false,
                title: '',
                _timer: null,

                async celebrate(title) {
                    this.title = title || '';
                    this.show = true;

                    if (!window.confetti) {
                        await new Promise((res, rej) => {
                            const s = document.createElement('script');
                            s.src = 'https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js';
                            s.onload = res; s.onerror = rej;
                            document.head.appendChild(s);
                        });
                    }

                    const colors = ['#a78bfa', '#818cf8', '#67e8f9', '#fbbf24', '#f9a8d4', '#6ee7b7'];
                    const end = Date.now() + 2400;
                    const burst = () => {
                        confetti({ particleCount: 4, angle: 60, spread: 60, origin: { x: 0, y: 0.6 }, colors });
                        confetti({ particleCount: 4, angle: 120, spread: 60, origin: { x: 1, y: 0.6 }, colors });
                        if (Date.now() < end) requestAnimationFrame(burst);
                    };
                    requestAnimationFrame(burst);

                    this._timer = setTimeout(() => this.dismiss(), 2800);
                },

                dismiss() {
                    clearTimeout(this._timer);
                    this.show = false;
                    setTimeout(() => location.reload(), 500);
                },
            }));
        });
        </script>
        @endpush
    @endonce

    @include('partials.reader-pdf-viewer-script')

    {{-- Assignment accepted celebration overlay --}}
    <div x-data="acceptedCelebration()"
         x-show="show"
         x-cloak
         x-transition:enter="transition-opacity ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-500"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @assignment-accepted.window="celebrate($event.detail)"
         @click="dismiss()"
         class="fixed inset-0 z-[9000] flex flex-col items-center justify-center bg-indigo-950/95 backdrop-blur-sm cursor-pointer select-none">
        <div class="text-center px-6 max-w-sm w-full mx-auto">
            <div class="text-8xl font-black text-white leading-none mb-4">✓</div>
            <div class="text-5xl sm:text-7xl font-black text-white leading-tight tracking-tight">Accepted!</div>
            <div x-show="title" x-text="title"
                 class="mt-3 text-base sm:text-lg text-indigo-300 font-medium leading-snug"></div>
            <div class="mt-10 text-sm text-indigo-400">Tap to continue</div>
        </div>
    </div>
</x-app-layout>
