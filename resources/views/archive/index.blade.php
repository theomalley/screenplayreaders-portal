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
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
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
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($groups as $orderNumber => $group)
                                @php
                                    $first       = $group->first();
                                    $latestDone  = $group->max(fn($a) => $a->completed_at?->timestamp ?? 0);
                                    $scriptId    = $group->firstWhere(fn($a) => !empty($a->drive_script_file_id))?->drive_script_file_id;
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
                                @endphp
                                <tr class="hover:bg-gray-50 align-top">
                                    <td class="px-4 py-3 font-mono text-gray-700 whitespace-nowrap">
                                        <a href="{{ route('assignments.show', $first) }}"
                                           class="hover:text-indigo-600">{{ $orderNumber }}</a>
                                    </td>
                                    @php $viewUrl = $scriptId ? route('assignments.streamScript', $first) : null; @endphp
                                    <td class="px-4 py-3" x-data="{ open: false }">
                                        @if($viewUrl)
                                            <button @click="open = true" type="button"
                                                    class="font-medium text-gray-800 hover:text-indigo-600 text-left leading-snug">{{ $first->script_title }}</button>
                                            <div x-show="open" x-cloak
                                                 @keydown.escape.window="open = false"
                                                 tabindex="-1"
                                                 x-effect="if (open) $nextTick(() => $el.focus())"
                                                 class="fixed inset-0 z-50 flex flex-col bg-black/80">
                                                <div class="flex items-center justify-between px-4 py-2 bg-gray-900 shrink-0 gap-2 flex-wrap">
                                                    <span class="text-sm text-gray-200 font-medium truncate min-w-0">{{ $first->drive_script_filename ?? $first->script_title }}</span>
                                                    <div class="flex items-center gap-2 shrink-0">
                                                        <form method="POST" action="{{ route('assignments.removePages', $first) }}"
                                                              onsubmit="return confirm('Remove title page (page 1)?')">
                                                            @csrf
                                                            <input type="hidden" name="pages" value="1">
                                                            <button type="submit"
                                                                    class="px-2 py-1 bg-red-700 hover:bg-red-600 rounded text-xs text-white whitespace-nowrap">
                                                                Remove title page
                                                            </button>
                                                        </form>
                                                        <form method="POST" action="{{ route('assignments.removePages', $first) }}"
                                                              onsubmit="return confirm('Remove last page?')">
                                                            @csrf
                                                            <input type="hidden" name="pages" value="last">
                                                            <button type="submit"
                                                                    class="px-2 py-1 bg-red-700 hover:bg-red-600 rounded text-xs text-white whitespace-nowrap">
                                                                Remove last page
                                                            </button>
                                                        </form>
                                                        <form method="POST" action="{{ route('assignments.removePages', $first) }}"
                                                              class="flex items-center gap-1"
                                                              x-data="{ pg: '' }"
                                                              @submit.prevent="if (pg.trim()) { if (confirm('Remove page ' + pg + '?')) $el.submit(); }">
                                                            @csrf
                                                            <input type="text" name="pages" x-model="pg" placeholder="pg #"
                                                                   class="w-14 text-xs bg-gray-700 border border-gray-600 rounded px-1.5 py-1 text-gray-200 placeholder-gray-500 focus:outline-none focus:border-indigo-400">
                                                            <button type="submit"
                                                                    class="px-2 py-1 bg-red-700 hover:bg-red-600 rounded text-xs text-white">
                                                                Remove
                                                            </button>
                                                        </form>
                                                        <button @click="open = false" type="button"
                                                                class="text-gray-400 hover:text-white text-2xl leading-none px-1">×</button>
                                                    </div>
                                                </div>
                                                <iframe :src="open ? @js($viewUrl) : ''"
                                                        class="flex-1 w-full border-0"
                                                        allowfullscreen></iframe>
                                            </div>
                                        @else
                                            <div class="font-medium text-gray-800">{{ $first->script_title }}</div>
                                        @endif
                                        <div class="text-gray-400 text-xs">{{ $first->writer_name }}</div>
                                        <a href="{{ route('assignments.show', $first) }}"
                                           class="text-xs text-indigo-500 hover:text-indigo-700 mt-0.5 inline-block">Details →</a>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $typeLabel }}</td>
                                    <td class="px-4 py-3 text-gray-500 whitespace-nowrap tabular-nums">
                                        {{ $latestDone ? \Carbon\Carbon::createFromTimestamp($latestDone)->setTimezone('America/Los_Angeles')->format('M j, Y g:ia') : '—' }}
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
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
