<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">QC Queue</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

            @if(session('success'))
                <div class="bg-green-50 border border-green-200 rounded-lg px-4 py-3 text-sm text-green-700">
                    {{ session('success') }}
                </div>
            @endif

            @if($assignments->isEmpty())
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 px-6 py-12 text-center text-gray-400 text-sm">
                    No assignments awaiting QC.
                </div>
            @else
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs font-medium text-gray-500 uppercase tracking-wide">
                            <tr>
                                <th class="px-4 py-3 text-left">Order</th>
                                <th class="px-4 py-3 text-left">Script / Writer</th>
                                <th class="px-4 py-3 text-left">Type</th>
                                <th class="px-4 py-3 text-left">Reader</th>
                                <th class="px-4 py-3 text-left">Submitted</th>
                                <th class="px-4 py-3 text-left">Doc / PDF</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($assignments as $assignment)
                                @php
                                    $viewUrl   = $assignment->hasCloudScript()
                                        ? route('assignments.streamScript', $assignment)
                                        : null;
                                    $typeLabel = match($assignment->assignment_type) {
                                        'script_coverage' => 'Script Coverage',
                                        'notes_only'      => 'Notes-Only',
                                        'deep_dive'       => 'Deep-Dive',
                                        'short'           => 'Short',
                                        'budget'          => 'Budget',
                                        'book'            => 'Book',
                                        'coverage'        => 'Coverage',
                                        'development_notes' => 'Dev Notes',
                                        default           => $assignment->assignment_type ?? '—',
                                    };
                                    if ($assignment->vendor === 'wd') {
                                        $typeLabel = 'WD ' . $typeLabel;
                                    }
                                @endphp
                                <tr class="hover:bg-gray-50 cursor-pointer"
                                    onclick="if (!event.target.closest('a, button, form')) window.location='{{ route('qc.show', $assignment) }}'">
                                    <td class="px-4 py-3 font-mono text-gray-700 whitespace-nowrap">
                                        {{ $assignment->order_number }}
                                    </td>
                                    <td class="px-4 py-3" x-data="pdfViewer(@js($viewUrl))">
                                        @if($viewUrl)
                                            <button @click="openViewer()" type="button"
                                                    class="font-medium text-gray-800 hover:text-indigo-600 text-left leading-snug">📄 {{ $assignment->script_title }}</button>
                                            <div x-show="open" x-cloak
                                                 @keydown.escape.window="open = false"
                                                 @keydown.arrow-right.window="if (open) nextPage()"
                                                 @keydown.arrow-left.window="if (open) prevPage()"
                                                 x-ref="modal"
                                                 tabindex="-1"
                                                 class="fixed inset-0 z-50 flex flex-col bg-black/80">
                                                <div class="flex items-center justify-between px-4 py-2 bg-gray-900 shrink-0 gap-4">
                                                    <span class="text-sm text-gray-200 font-medium truncate min-w-0">{{ $assignment->drive_script_filename ?? $assignment->script_title }}</span>
                                                    <div class="flex items-center gap-3 shrink-0">
                                                        <div x-show="totalPages > 0" class="flex items-center gap-2">
                                                            <button @click="prevPage()" :disabled="currentPage <= 1 || loading"
                                                                    class="px-2 py-1 bg-gray-700 hover:bg-gray-600 rounded text-xs text-gray-200 disabled:opacity-40">‹</button>
                                                            <span class="text-xs text-gray-300 tabular-nums" x-text="currentPage + ' / ' + totalPages"></span>
                                                            <button @click="nextPage()" :disabled="currentPage >= totalPages || loading"
                                                                    class="px-2 py-1 bg-gray-700 hover:bg-gray-600 rounded text-xs text-gray-200 disabled:opacity-40">›</button>
                                                        </div>
                                                        <button @click="open = false" type="button"
                                                                class="text-gray-400 hover:text-white text-2xl leading-none px-1">×</button>
                                                    </div>
                                                </div>
                                                <div x-ref="canvasWrap" class="flex-1 overflow-auto flex flex-col items-center bg-gray-800 py-6 px-4" @wheel="handleWheel($event)">
                                                    <div x-show="loading && totalPages === 0" class="text-gray-400 text-sm">Loading…</div>
                                                    <canvas x-ref="canvas" class="shadow-2xl"></canvas>
                                                </div>
                                            </div>
                                        @else
                                            <div class="font-medium text-gray-800">{{ $assignment->script_title }}</div>
                                        @endif
                                        <div class="text-gray-400 text-xs">{{ $assignment->writer_name }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $typeLabel }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        @if ($assignment->assignedReader)
                                            <x-staff-icon :user="$assignment->assignedReader" size="sm" />
                                        @else
                                            <span class="text-gray-300">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-gray-500 whitespace-nowrap tabular-nums">
                                        {{ $assignment->submitted_at?->format('M j, Y') ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="{{ $assignment->drive_coverage_doc_id ? 'text-green-600' : 'text-gray-300' }}" title="Google Doc">
                                            Doc
                                        </span>
                                        <span class="text-gray-300 mx-1">/</span>
                                        <span class="{{ $assignment->drive_coverage_pdf_id ? 'text-green-600' : 'text-gray-300' }}" title="PDF">
                                            PDF
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('qc.show', $assignment) }}"
                                            class="text-xs font-medium text-indigo-600 hover:text-indigo-800">
                                            Review →
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($assignments->hasPages())
                    <div>{{ $assignments->links() }}</div>
                @endif
            @endif

        </div>
    </div>

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

        Alpine.data('pdfViewer', (url) => {
            let pdfDoc = null;
            let wheelTimer = null;
            return {
                open: false,
                url: url,
                currentPage: 1,
                totalPages: 0,
                loading: false,

                async openViewer() {
                    this.open = true;
                    await this.$nextTick();
                    this.$refs.modal.focus();
                    if (!pdfDoc) await this.loadPdf();
                },

                async loadPdf() {
                    this.loading = true;
                    try {
                        await ensurePdfJs();
                        pdfDoc = await pdfjsLib.getDocument({ url: this.url, withCredentials: true }).promise;
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
                        const maxW = Math.max(wrap.clientWidth - 48, 200);
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

                async prevPage() { if (this.currentPage > 1) await this.renderPage(this.currentPage - 1); },
                async nextPage() { if (this.currentPage < this.totalPages) await this.renderPage(this.currentPage + 1); },

                handleWheel(e) {
                    e.preventDefault();
                    if (this.loading || wheelTimer) return;
                    if (e.deltaY > 0 && this.currentPage < this.totalPages) {
                        wheelTimer = setTimeout(() => { wheelTimer = null; }, 200);
                        this.nextPage();
                    } else if (e.deltaY < 0 && this.currentPage > 1) {
                        wheelTimer = setTimeout(() => { wheelTimer = null; }, 200);
                        this.prevPage();
                    }
                },
            };
        });
    });
    </script>
    @endpush
</x-app-layout>
