{{-- Admin assignment table row. Expects: $assignment, $ageThresholds, $appTimezone, $assignableUsers --}}
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

    $statusLabel = match($assignment->status) {
        'on_hold_customer' => 'On Hold – Customer',
        'on_hold_sr'       => 'On Hold – SR',
        'qc'               => 'QC',
        'needs_attention'  => 'Needs Attention',
        default            => ucfirst($assignment->status),
    };

    $reqInitials  = $assignment->requestedReader?->readerProfile?->initials;
    $reqPhotoUrl  = $assignment->requestedReader?->readerProfile?->photo
        ? asset('storage/' . $assignment->requestedReader->readerProfile->photo)
        : null;

    $assignedInitials = $assignment->assignedReader?->readerProfile?->initials
        ?? $assignment->assignedReader?->editorProfile?->initials
        ?? ($assignment->assignedReader ? strtoupper(substr($assignment->assignedReader->name, 0, 2)) : null);
    $assignedPhoto    = $assignment->assignedReader?->readerProfile?->photo
        ?? $assignment->assignedReader?->editorProfile?->photo;
    $assignedPhotoUrl = $assignedPhoto ? asset('storage/' . $assignedPhoto) : null;

    $viewUrl  = $assignment->hasCloudScript()
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
        'budget'            => 'Budget Coverage',
        'book'              => 'Book',
        'coverage'          => 'Coverage',
        'development_notes' => 'Dev Notes',
        default             => $assignment->assignment_type ?? '—',
    };
    if ($assignment->vendor === 'wd') {
        $typeLabel = 'WD ' . $typeLabel;
    }
    $searchStr = strtolower(implode(' ', array_filter([
        $assignment->order_number,
        $assignment->script_title,
        $assignment->writer_name,
        $assignment->assignedReader?->readerProfile?->displayName(),
        $assignment->assignedReader?->readerProfile?->initials,
        $assignment->assignedReader?->name,
    ])));
@endphp
<tr class="hover:bg-gray-50 {{ $rowClass }} cursor-pointer"
    x-show="!search || $el.dataset.search.includes(search.toLowerCase())"
    data-search="{{ $searchStr }}"
    data-sort-date="{{ $assignment->created_at?->timestamp ?? 0 }}"
    data-sort-rush="{{ $assignment->rush ? 1 : 0 }}"
    data-sort-type="{{ $assignment->assignment_type ?? '' }}"
    data-sort-rate="{{ $assignment->pay_rate ?? 0 }}"
    data-sort-status="{{ $assignment->status ?? '' }}"
    data-sort-acceptedby="{{ strtolower($assignedInitials ?? '') }}"
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

    {{-- Assignment (type + notes icon + title + writer + pages · pay + request + status) --}}
    <td class="px-3 py-3"
        x-data="{
            viewerOpen: false,
            notesOpen: false,
            hover: false,
            tipX: 0,
            tipY: 0,
            note: @js($assignment->notes ?? ''),
            saving: false,
            saved: false,
            async saveNote() {
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
        <div class="text-[10px] text-gray-400 uppercase tracking-wide mb-0.5 flex items-center gap-1.5">
            {{ $typeLabel }}
            @if($assignment->is_test)
                <span class="inline-flex items-center px-1 py-px rounded text-[9px] font-bold bg-amber-200 text-amber-800 tracking-wide">TEST</span>
            @endif
        </div>
        <div class="flex items-center gap-1">
            @if($assignment->notes)
                <div class="inline-block shrink-0"
                     @mouseenter="hover = true; const r = $el.getBoundingClientRect(); tipX = r.left + r.width / 2; tipY = r.top"
                     @mouseleave="hover = false">
                    <button @click="notesOpen = !notesOpen" type="button"
                            class="text-amber-500 hover:text-amber-600 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3.5 h-3.5">
                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                    <div x-show="hover && !notesOpen" x-cloak
                         :style="`position:fixed;left:${tipX}px;top:${tipY}px;transform:translate(-50%,calc(-100% - 8px))`"
                         class="z-50 w-56 bg-gray-800 text-white text-xs rounded-md px-2.5 py-2 shadow-lg whitespace-pre-wrap pointer-events-none">
                        <p x-text="note"></p>
                        <div class="absolute top-full left-1/2 -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-l-transparent border-r-transparent border-t-4 border-t-gray-800"></div>
                    </div>
                </div>
            @endif
            @if($viewUrl)
                <button @click="viewerOpen = true; window.dispatchEvent(new CustomEvent('sr-load-pdf-{{ $assignment->id }}'))" type="button"
                        class="font-medium text-gray-900 hover:text-indigo-600 text-left leading-snug max-w-xs block">📄 {{ $assignment->script_title }}</button>
            @else
                <div class="font-medium text-gray-900 max-w-xs">{{ $assignment->script_title }}</div>
            @endif
        </div>
        <div class="text-xs text-gray-500">{{ $assignment->writer_name }}</div>
        <div class="text-[10px] text-gray-400 tabular-nums">{{ $assignment->page_count }}p · ${{ number_format($assignment->pay_rate, 2) }}</div>
        @if ($reqInitials)
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
                @if ($assignment->reader_declined)
                    <span class="text-[9px] text-red-400 font-mono leading-none">· No can do</span>
                @endif
            </div>
        @endif
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

        {{-- Notes edit panel --}}
        <div x-show="notesOpen" x-cloak class="mt-1.5 w-56">
            <textarea x-model="note" rows="3"
                      class="w-full text-xs border border-gray-200 rounded p-1.5 resize-none focus:outline-none focus:ring-1 focus:ring-indigo-400"></textarea>
            <div class="flex items-center justify-end gap-1 mt-1">
                <button type="button" @click="notesOpen=false"
                        class="text-xs text-gray-400 hover:text-gray-600 px-1.5 py-0.5">Close</button>
                <button type="button" :disabled="saving" @click="saveNote()"
                        class="text-xs px-2 py-0.5 bg-indigo-600 text-white rounded hover:bg-indigo-500 disabled:opacity-50"
                        x-text="saving ? 'Saving…' : 'Save'"></button>
            </div>
            <span x-show="saved" class="text-[10px] text-green-600 block mt-0.5">Saved</span>
        </div>

        {{-- Script viewer --}}
        @if($viewUrl)
            <div x-show="viewerOpen" x-cloak
                 @keydown.escape.window="viewerOpen = false"
                 tabindex="-1"
                 x-effect="if (viewerOpen) $nextTick(() => $el.focus())"
                 class="fixed inset-0 z-50 flex flex-col bg-black/80">
                <div class="flex items-center justify-between px-4 py-2 bg-gray-900 shrink-0 gap-2 flex-wrap">
                    <span class="text-sm text-gray-200 font-medium truncate min-w-0">{{ $assignment->drive_script_filename ?? $assignment->script_title }}</span>
                    <div class="flex items-center gap-2 shrink-0"
                         x-data="{
                             pgStatus: '',
                             pgError: false,
                             pg: '',
                             async act(url, body, confirmMsg) {
                                 if (!confirm(confirmMsg)) return;
                                 this.pgStatus = 'Working…';
                                 this.pgError = false;
                                 try {
                                     const r = await fetch(url, {
                                         method: 'POST',
                                         headers: {
                                             'Content-Type': 'application/x-www-form-urlencoded',
                                             'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                             'X-Requested-With': 'XMLHttpRequest',
                                         },
                                         body,
                                     });
                                     const d = await r.json();
                                     if (d.success) {
                                         this.pgStatus = d.message || 'Done.';
                                         window.dispatchEvent(new CustomEvent('sr-reload-pdf-{{ $assignment->id }}'));
                                         setTimeout(() => { this.pgStatus = ''; }, 4000);
                                     } else {
                                         this.pgStatus = d.message || 'Error.';
                                         this.pgError = true;
                                     }
                                 } catch(e) {
                                     this.pgStatus = 'Request failed.';
                                     this.pgError = true;
                                 }
                             },
                         }">
                        @if (\App\Support\Permission::check('script.download'))
                            <a href="{{ route('assignments.downloadScript', $assignment) }}"
                               class="px-2 py-1 bg-gray-700 hover:bg-gray-600 rounded text-xs text-white whitespace-nowrap">Download</a>
                        @endif
                        @if (\App\Support\Permission::check('script.print'))
                            <a href="{{ route('assignments.streamScript', $assignment) }}" target="_blank" rel="noopener"
                               class="px-2 py-1 bg-gray-700 hover:bg-gray-600 rounded text-xs text-white whitespace-nowrap">Print</a>
                        @endif
                        <span x-show="pgStatus" x-cloak x-text="pgStatus" :class="pgError ? 'text-red-400' : 'text-green-400'" class="text-xs"></span>
                        <button type="button"
                                @click="act('{{ route('assignments.unlockScript', $assignment) }}', '', 'Unlock this PDF? The locked version will be replaced with an unlocked one.')"
                                class="px-2 py-1 bg-yellow-700 hover:bg-yellow-600 rounded text-xs text-white whitespace-nowrap">
                            Unlock PDF
                        </button>
                        <button type="button"
                                @click="act('{{ route('assignments.removePages', $assignment) }}', 'pages=1', 'Remove title page (page 1)?')"
                                class="px-2 py-1 bg-red-700 hover:bg-red-600 rounded text-xs text-white whitespace-nowrap">
                            Remove title page
                        </button>
                        <button type="button"
                                @click="act('{{ route('assignments.removePages', $assignment) }}', 'pages=last', 'Remove last page?')"
                                class="px-2 py-1 bg-red-700 hover:bg-red-600 rounded text-xs text-white whitespace-nowrap">
                            Remove last page
                        </button>
                        <span class="flex items-center gap-1">
                            <input type="text" x-model="pg" placeholder="pg #"
                                   class="w-14 text-xs bg-gray-700 border border-gray-600 rounded px-1.5 py-1 text-gray-200 placeholder-gray-500 focus:outline-none focus:border-indigo-400">
                            <button type="button"
                                    @click="if (pg.trim()) act('{{ route('assignments.removePages', $assignment) }}', 'pages=' + encodeURIComponent(pg), 'Remove page ' + pg + '?')"
                                    class="px-2 py-1 bg-red-700 hover:bg-red-600 rounded text-xs text-white">
                                Remove
                            </button>
                        </span>
                        <button @click="viewerOpen = false" type="button"
                                class="text-gray-400 hover:text-white text-2xl leading-none px-1">×</button>
                    </div>
                </div>
                {{-- PDF.js multi-page canvas viewer --}}
                <div x-data="pdfViewer(@js($viewUrl))"
                     @sr-load-pdf-{{ $assignment->id }}.window="if (totalPages === 0 && !loading) loadPdf()"
                     @sr-reload-pdf-{{ $assignment->id }}.window="reloadPdf()"
                     class="flex-1 flex flex-col min-h-0">
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
            </div>
        @endif
    </td>

    {{-- Accepted by --}}
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
        @if ($assignment->status === 'unassigned')
            <div class="mt-1.5" x-data="{ busy: false, err: '' }">
                <button type="button"
                        :disabled="busy"
                        @click.stop="busy = true; err = '';
                            fetch(@js(route('assignments.accept', $assignment)), {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                    'Accept': 'application/json',
                                }
                            }).then(r => r.ok ? window.dispatchEvent(new CustomEvent('assignment-accepted', { detail: @js($assignment->script_title) })) : r.json().then(d => { busy = false; err = d.message ?? 'Failed'; }))
                            .catch(() => { busy = false; err = 'Failed'; })"
                        class="inline-flex items-center px-2.5 py-1 bg-green-600 hover:bg-green-500 text-white text-[10px] font-semibold rounded transition-colors disabled:opacity-50 whitespace-nowrap"
                        x-text="busy ? 'Accepting…' : 'Accept'">
                </button>
                <span x-show="err" x-text="err" class="block text-[10px] text-red-500 mt-0.5"></span>
            </div>
        @endif
    </td>

</tr>
