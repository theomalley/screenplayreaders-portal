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
                                <th class="px-4 py-3 text-left">Script / Writer</th>
                                <th class="px-4 py-3 text-left">Type</th>
                                <th class="px-4 py-3 text-left">Completed</th>
                                <th class="px-4 py-3 text-left">Script</th>
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
                                    <td class="px-4 py-3">
                                        <a href="{{ route('assignments.show', $first) }}"
                                           class="font-medium text-gray-800 hover:text-indigo-600">{{ $first->script_title }}</a>
                                        <div class="text-gray-400 text-xs">{{ $first->writer_name }}</div>
                                        @if($scriptId)
                                            <div class="flex items-center gap-2 mt-1">
                                                <a href="{{ route('assignments.streamScript', $first) }}" target="_blank"
                                                   class="text-xs text-indigo-500 hover:text-indigo-700">View Script</a>
                                                <span class="text-gray-300 text-xs">·</span>
                                                <a href="https://drive.google.com/uc?export=download&id={{ $scriptId }}" target="_blank"
                                                   class="text-xs text-indigo-500 hover:text-indigo-700">Download</a>
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $typeLabel }}</td>
                                    <td class="px-4 py-3 text-gray-500 whitespace-nowrap tabular-nums">
                                        {{ $latestDone ? \Carbon\Carbon::createFromTimestamp($latestDone)->setTimezone('America/Los_Angeles')->format('M j, Y g:ia') : '—' }}
                                    </td>

                                    {{-- Script link --}}
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        @if($scriptId)
                                            <a href="https://drive.google.com/file/d/{{ $scriptId }}/view"
                                               target="_blank"
                                               class="inline-flex items-center gap-1 text-xs font-medium text-indigo-600 hover:text-indigo-800">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                </svg>
                                                Script
                                            </a>
                                        @else
                                            <span class="text-gray-300 text-xs">—</span>
                                        @endif
                                    </td>

                                    {{-- Coverage links — one per completed reader --}}
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            @foreach($group as $assignment)
                                                @php
                                                    $initials = $assignment->assignedReader?->readerProfile?->initials ?? '?';
                                                    $pdfId    = $assignment->drive_coverage_pdf_id;
                                                    $docId    = $assignment->drive_coverage_doc_id;
                                                @endphp
                                                @if($pdfId)
                                                    <a href="https://drive.google.com/file/d/{{ $pdfId }}/view"
                                                       target="_blank"
                                                       title="{{ $initials }} — Coverage PDF"
                                                       class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-green-50 text-green-700 border border-green-200 hover:bg-green-100">
                                                        {{ $initials }}
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                                        </svg>
                                                    </a>
                                                @elseif($docId)
                                                    <a href="https://docs.google.com/document/d/{{ $docId }}/view"
                                                       target="_blank"
                                                       title="{{ $initials }} — Coverage Doc (no PDF)"
                                                       class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-amber-50 text-amber-700 border border-amber-200 hover:bg-amber-100">
                                                        {{ $initials }}
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                                        </svg>
                                                    </a>
                                                @else
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-400 border border-gray-200"
                                                          title="{{ $initials }} — No coverage doc">
                                                        {{ $initials }}
                                                    </span>
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
