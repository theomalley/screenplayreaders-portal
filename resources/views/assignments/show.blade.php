<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ $assignment->script_title }}
                </h2>
                <p class="text-sm text-gray-500 mt-0.5">
                    {{ $assignment->writer_name }} &middot; {{ $assignment->page_count }}pp
                    @if ($assignment->rush)
                        &middot; <span class="text-amber-600 font-medium">Rush</span>
                    @endif
                </p>
            </div>
            <a href="{{ route('assignments.index') }}"
               class="text-sm text-gray-500 hover:text-gray-700">&larr; Assignments</a>
        </div>
    </x-slot>

    @include('partials.pdf-text-layer-styles')

    @php
        $batchLineItem = null;
        if (auth()->user()?->isAdminOrEditor()) {
            $batchLineItem = \App\Models\InvoiceLineItem::where('assignment_id', $assignment->id)
                ->whereHas('invoice', fn ($q) => $q->where('status', 'draft')
                    ->whereNull('stripe_invoice_id')
                    ->whereNull('google_doc_id'))
                ->with('invoice.client')
                ->first();
        }
    @endphp

    @if ($isMultiReader)
    {{-- ===== MULTI-READER N-UP LAYOUT ===== --}}
    <div class="py-6 px-4 sm:px-6 lg:px-8">

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
        @if (session('warning'))
            <div class="mb-4 px-4 py-3 bg-amber-50 border border-amber-200 text-amber-800 rounded-md text-sm">
                {{ session('warning') }}
            </div>
        @endif

        @if ($batchLineItem)
            <div class="mb-4 px-4 py-3 bg-amber-50 border border-amber-200 rounded-md text-sm text-amber-800 flex items-center justify-between">
                <span>Added to <a href="{{ route('clients.show', $batchLineItem->invoice->client) }}" class="font-medium underline">{{ $batchLineItem->invoice->client->name }}</a>'s open weekly invoice #{{ $batchLineItem->invoice->invoice_number }} — ${{ number_format((float) $batchLineItem->amount, 2) }}</span>
                <a href="{{ route('clients.show', $batchLineItem->invoice->client) }}" class="ml-4 text-amber-700 hover:text-amber-900 font-medium text-xs whitespace-nowrap">View Invoice →</a>
            </div>
        @endif

        {{-- Shared info + order-level actions --}}
        <div class="bg-white rounded-lg shadow px-5 py-4 text-sm text-gray-700 space-y-2 mb-5">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-x-6 gap-y-2">
                <div><span class="font-medium">Order #</span> {{ $assignment->order_number }}</div>
                <div><span class="font-medium">Status</span> {{ ucfirst(str_replace('_', ' ', $assignment->status)) }}</div>
                <div><span class="font-medium">Type</span> {{ ucfirst(str_replace('_', ' ', $assignment->assignment_type)) }}</div>
                <div><span class="font-medium">Vendor</span> {{ strtoupper($assignment->vendor) }}</div>
            </div>
            @if ($assignment->notes)
                <div class="pt-2 border-t border-gray-100">
                    <span class="font-medium">Notes</span>
                    <p class="mt-1 text-gray-600">{{ $assignment->notes }}</p>
                </div>
            @endif

            <div class="pt-3 border-t border-gray-100 flex gap-3 flex-wrap items-center">
                <a href="{{ route('assignments.edit', $assignment) }}"
                   class="px-4 py-2 bg-gray-800 text-white text-sm font-medium rounded hover:bg-gray-700">
                    Edit Assignment
                </a>

                <form method="POST" action="{{ route('qc.draft-all', $assignment) }}"
                      onsubmit="return confirm('Create a HelpScout draft with all available coverage PDFs for order #{{ $assignment->order_number }}?')">
                    @csrf
                    <button type="submit"
                            class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded hover:bg-indigo-700">
                        Create HelpScout GoBack Draft
                    </button>
                </form>
            </div>
        </div>

        {{-- N-up coverage grid --}}
        <div class="grid gap-4" style="grid-template-columns: repeat({{ $siblings->count() }}, minmax(0, 1fr))">
            @foreach ($siblings as $sibling)
                @php
                    $sibInitials = $sibling->assignedReader?->readerProfile?->initials ?? '?';
                    $sibViewLink = $sibling->drive_coverage_pdf_id
                        ? route('assignments.streamCoverage', $sibling)
                        : null;
                    $sibDlUrl = $sibling->drive_coverage_pdf_id
                        ? 'https://drive.google.com/uc?export=download&id=' . $sibling->drive_coverage_pdf_id
                        : null;
                    $sibFormId = 'regenerate-form-' . $sibling->id;
                @endphp
                <div x-data="{ editOpen: false }" class="bg-white rounded-lg shadow overflow-hidden flex flex-col">

                    {{-- Card header --}}
                    <div class="px-4 py-3 border-b border-gray-100 bg-gray-50 flex items-center justify-between shrink-0">
                        <span class="text-sm font-semibold text-gray-700">{{ $sibInitials }}</span>
                        <div class="flex items-center gap-3">
                            @if ($sibling->drive_coverage_doc_id)
                                <button type="button" @click="editOpen = true"
                                        class="text-xs font-medium text-indigo-600 hover:text-indigo-800">
                                    Edit Doc
                                </button>
                                <form id="{{ $sibFormId }}" method="POST"
                                      action="{{ route('qc.regenerate-pdf', $sibling) }}" class="inline">
                                    @csrf
                                    <button type="submit"
                                            class="text-xs font-medium text-gray-500 hover:text-gray-700">
                                        Gen PDF
                                    </button>
                                </form>
                            @endif
                            @if ($sibDlUrl)
                                <a href="{{ $sibDlUrl }}" target="_blank"
                                   class="text-xs font-medium text-indigo-600 hover:text-indigo-800">
                                    Download
                                </a>
                            @endif
                        </div>
                    </div>

                    {{-- PDF viewer --}}
                    @if ($sibViewLink)
                        <div x-data="siblingPdfViewer(@js($sibViewLink))" x-init="loadPdf()" class="flex flex-col flex-1">
                            <div x-show="totalPages > 0"
                                 class="flex items-center justify-center gap-3 px-4 py-2 bg-gray-50 border-b border-gray-100 shrink-0">
                                <button @click="prevPage()" :disabled="currentPage <= 1 || loading"
                                        class="px-2 py-1 bg-white border border-gray-200 rounded text-xs text-gray-700 hover:bg-gray-50 disabled:opacity-40">‹ Prev</button>
                                <span class="text-xs text-gray-500 tabular-nums" x-text="currentPage + ' / ' + totalPages"></span>
                                <button @click="nextPage()" :disabled="currentPage >= totalPages || loading"
                                        class="px-2 py-1 bg-white border border-gray-200 rounded text-xs text-gray-700 hover:bg-gray-50 disabled:opacity-40">Next ›</button>
                            </div>
                            <div x-ref="canvasWrap"
                                 class="flex-1 flex flex-col items-center bg-gray-100 py-4 px-2 overflow-auto"
                                 style="min-height: 55vh"
                                 @wheel="handleWheel($event)">
                                <div x-show="loading && totalPages === 0"
                                     class="text-gray-400 text-sm mt-10">Loading…</div>
                                <canvas x-ref="canvas" class="shadow-lg"></canvas>
                            </div>
                        </div>
                    @else
                        <div class="flex-1 flex items-center justify-center py-10 text-sm text-gray-400">
                            @if ($sibling->drive_coverage_doc_id)
                                No PDF yet — click <strong class="mx-1">Gen PDF</strong> above.
                            @else
                                No coverage doc available.
                            @endif
                        </div>
                    @endif

                    {{-- Full-screen Google Docs editing overlay --}}
                    @if ($sibling->drive_coverage_doc_id)
                        <div x-show="editOpen" x-cloak class="fixed inset-0 z-50 flex flex-col bg-white">
                            <div class="flex items-center justify-between px-5 py-3 bg-indigo-700 text-white shrink-0">
                                <span class="font-semibold text-sm truncate pr-4">
                                    Editing: {{ $sibling->script_title }} — {{ $sibInitials }} — #{{ $sibling->order_number }}
                                </span>
                                <div class="flex items-center gap-3 shrink-0">
                                    <button type="button" @click="editOpen = false"
                                            class="text-sm text-indigo-200 hover:text-white transition-colors">
                                        Cancel
                                    </button>
                                    <button type="button"
                                            @click="editOpen = false; document.getElementById('{{ $sibFormId }}').submit()"
                                            class="inline-flex items-center gap-1.5 px-4 py-1.5 text-sm font-semibold bg-green-500 hover:bg-green-400 text-white rounded-md transition-colors">
                                        Done Editing — Generate New PDF
                                    </button>
                                </div>
                            </div>
                            <iframe src="https://docs.google.com/document/d/{{ $sibling->drive_coverage_doc_id }}/edit"
                                    class="flex-1 w-full border-0"></iframe>
                        </div>
                    @endif

                </div>
            @endforeach
        </div>
    </div>

    @else
    {{-- ===== SINGLE / READER LAYOUT ===== --}}
    <div class="py-6 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8"
         x-data="{ editOpen: false }">

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
        @if (session('warning'))
            <div class="mb-4 px-4 py-3 bg-amber-50 border border-amber-200 text-amber-800 rounded-md text-sm">
                {{ session('warning') }}
            </div>
        @endif

        @if ($batchLineItem)
            <div class="mb-4 px-4 py-3 bg-amber-50 border border-amber-200 rounded-md text-sm text-amber-800 flex items-center justify-between">
                <span>Added to <a href="{{ route('clients.show', $batchLineItem->invoice->client) }}" class="font-medium underline">{{ $batchLineItem->invoice->client->name }}</a>'s open weekly invoice #{{ $batchLineItem->invoice->invoice_number }} — ${{ number_format((float) $batchLineItem->amount, 2) }}</span>
                <a href="{{ route('clients.show', $batchLineItem->invoice->client) }}" class="ml-4 text-amber-700 hover:text-amber-900 font-medium text-xs whitespace-nowrap">View Invoice →</a>
            </div>
        @endif

        {{-- Assignment details + admin actions --}}
        <div class="bg-white rounded-lg shadow px-5 py-4 text-sm text-gray-700 space-y-2 mb-6">
            <div class="grid grid-cols-2 gap-x-6 gap-y-2">
                <div><span class="font-medium">Order #</span> {{ $assignment->order_number }}</div>
                <div><span class="font-medium">Status</span> {{ ucfirst(str_replace('_', ' ', $assignment->status)) }}</div>
                <div><span class="font-medium">Type</span> {{ ucfirst(str_replace('_', ' ', $assignment->assignment_type)) }}</div>
                <div><span class="font-medium">Vendor</span> {{ strtoupper($assignment->vendor) }}</div>
                @if (auth()->user()->isAdminOrEditor())
                    <div><span class="font-medium">Pay rate</span> ${{ number_format($assignment->pay_rate, 2) }}</div>
                @endif
            </div>
            @if ($assignment->notes)
                <div class="pt-2 border-t border-gray-100">
                    <span class="font-medium">Notes</span>
                    <p class="mt-1 text-gray-600">{{ $assignment->notes }}</p>
                </div>
            @endif

            @if (auth()->user()->isAdminOrEditor())
                <div class="pt-3 border-t border-gray-100 flex gap-3 flex-wrap">
                    <a href="{{ route('assignments.edit', $assignment) }}"
                       class="px-4 py-2 bg-gray-800 text-white text-sm font-medium rounded hover:bg-gray-700">
                        Edit Assignment
                    </a>

                    @if ($assignment->drive_coverage_doc_id)
                        <button type="button" @click="editOpen = true"
                                class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded hover:bg-indigo-700">
                            Edit Coverage Doc
                        </button>

                        <form id="regenerate-form" method="POST" action="{{ route('qc.regenerate-pdf', $assignment) }}">
                            @csrf
                            <button type="submit"
                                    class="px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-medium rounded hover:bg-gray-50">
                                Generate New PDF
                            </button>
                        </form>
                    @endif

                    @if ($assignment->status === \App\Models\Assignment::STATUS_COMPLETED && $assignment->drive_coverage_doc_id)
                        <form method="POST" action="{{ route('qc.draft-now', $assignment) }}"
                              onsubmit="return confirm('Create a HelpScout GoBack draft for this coverage?')">
                            @csrf
                            <button type="submit"
                                    class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded hover:bg-indigo-700">
                                Create HelpScout GoBack Draft
                            </button>
                        </form>
                    @endif
                </div>
            @endif
        </div>

        {{-- PDF viewer --}}
        <div class="bg-white rounded-lg shadow mb-6 overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                <span class="text-sm font-medium text-gray-700">{{ $viewerLabel }}</span>
                <div class="flex items-center gap-3">
                    @if ($dlUrl)
                        <a href="{{ $dlUrl }}"
                           class="text-xs text-indigo-600 hover:text-indigo-800">{{ $dlLabel }}</a>
                    @endif
                    @if (!auth()->user()->isReader() && \App\Support\Permission::check('script.print') && $viewLink)
                        <a href="{{ $viewLink }}" target="_blank" rel="noopener"
                           class="text-xs text-indigo-600 hover:text-indigo-800">Print</a>
                    @endif
                </div>
            </div>

            @if ($viewLink && $assignment->assigned_reader_id === auth()->id() || auth()->user()->isAdminOrEditor())
                @if ($viewLink)
                    <div x-data="readerPdfViewer(@js($viewLink), @js($assignment->id), @js(csrf_token()))"
                         x-init="loadPdf()"
                         class="flex flex-col bg-black/80" style="height: 80vh">
                        @include('partials.reader-pdf-viewer', ['assignment' => $assignment, 'standalone' => true])
                    </div>
                @else
                    <div class="px-5 py-10 text-center text-sm text-gray-400">
                        Script not yet uploaded.
                    </div>
                @endif
            @else
                <div class="px-5 py-10 text-center text-sm text-gray-400">
                    Script will be available once you accept this assignment.
                </div>
            @endif
        </div>

        {{-- Accept / reader actions --}}
        @if (auth()->user()->isReader() || auth()->user()->canManageAssignments())
            <div class="flex gap-3">
                @can('accept', $assignment)
                    <form method="POST" action="{{ route('assignments.accept', $assignment) }}">
                        @csrf
                        <button type="submit"
                                class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded hover:bg-indigo-700">
                            Accept Assignment
                        </button>
                    </form>
                @endcan

                @can('cancel', $assignment)
                    <form method="POST" action="{{ route('assignments.cancel', $assignment) }}">
                        @csrf
                        <button type="submit"
                                class="px-4 py-2 bg-gray-200 text-gray-700 text-sm font-medium rounded hover:bg-gray-300">
                            Return to Pool
                        </button>
                    </form>
                @endcan

                @can('submitCoverage', $assignment)
                    <a href="{{ route('coverage.show', $assignment) }}"
                       class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded hover:bg-green-700">
                        Submit Coverage
                    </a>
                @endcan
            </div>
        @endif

    </div>

    {{-- Full-screen Google Docs editing overlay (single layout) --}}
    @if ($assignment->drive_coverage_doc_id)
        <div x-show="editOpen" x-cloak class="fixed inset-0 z-50 flex flex-col bg-white">
            <div class="flex items-center justify-between px-5 py-3 bg-indigo-700 text-white shrink-0">
                <span class="font-semibold text-sm truncate pr-4">
                    Editing: {{ $assignment->script_title }} — #{{ $assignment->order_number }}
                </span>
                <div class="flex items-center gap-3 shrink-0">
                    <button type="button" @click="editOpen = false"
                            class="text-sm text-indigo-200 hover:text-white transition-colors">
                        Cancel
                    </button>
                    <button type="button"
                            @click="editOpen = false; document.getElementById('regenerate-form').submit()"
                            class="inline-flex items-center gap-1.5 px-4 py-1.5 text-sm font-semibold bg-green-500 hover:bg-green-400 text-white rounded-md transition-colors">
                        Done Editing — Generate New PDF
                    </button>
                </div>
            </div>
            <iframe src="https://docs.google.com/document/d/{{ $assignment->drive_coverage_doc_id }}/edit"
                    class="flex-1 w-full border-0"></iframe>
        </div>
    @endif

    @endif {{-- end single layout --}}

    @push('scripts')
    <script>
    document.addEventListener('alpine:init', () => {
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

        // Single-page Prev/Next viewer for the N-up admin layout's sibling
        // coverage PDFs — no text layer, highlights, or notes.
        Alpine.data('siblingPdfViewer', (url) => {
            let pdfDoc = null;
            let wheelTimer = null;

            return {
                url: url,
                currentPage: 1,
                totalPages: 0,
                loading: false,

                async loadPdf() {
                    this.loading = true;
                    try {
                        await ensurePdfJs();
                        pdfDoc = await pdfjsLib.getDocument({
                            url: this.url,
                            withCredentials: true,
                        }).promise;
                        this.totalPages = pdfDoc.numPages;
                        await this.renderPage(1);
                    } catch (e) {
                        console.error('PDF load error:', e);
                    } finally {
                        this.loading = false;
                    }
                },

                async renderPage(num) {
                    if (!pdfDoc) return;
                    this.loading = true;
                    try {
                        const page = await pdfDoc.getPage(num);
                        const wrap = this.$refs.canvasWrap;
                        const dpr  = window.devicePixelRatio || 1;
                        const maxW = Math.max(wrap.clientWidth - 32, 200);
                        const base  = page.getViewport({ scale: 1 });
                        const scale = Math.min(maxW / base.width, 2.0);
                        const vp    = page.getViewport({ scale: scale * dpr });
                        const canvas = this.$refs.canvas;
                        canvas.width  = vp.width;
                        canvas.height = vp.height;
                        canvas.style.width  = (vp.width  / dpr) + 'px';
                        canvas.style.height = (vp.height / dpr) + 'px';
                        await page.render({ canvasContext: canvas.getContext('2d'), viewport: vp }).promise;
                        this.currentPage = num;
                        if (this.$refs.canvasWrap) this.$refs.canvasWrap.scrollTop = 0;
                    } finally {
                        this.loading = false;
                    }
                },

                async prevPage() {
                    if (this.currentPage > 1) await this.renderPage(this.currentPage - 1);
                },

                async nextPage() {
                    if (this.currentPage < this.totalPages) await this.renderPage(this.currentPage + 1);
                },

                handleWheel(e) {
                    const wrap = this.$refs.canvasWrap;
                    if (!wrap || this.loading) return;
                    if (e.deltaY > 0) {
                        const atBottom = wrap.scrollTop + wrap.clientHeight >= wrap.scrollHeight - 10;
                        if (atBottom && this.currentPage < this.totalPages) {
                            e.preventDefault();
                            if (wheelTimer) return;
                            wheelTimer = setTimeout(() => { wheelTimer = null; }, 600);
                            this.nextPage();
                        }
                    } else if (e.deltaY < 0) {
                        const atTop = wrap.scrollTop <= 10;
                        if (atTop && this.currentPage > 1) {
                            e.preventDefault();
                            if (wheelTimer) return;
                            wheelTimer = setTimeout(() => { wheelTimer = null; }, 600);
                            this.prevPage();
                        }
                    }
                },
            };
        });
    });
    </script>
    @endpush

    @include('partials.reader-pdf-viewer-script')
</x-app-layout>
