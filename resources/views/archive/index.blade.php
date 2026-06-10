<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Assignment Archive</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

            @if($groups->isEmpty())
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 px-6 py-12 text-center text-gray-400 text-sm">
                    No completed assignments yet.
                </div>
            @else
                <div x-data="{ search: '' }">
                <div class="flex items-center gap-2 mb-3">
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
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs font-medium text-gray-500 uppercase tracking-wide">
                            <tr>
                                <th class="px-4 py-3 text-left">Order</th>
                                <th class="px-4 py-3 text-left">Title / Writer</th>
                                <th class="px-4 py-3 text-left">Type</th>
                                <th class="px-4 py-3 text-left">Completed</th>
                                <th class="px-4 py-3 text-left">Turnaround</th>
                                <th class="px-4 py-3 text-left">Coverage</th>
                                <th class="px-4 py-3 text-center">GoBack</th>
                                <th class="px-4 py-3 text-right">Followup</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($groups as $orderNumber => $group)
                                @php
                                    $first       = $group->first();
                                    $latestDone  = $group->max(fn($a) => $a->completed_at?->timestamp ?? 0);
                                    $scriptId    = $group->firstWhere(fn($a) => $a->hasCloudScript())?->drive_script_file_id;
                                    $draftSent   = $group->some(fn($a) => !empty($a->helpscout_draft_sent_at));

                                    $hsSentAt    = $group->filter(fn($a) => !empty($a->helpscout_draft_sent_at))->first()?->helpscout_draft_sent_at;
                                    $minCreated  = $group->min(fn($a) => $a->created_at?->timestamp ?? PHP_INT_MAX);
                                    if ($hsSentAt && $minCreated !== PHP_INT_MAX) {
                                        $taDiff  = \Carbon\Carbon::createFromTimestamp($minCreated)->diff($hsSentAt);
                                        $taStr   = $taDiff->days >= 1
                                            ? ($taDiff->days . 'd ' . $taDiff->h . 'h')
                                            : ($taDiff->h >= 1 ? ($taDiff->h . 'h ' . $taDiff->i . 'm') : (max(0, $taDiff->i) . 'm'));
                                        $taTitle = \Carbon\Carbon::createFromTimestamp($minCreated)->format('M j g:ia') . ' → ' . $hsSentAt->format('M j g:ia');
                                    } else {
                                        $taStr   = null;
                                        $taTitle = null;
                                    }

                                    $typeLabel = match($first->assignment_type) {
                                        'script_coverage'   => 'Script Coverage',
                                        'notes_only'        => 'Notes-Only',
                                        'deep_dive'         => 'Deep-Dive',
                                        'short'             => 'Short',
                                        'budget'            => 'Budget',
                                        'book'              => 'Book',
                                        'coverage'          => 'Coverage',
                                        'development_notes' => 'Dev Notes',
                                        default             => $first->assignment_type ?? '—',
                                    };
                                    if ($first->vendor === 'wd') {
                                        $typeLabel = 'WD ' . $typeLabel;
                                    }
                                    $searchStr = strtolower(implode(' ', array_filter(array_merge(
                                        [$orderNumber, $first->script_title, $first->writer_name],
                                        $group->map(fn($a) => $a->assignedReader?->readerProfile?->displayName() ?? $a->assignedReader?->name)->filter()->values()->toArray()
                                    ))));
                                @endphp
                                @php
                                    $archiveHsId = $group->first(fn($a) => !empty($a->helpscout_ticket_number))?->helpscout_ticket_number
                                        ?: $first->helpscoutConversation?->helpscout_conversation_id;
                                @endphp
                                <tr class="hover:bg-gray-50 align-top cursor-pointer"
                                    x-show="!search || '{{ $searchStr }}'.includes(search.toLowerCase())"
                                    data-search="{{ $searchStr }}"
                                    @click="if (!$event.target.closest('a,button,form')) window.location='{{ route('assignments.edit', $first) }}'">
                                    <td class="px-4 py-3 font-mono text-gray-700 whitespace-nowrap">
                                        <a href="{{ route('assignments.show', $first) }}"
                                           class="hover:text-indigo-600">{{ $orderNumber }}</a>
                                        @if ($archiveHsId)
                                            <div class="mt-1">
                                                <a href="https://secure.helpscout.net/conversation/{{ $archiveHsId }}/"
                                                   target="_blank" rel="noopener noreferrer"
                                                   title="Open in HelpScout"
                                                   class="inline-flex text-gray-400 hover:text-indigo-600">
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3.5 h-3.5">
                                                        <path fill-rule="evenodd" d="M2 5a2 2 0 012-2h12a2 2 0 012 2v7a2 2 0 01-2 2H6l-4 4V5z" clip-rule="evenodd"/>
                                                    </svg>
                                                </a>
                                            </div>
                                        @endif
                                    </td>
                                    @php $viewUrl = $scriptId ? route('assignments.streamScript', $first) : null; @endphp
                                    <td class="px-4 py-3" x-data="pdfViewer(@js($viewUrl ?? ''))">
                                        @if($viewUrl)
                                            <button @click="openViewer()" type="button"
                                                    class="font-medium text-gray-800 hover:text-indigo-600 text-left leading-snug max-w-xs block">📄 {{ $first->script_title }}</button>
                                            <div x-show="open" x-cloak x-ref="modal"
                                                 @keydown.escape.window="open = false"
                                                 tabindex="-1"
                                                 class="fixed inset-0 z-50 flex flex-col bg-black/80">
                                                <div class="flex items-center justify-between px-4 py-2 bg-gray-900 shrink-0 gap-2 flex-wrap">
                                                    <span class="text-sm text-gray-200 font-medium truncate min-w-0">{{ $first->drive_script_filename ?? $first->script_title }}</span>
                                                    <div class="flex items-center gap-2 shrink-0">
                                                        @if (\App\Support\Permission::check('script.download'))
                                                            <a href="{{ route('assignments.downloadScript', $first) }}"
                                                               class="px-2 py-1 bg-gray-700 hover:bg-gray-600 rounded text-xs text-white whitespace-nowrap">Download</a>
                                                        @endif
                                                        @if (\App\Support\Permission::check('script.print'))
                                                            <a href="{{ $viewUrl }}" target="_blank" rel="noopener"
                                                               class="px-2 py-1 bg-gray-700 hover:bg-gray-600 rounded text-xs text-white whitespace-nowrap">Print</a>
                                                        @endif
                                                        <span x-show="pgStatus" x-cloak x-text="pgStatus" :class="pgError ? 'text-red-400' : 'text-green-400'" class="text-xs"></span>
                                                        <button type="button"
                                                                @click="pdfAction('{{ route('assignments.unlockScript', $first) }}', '', 'Unlock this PDF? The locked version will be replaced with an unlocked one.')"
                                                                class="px-2 py-1 bg-yellow-700 hover:bg-yellow-600 rounded text-xs text-white whitespace-nowrap">
                                                            Unlock PDF
                                                        </button>
                                                        <button type="button"
                                                                @click="pdfAction('{{ route('assignments.removePages', $first) }}', 'pages=1', 'Remove title page (page 1)?')"
                                                                class="px-2 py-1 bg-red-700 hover:bg-red-600 rounded text-xs text-white whitespace-nowrap">
                                                            Remove title page
                                                        </button>
                                                        <button type="button"
                                                                @click="pdfAction('{{ route('assignments.removePages', $first) }}', 'pages=last', 'Remove last page?')"
                                                                class="px-2 py-1 bg-red-700 hover:bg-red-600 rounded text-xs text-white whitespace-nowrap">
                                                            Remove last page
                                                        </button>
                                                        <span class="flex items-center gap-1">
                                                            <input type="text" x-model="pg" placeholder="pg #"
                                                                   class="w-14 text-xs bg-gray-700 border border-gray-600 rounded px-1.5 py-1 text-gray-200 placeholder-gray-500 focus:outline-none focus:border-indigo-400">
                                                            <button type="button"
                                                                    @click="if (pg.trim()) pdfAction('{{ route('assignments.removePages', $first) }}', 'pages=' + encodeURIComponent(pg), 'Remove page ' + pg + '?')"
                                                                    class="px-2 py-1 bg-red-700 hover:bg-red-600 rounded text-xs text-white">
                                                                Remove
                                                            </button>
                                                        </span>
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
                                            <div class="font-medium text-gray-800 max-w-xs">{{ $first->script_title }}</div>
                                        @endif
                                        <div class="text-gray-400 text-xs">{{ $first->writer_name }}</div>
                                        <a href="{{ route('assignments.show', $first) }}"
                                           class="text-xs text-indigo-500 hover:text-indigo-700 mt-0.5 inline-block">Details →</a>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $typeLabel }}</td>
                                    <td class="px-4 py-3 text-gray-500 whitespace-nowrap tabular-nums">
                                        {{ $latestDone ? \Carbon\Carbon::createFromTimestamp($latestDone)->setTimezone('America/Los_Angeles')->format('D M j, Y g:ia') : '—' }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap tabular-nums">
                                        @if($taStr)
                                            <span class="text-gray-700" title="{{ $taTitle }}">{{ $taStr }}</span>
                                        @else
                                            <span class="text-gray-300">—</span>
                                        @endif
                                    </td>

                                    {{-- Coverage links — one per completed reader --}}
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            @foreach($group as $assignment)
                                                @php
                                                    $initials            = $assignment->assignedReader?->readerProfile?->initials ?? '?';
                                                    $pdfId               = $assignment->drive_coverage_pdf_id;
                                                    $docId               = $assignment->drive_coverage_doc_id;
                                                    $coverageStreamUrl   = $pdfId ? route('assignments.streamCoverage', $assignment) : null;
                                                    $coverageDownloadUrl = $pdfId ? "https://drive.google.com/uc?export=download&id={$pdfId}" : null;
                                                @endphp
                                                @if($pdfId || $docId)
                                                    <div x-data="{ pdfOpen: false, editOpen: false, textOpen: false }">

                                                        {{-- Badge button --}}
                                                        <button @click="pdfOpen = true" type="button"
                                                                title="{{ $initials }} — {{ $pdfId ? 'Coverage PDF' : 'Coverage Doc (no PDF yet)' }}"
                                                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium border hover:opacity-80 {{ $pdfId ? 'bg-green-50 text-green-700 border-green-200' : 'bg-amber-50 text-amber-700 border-amber-200' }}">
                                                            {{ $initials }}
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                                            </svg>
                                                        </button>
                                                        @if($assignment->coverageSubmission)
                                                            <button @click="textOpen = true" type="button"
                                                                    class="text-[10px] text-indigo-500 hover:text-indigo-700 underline leading-none">txt</button>
                                                        @endif

                                                        {{-- Coverage PDF modal --}}
                                                        <div x-show="pdfOpen" x-cloak
                                                             @keydown.escape.window="editOpen ? (editOpen = false) : (pdfOpen = false)"
                                                             tabindex="-1"
                                                             x-effect="if (pdfOpen && !editOpen) $nextTick(() => $el.focus())"
                                                             class="fixed inset-0 z-50 flex flex-col bg-black/80">
                                                            <div class="flex items-center justify-between px-4 py-2 bg-gray-900 shrink-0 gap-2 flex-wrap">
                                                                <span class="text-sm text-gray-200 font-medium truncate min-w-0">
                                                                    {{ $first->script_title }} — {{ $initials }}
                                                                </span>
                                                                <div class="flex items-center gap-2 shrink-0">
                                                                    @if($docId)
                                                                        <button @click="editOpen = true" type="button"
                                                                                class="inline-flex items-center gap-1 px-2.5 py-1 bg-indigo-600 hover:bg-indigo-500 text-white rounded text-xs font-medium whitespace-nowrap">
                                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                                            </svg>
                                                                            Edit Doc
                                                                        </button>
                                                                        <form x-ref="regenForm" method="POST" action="{{ route('qc.regenerate-pdf', $assignment) }}">
                                                                            @csrf
                                                                            <button type="submit"
                                                                                    class="inline-flex items-center gap-1 px-2.5 py-1 bg-gray-700 hover:bg-gray-600 text-white rounded text-xs font-medium whitespace-nowrap">
                                                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                                                                </svg>
                                                                                Regen PDF
                                                                            </button>
                                                                        </form>
                                                                    @endif
                                                                    @if($pdfId)
                                                                        <a href="{{ $coverageDownloadUrl }}" target="_blank"
                                                                           class="inline-flex items-center gap-1 px-2.5 py-1 bg-gray-700 hover:bg-gray-600 text-white rounded text-xs font-medium whitespace-nowrap">
                                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                                                            </svg>
                                                                            Download
                                                                        </a>
                                                                    @endif
                                                                    <button @click="pdfOpen = false" type="button"
                                                                            class="text-gray-400 hover:text-white text-2xl leading-none px-1">×</button>
                                                                </div>
                                                            </div>
                                                            @if($pdfId)
                                                                <iframe :src="pdfOpen ? @js($coverageStreamUrl) : ''"
                                                                        class="flex-1 w-full border-0"
                                                                        allowfullscreen></iframe>
                                                            @else
                                                                <div class="flex-1 flex items-center justify-center flex-col gap-2 text-center px-4">
                                                                    <p class="text-gray-400 text-sm">No PDF generated yet.</p>
                                                                    <p class="text-gray-500 text-xs">Edit the doc, then click <strong class="text-gray-300">Regen PDF</strong> to generate one.</p>
                                                                </div>
                                                            @endif
                                                        </div>

                                                        {{-- Google Docs editing overlay (above PDF modal) --}}
                                                        @if($docId)
                                                            <div x-show="editOpen" x-cloak
                                                                 class="fixed inset-0 z-[60] flex flex-col bg-white">
                                                                <div class="flex items-center justify-between px-5 py-3 bg-indigo-700 text-white shrink-0 gap-3 flex-wrap">
                                                                    <span class="font-semibold text-sm truncate min-w-0">
                                                                        Editing: {{ $first->script_title }} — {{ $initials }}
                                                                    </span>
                                                                    <div class="flex items-center gap-3 shrink-0">
                                                                        <button @click="editOpen = false" type="button"
                                                                                class="text-sm text-indigo-200 hover:text-white transition-colors">
                                                                            Cancel
                                                                        </button>
                                                                        <button @click="editOpen = false; $refs.regenForm.submit()" type="button"
                                                                                class="inline-flex items-center gap-1.5 px-4 py-1.5 text-sm font-semibold bg-green-500 hover:bg-green-400 text-white rounded-md transition-colors">
                                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                                            </svg>
                                                                            Done Editing — Generate New PDF
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                                <iframe src="https://docs.google.com/document/d/{{ $docId }}/edit"
                                                                        class="flex-1 w-full border-0"></iframe>
                                                            </div>
                                                        @endif

                                                        {{-- Coverage text modal --}}
                                                        @if($assignment->coverageSubmission)
                                                            <div x-show="textOpen" x-cloak
                                                                 @keydown.escape.window="textOpen = false"
                                                                 class="fixed inset-0 z-[70] flex flex-col bg-black/80">
                                                                <div class="flex items-center justify-between px-4 py-2 bg-gray-900 shrink-0 gap-2 flex-wrap">
                                                                    <span class="text-sm text-gray-200 font-medium truncate min-w-0">
                                                                        {{ $first->script_title }} — {{ $initials }} — Coverage Text
                                                                    </span>
                                                                    <button @click="textOpen = false" type="button"
                                                                            class="text-gray-400 hover:text-white text-2xl leading-none px-1">×</button>
                                                                </div>
                                                                <iframe :src="textOpen ? @js(route('coverage.preview', $assignment)) : ''"
                                                                        x-ref="txtFrameA{{ $assignment->id }}"
                                                                        @load="try { $refs['txtFrameA{{ $assignment->id }}'].contentWindow.addEventListener('keydown', e => { if (e.key === 'Escape') textOpen = false; }); } catch(e) {}"
                                                                        class="flex-1 w-full border-0 bg-white"></iframe>
                                                            </div>
                                                        @endif

                                                    </div>
                                                @else
                                                    @if($assignment->coverageSubmission)
                                                        <div x-data="{ textOpen: false }">
                                                            <button @click="textOpen = true" type="button"
                                                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium border bg-indigo-50 text-indigo-600 border-indigo-200 hover:opacity-80">
                                                                {{ $initials }}
                                                                <span class="text-[9px]">txt</span>
                                                            </button>
                                                            <div x-show="textOpen" x-cloak
                                                                 @keydown.escape.window="textOpen = false"
                                                                 class="fixed inset-0 z-[70] flex flex-col bg-black/80">
                                                                <div class="flex items-center justify-between px-4 py-2 bg-gray-900 shrink-0 gap-2 flex-wrap">
                                                                    <span class="text-sm text-gray-200 font-medium truncate min-w-0">
                                                                        {{ $first->script_title }} — {{ $initials }} — Coverage Text
                                                                    </span>
                                                                    <button @click="textOpen = false" type="button"
                                                                            class="text-gray-400 hover:text-white text-2xl leading-none px-1">×</button>
                                                                </div>
                                                                <iframe :src="textOpen ? @js(route('coverage.preview', $assignment)) : ''"
                                                                        x-ref="txtFrameB{{ $assignment->id }}"
                                                                        @load="try { $refs['txtFrameB{{ $assignment->id }}'].contentWindow.addEventListener('keydown', e => { if (e.key === 'Escape') textOpen = false; }); } catch(e) {}"
                                                                        class="flex-1 w-full border-0 bg-white"></iframe>
                                                            </div>
                                                        </div>
                                                    @else
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-400 border border-gray-200"
                                                              title="{{ $initials }} — No coverage doc">
                                                            {{ $initials }}
                                                        </span>
                                                    @endif
                                                @endif
                                            @endforeach
                                        </div>
                                    </td>

                                    {{-- GoBack draft status --}}
                                    <td class="px-4 py-3 text-center">
                                        @if($draftSent)
                                            <svg class="w-5 h-5 text-green-500 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24" title="HelpScout draft created">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                            </svg>
                                        @else
                                            <span class="text-gray-300 text-xs">—</span>
                                        @endif
                                    </td>

                                    {{-- Send / Reset Followups --}}
                                    @php
                                        $readersForFollowup = $group->filter(fn($a) => $a->assigned_reader_id !== null)->values();
                                        $hasExistingToken   = isset($ordersWithSubmissions[$orderNumber]);
                                        $isMulti            = $readersForFollowup->count() > 1;
                                        $singleInitials     = ! $isMulti
                                            ? ($readersForFollowup->first()?->assignedReader?->readerProfile?->initials
                                                ?? ($readersForFollowup->first()?->assignedReader ? strtoupper(substr($readersForFollowup->first()->assignedReader->name, 0, 2)) : '??'))
                                            : '';
                                    @endphp
                                    <td class="px-4 py-3 text-right">
                                        <div x-data="{
                                                hasToken: {{ $hasExistingToken ? 'true' : 'false' }},
                                                flash: '',
                                                async act() {
                                                    const r = await fetch('{{ route('assignments.followup-reset', $first) }}', {
                                                        method: 'POST',
                                                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' }
                                                    });
                                                    const d = await r.json();
                                                    navigator.clipboard.writeText(d.url);
                                                    this.flash = this.hasToken ? 'reset' : 'copied';
                                                    this.hasToken = !this.hasToken;
                                                    setTimeout(() => this.flash = '', 2000);
                                                }
                                            }">
                                            <button type="button" @click="act()"
                                                    class="text-[10px] text-indigo-400 hover:text-indigo-600 transition whitespace-nowrap">
                                                @if ($isMulti)
                                                    <span x-show="flash === 'reset'" class="text-green-600">Followups reset for all</span>
                                                    <span x-show="flash === 'copied'" class="text-green-600">✓ Copied</span>
                                                    <span x-show="flash === '' && hasToken">Reset Followups (all)</span>
                                                    <span x-show="flash === '' && !hasToken">Send Followups URL (all)</span>
                                                @else
                                                    <span x-show="flash === 'reset'" class="text-green-600">Followups reset for {{ $singleInitials }}</span>
                                                    <span x-show="flash === 'copied'" class="text-green-600">✓ Copied</span>
                                                    <span x-show="flash === '' && hasToken">Reset Followups for {{ $singleInitials }}</span>
                                                    <span x-show="flash === '' && !hasToken">Send Followup URL</span>
                                                @endif
                                            </button>
                                        </div>
                                        @if (isset($ordersWithSubmissions[$orderNumber]))
                                        <div class="mt-1.5">
                                            <a href="{{ route('followups.history', $orderNumber) }}"
                                               class="text-[10px] text-gray-400 hover:text-indigo-600 transition">
                                                Followup History
                                            </a>
                                        </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                </div> {{-- /x-data search wrapper --}}
            @endif

        </div>
    </div>

    @push('scripts')
    <script>
    document.addEventListener('alpine:init', () => {
        if (Alpine._data?.pdfViewer) return;

        async function ensurePdfJs() {
            if (window.pdfjsLib) return;
            await new Promise((resolve, reject) => {
                const s = document.createElement('script');
                s.src = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.min.js';
                s.onload = () => {
                    window.pdfjsLib.GlobalWorkerOptions.workerSrc =
                        'https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.worker.min.js';
                    resolve();
                };
                s.onerror = () => reject(new Error('PDF.js failed to load'));
                document.head.appendChild(s);
            });
        }

        Alpine.data('pdfViewer', (url) => {
            let pdfDoc = null;
            let pages  = [];

            return {
                open: false,
                url: url,
                currentPage: 0,
                totalPages: 0,
                loading: false,
                pg: '',
                pgStatus: '',
                pgError: false,

                async openViewer() {
                    this.open = true;
                    await this.$nextTick();
                    this.$refs.modal?.focus();
                    if (!pdfDoc) await this.loadPdf();
                },

                async loadPdf() {
                    this.loading = true;
                    try {
                        await ensurePdfJs();
                        pdfDoc = await pdfjsLib.getDocument({
                            url: this.url,
                            withCredentials: true,
                        }).promise;
                        this.totalPages = pdfDoc.numPages;
                        await this.renderAllPages();
                    } catch (e) {
                        console.error('PDF load error:', e);
                    } finally {
                        this.loading = false;
                    }
                },

                async renderAllPages() {
                    const wrap = this.$refs.canvasWrap;
                    const dpr  = window.devicePixelRatio || 1;
                    pages = [];
                    const maxW = Math.max(wrap.clientWidth - 48, 200);
                    for (let i = 1; i <= this.totalPages; i++) {
                        this.currentPage = i;
                        const page = await pdfDoc.getPage(i);
                        const base  = page.getViewport({ scale: 1 });
                        const scale = Math.min(maxW / base.width, 2.0);
                        const vp    = page.getViewport({ scale: scale * dpr });
                        const canvas = document.createElement('canvas');
                        canvas.width  = vp.width;
                        canvas.height = vp.height;
                        canvas.style.width  = (vp.width  / dpr) + 'px';
                        canvas.style.height = (vp.height / dpr) + 'px';
                        canvas.className = 'shadow-2xl shrink-0';
                        wrap.appendChild(canvas);
                        pages.push(canvas);
                        await page.render({ canvasContext: canvas.getContext('2d'), viewport: vp }).promise;
                    }
                },

                scrollToPage(num) {
                    const n = Math.max(1, Math.min(parseInt(num) || 1, this.totalPages));
                    if (pages[n - 1]) pages[n - 1].scrollIntoView({ behavior: 'smooth' });
                },

                async reloadPdf() {
                    const wrap = this.$refs.canvasWrap;
                    if (wrap) for (const c of [...wrap.querySelectorAll('canvas')]) c.remove();
                    pages = [];
                    pdfDoc = null;
                    this.totalPages = 0;
                    this.currentPage = 0;
                    this.url = this.url.split('?')[0] + '?t=' + Date.now();
                    await this.loadPdf();
                },

                async pdfAction(url, body, confirmMsg) {
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
                            await this.reloadPdf();
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
            };
        });
    });
    </script>
    @endpush
</x-app-layout>
