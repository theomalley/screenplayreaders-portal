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

    <div class="py-6 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">

        @if (session('success'))
            <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded-md text-sm">
                {{ session('success') }}
            </div>
        @endif

        {{-- Script viewer --}}
        <div class="bg-white rounded-lg shadow mb-6 overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                <span class="text-sm font-medium text-gray-700">Script</span>
                @if ($dlUrl)
                    <a href="{{ $dlUrl }}" target="_blank"
                       class="text-xs text-indigo-600 hover:text-indigo-800">Download (admin)</a>
                @endif
            </div>

            @if ($viewLink && $assignment->assigned_reader_id === auth()->id() || auth()->user()->isAdminOrEditor())
                @if ($viewLink)
                    <div x-data="{
                            url: @js($viewLink),
                            _pdf: null,
                            currentPage: 1,
                            totalPages: 0,
                            loading: true,

                            async init() {
                                await this.$nextTick();
                                await this.loadPdf();
                            },

                            async loadPdf() {
                                this.loading = true;
                                try {
                                    this._pdf = await pdfjsLib.getDocument({ url: this.url, withCredentials: true }).promise;
                                    this.totalPages = this._pdf.numPages;
                                    await this.renderPage(1);
                                } catch (e) {
                                    console.error('PDF load error:', e);
                                } finally {
                                    this.loading = false;
                                }
                            },

                            async renderPage(num) {
                                if (!this._pdf) return;
                                this.loading = true;
                                try {
                                    const page = await this._pdf.getPage(num);
                                    const wrap = this.$refs.canvasWrap;
                                    const maxW = Math.max(wrap.clientWidth - 48, 200);
                                    const base = page.getViewport({ scale: 1 });
                                    const scale = Math.min(maxW / base.width, 1.5);
                                    const vp = page.getViewport({ scale });
                                    const canvas = this.$refs.canvas;
                                    canvas.width  = vp.width;
                                    canvas.height = vp.height;
                                    await page.render({ canvasContext: canvas.getContext('2d'), viewport: vp }).promise;
                                    this.currentPage = num;
                                    wrap.scrollTop = 0;
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
                        }"
                        @keydown.arrow-right.window="nextPage()"
                        @keydown.arrow-left.window="prevPage()">

                        {{-- Page controls bar --}}
                        <div x-show="totalPages > 0" class="flex items-center justify-center gap-3 px-4 py-2 bg-gray-50 border-b border-gray-100">
                            <button @click="prevPage()" :disabled="currentPage <= 1 || loading"
                                    class="px-3 py-1 bg-white border border-gray-200 rounded text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-40">‹ Prev</button>
                            <span class="text-sm text-gray-500 tabular-nums" x-text="currentPage + ' / ' + totalPages"></span>
                            <button @click="nextPage()" :disabled="currentPage >= totalPages || loading"
                                    class="px-3 py-1 bg-white border border-gray-200 rounded text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-40">Next ›</button>
                        </div>

                        <div x-ref="canvasWrap" class="flex flex-col items-center bg-gray-100 py-6 px-4" style="min-height:60vh">
                            <div x-show="loading && totalPages === 0" class="text-gray-400 text-sm mt-10">Loading…</div>
                            <canvas x-ref="canvas" class="shadow-lg"></canvas>
                        </div>
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

        {{-- Assignment details --}}
        <div class="bg-white rounded-lg shadow px-5 py-4 text-sm text-gray-700 space-y-2">
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
        </div>

        {{-- Reader actions --}}
        @if (auth()->user()->isReader())
            <div class="mt-4 flex gap-3">
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

        {{-- Admin actions --}}
        @if (auth()->user()->isAdminOrEditor())
            <div class="mt-4 flex gap-3">
                <a href="{{ route('assignments.edit', $assignment) }}"
                   class="px-4 py-2 bg-gray-800 text-white text-sm font-medium rounded hover:bg-gray-700">
                    Edit Assignment
                </a>
            </div>
        @endif

    </div>
</x-app-layout>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.min.js"></script>
<script>
    pdfjsLib.GlobalWorkerOptions.workerSrc =
        'https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.worker.min.js';
</script>
@endpush
