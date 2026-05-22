<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-4">
            <a href="{{ route('qc.index') }}" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                QC Review — #{{ $assignment->order_number }}
                @if($assignment->rush)
                    <span class="ml-2 text-sm font-bold text-amber-600 uppercase tracking-wide">Rush</span>
                @endif
            </h2>
        </div>
    </x-slot>

    <div class="py-6" x-data="{ editOpen: false }">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">

            {{-- Flash messages --}}
            @if(session('success'))
                <div class="bg-green-50 border border-green-200 rounded-lg px-4 py-3 text-sm text-green-700">
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm text-red-700">
                    {{ session('error') }}
                </div>
            @endif

            {{-- Assignment metadata --}}
            @php
                $typeLabels = [
                    'script_coverage'   => 'Script Coverage',
                    'notes_only'        => 'Notes-Only Coverage',
                    'deep_dive'         => 'Deep-Dive Dev Notes',
                    'short'             => 'Short Coverage',
                    'budget'            => 'Budget Coverage',
                    'book'              => 'Book Coverage',
                    'coverage'          => 'Coverage',
                    'development_notes' => 'Development Notes',
                ];
                $typeDisplay = $typeLabels[$assignment->assignment_type] ?? ucfirst(str_replace('_', ' ', $assignment->assignment_type ?? '—'));
                if ($assignment->vendor === 'wd') {
                    $typeDisplay = 'WD ' . $typeDisplay;
                }
            @endphp
            <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4 grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                <div><span class="text-indigo-500 font-medium block">Script</span>{{ $assignment->script_title }}</div>
                <div><span class="text-indigo-500 font-medium block">Writer</span>{{ $assignment->writer_name }}</div>
                <div><span class="text-indigo-500 font-medium block">Pages</span>{{ $assignment->page_count }}</div>
                <div><span class="text-indigo-500 font-medium block">Type</span>{{ $typeDisplay }}</div>
                <div><span class="text-indigo-500 font-medium block">Reader</span>{{ $assignment->assignedReader?->readerProfile?->initials ?? '—' }}</div>
                <div><span class="text-indigo-500 font-medium block">Submitted</span>{{ $assignment->submitted_at?->format('M j, Y') ?? '—' }}</div>
                <div><span class="text-indigo-500 font-medium block">Rate</span>${{ number_format($assignment->pay_rate, 2) }}</div>
                <div><span class="text-indigo-500 font-medium block">Order</span><span class="font-mono">{{ $assignment->order_number }}</span></div>
            </div>

            {{-- Action bar --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 flex flex-wrap items-center gap-3">

                {{-- Edit in Google Docs --}}
                @if($assignment->drive_coverage_doc_id)
                    <button type="button" @click="editOpen = true"
                        class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-md transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Edit Coverage Doc
                    </button>
                @else
                    <span class="px-4 py-2 text-sm text-gray-400 bg-gray-50 border border-gray-200 rounded-md">No doc available</span>
                @endif

                {{-- Regenerate PDF (also submitted programmatically by the overlay) --}}
                @if($assignment->drive_coverage_doc_id)
                    <form id="regenerate-form" method="POST" action="{{ route('qc.regenerate-pdf', $assignment) }}">
                        @csrf
                        <button type="submit"
                            class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 rounded-md transition-colors">
                            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                            Generate New PDF
                        </button>
                    </form>
                @endif

                <div class="flex-1"></div>

                {{-- Approve --}}
                <form method="POST" action="{{ route('qc.approve', $assignment) }}"
                    onsubmit="return confirm('Approve #{{ $assignment->order_number }} and mark as complete?')">
                    @csrf
                    <button type="submit"
                        class="inline-flex items-center gap-1.5 px-5 py-2 text-sm font-semibold text-white bg-green-600 hover:bg-green-700 rounded-md transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Approve
                    </button>
                </form>
            </div>

            {{-- PDF preview --}}
            @if($assignment->drive_coverage_pdf_id)
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="flex items-center justify-between px-4 py-2 border-b border-gray-100 bg-gray-50">
                        <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">Draft PDF</span>
                        <a href="https://drive.google.com/file/d/{{ $assignment->drive_coverage_pdf_id }}/view"
                            target="_blank"
                            class="text-xs text-indigo-600 hover:text-indigo-800">
                            Open in Drive ↗
                        </a>
                    </div>
                    <iframe
                        src="https://drive.google.com/file/d/{{ $assignment->drive_coverage_pdf_id }}/preview"
                        class="w-full"
                        style="height: 900px;"
                        allow="autoplay">
                    </iframe>
                </div>
            @elseif($assignment->drive_coverage_doc_id)
                <div class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 text-sm text-amber-700">
                    No PDF generated yet — click <strong>Generate New PDF</strong> above.
                </div>
            @else
                <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm text-red-700">
                    No Google Doc found for this assignment. The coverage doc may have failed to generate — check the logs.
                </div>
            @endif

        </div>

        {{-- Full-screen Google Docs editing overlay --}}
        @if($assignment->drive_coverage_doc_id)
            <div x-show="editOpen" x-cloak
                class="fixed inset-0 z-50 flex flex-col bg-white">

                {{-- Overlay header --}}
                <div class="flex items-center justify-between px-5 py-3 bg-indigo-700 text-white shrink-0">
                    <span class="font-semibold text-sm truncate pr-4">
                        Editing: {{ $assignment->script_title }} — #{{ $assignment->order_number }}
                    </span>
                    <div class="flex items-center gap-3 shrink-0">
                        <button type="button"
                            @click="editOpen = false"
                            class="text-sm text-indigo-200 hover:text-white transition-colors">
                            Cancel
                        </button>
                        <button type="button"
                            @click="editOpen = false; document.getElementById('regenerate-form').submit()"
                            class="inline-flex items-center gap-1.5 px-4 py-1.5 text-sm font-semibold bg-green-500 hover:bg-green-400 text-white rounded-md transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Done Editing — Generate New PDF
                        </button>
                    </div>
                </div>

                {{-- Docs iframe --}}
                <iframe
                    src="https://docs.google.com/document/d/{{ $assignment->drive_coverage_doc_id }}/edit"
                    class="flex-1 w-full border-0">
                </iframe>
            </div>
        @endif

    </div>
</x-app-layout>
