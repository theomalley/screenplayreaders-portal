{{-- Shared continuous-scroll PDF.js viewer Alpine components: `pdfViewer`
     (plain continuous scroll) and `readerPdfViewer` (continuous scroll +
     search, reading notes, and highlight/selection support). Pairs with
     resources/views/partials/reader-pdf-viewer.blade.php. --}}
@once
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

    // PDF text content items aren't always in visual reading order, which makes
    // click-and-drag selection jump around. Re-order top-to-bottom, left-to-right
    // before handing off to the text layer so drag-selection follows the page visually.
    function sortTextItemsForSelection(textContent) {
        const items = [...textContent.items].sort((a, b) => {
            const ay = a.transform ? a.transform[5] : 0;
            const by = b.transform ? b.transform[5] : 0;
            if (Math.abs(ay - by) > 1) return by - ay;
            const ax = a.transform ? a.transform[4] : 0;
            const bx = b.transform ? b.transform[4] : 0;
            return ax - bx;
        });
        return { ...textContent, items };
    }

    function makePdfViewerData(url) {
        let pdfDoc = null;
        let pages  = [];
        let pageRatios  = new Map();
        let pageObserver = null;
        return {
            open: false,
            url: url,
            currentPage: 0,
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
                    pdfDoc = await pdfjsLib.getDocument({
                        url: this.url,
                        withCredentials: true,
                    }).promise;
                    this.totalPages = pdfDoc.numPages;
                    await this.renderAllPages();
                    await this.onPdfLoaded(pdfDoc);
                } catch (e) {
                    console.error('PDF load error:', e);
                } finally {
                    this.loading = false;
                }
            },

            async onPdfLoaded(pdfDoc) {},

            async renderPageOverlay(pageWrap, canvas, page, scale, pageNum) {},

            clearOverlays() {},

            async renderAllPages() {
                const wrap = this.$refs.canvasWrap;
                const dpr  = window.devicePixelRatio || 1;
                pages = [];
                if (pageObserver) pageObserver.disconnect();
                pageRatios.clear();
                this.clearOverlays();
                const maxW = Math.max(wrap.clientWidth - 48, 200);
                for (let i = 1; i <= this.totalPages; i++) {
                    this.currentPage = i;
                    const page = await pdfDoc.getPage(i);
                    const base  = page.getViewport({ scale: 1 });
                    const scale = Math.min(maxW / base.width, 2.0);
                    const vp    = page.getViewport({ scale: scale * dpr });
                    const pageWrap = document.createElement('div');
                    pageWrap.className = 'relative shrink-0 pdf-page';
                    pageWrap.dataset.page = i;
                    const canvas = document.createElement('canvas');
                    canvas.width  = vp.width;
                    canvas.height = vp.height;
                    canvas.style.width  = (vp.width  / dpr) + 'px';
                    canvas.style.height = (vp.height / dpr) + 'px';
                    canvas.className = 'shadow-2xl block';
                    pageWrap.appendChild(canvas);
                    wrap.appendChild(pageWrap);
                    pages.push(pageWrap);
                    await page.render({ canvasContext: canvas.getContext('2d'), viewport: vp }).promise;
                    await this.renderPageOverlay(pageWrap, canvas, page, scale, i);
                }

                // Track which page is currently in view as the user scrolls,
                // so notes can be auto-tagged with the page being read.
                pageObserver = new IntersectionObserver((entries) => {
                    for (const entry of entries) {
                        pageRatios.set(parseInt(entry.target.dataset.page, 10), entry.intersectionRatio);
                    }
                    let best = null, bestRatio = 0;
                    for (const [pg, ratio] of pageRatios) {
                        if (ratio > bestRatio) { bestRatio = ratio; best = pg; }
                    }
                    if (best) this.currentPage = best;
                }, { root: wrap, threshold: [0, 0.25, 0.5, 0.75, 1] });
                pages.forEach(c => pageObserver.observe(c));
            },

            scrollToPage(num) {
                const n = Math.max(1, Math.min(parseInt(num) || 1, this.totalPages));
                if (pages[n - 1]) pages[n - 1].scrollIntoView({ behavior: 'smooth' });
            },

            async reloadPdf() {
                const wrap = this.$refs.canvasWrap;
                if (pageObserver) { pageObserver.disconnect(); pageObserver = null; }
                pageRatios.clear();
                this.clearOverlays();
                if (wrap) for (const el of [...wrap.querySelectorAll('.pdf-page')]) el.remove();
                pages = [];
                pdfDoc = null;
                this.totalPages = 0;
                this.currentPage = 0;
                this.url = this.url.split('?')[0] + '?t=' + Date.now();
                await this.loadPdf();
            },
        };
    }

    Alpine.data('pdfViewer', makePdfViewerData);

    Alpine.data('readerPdfViewer', (url, assignmentId, csrfToken) => {
        let pageOverlays = new Map();
        let pageTextContents = new Map();

        return {
        ...makePdfViewerData(url),
        notesOpen: false,
        notesPanelWidth: 288,
        notes: [],
        noteBody: '',
        noteSaving: false,
        notesLoaded: false,
        searchOpen: false,
        searchQuery: '',
        searchResults: [],
        pageTexts: [],
        highlights: [],
        selectionToolbar: { show: false, x: 0, y: 0, text: '', pageNum: null, rects: [] },

        async onPdfLoaded(pdfDoc) {
            const texts = [];
            for (let i = 1; i <= pdfDoc.numPages; i++) {
                texts.push(pageTextContents.get(i) || '');
            }
            this.pageTexts = texts;
            if (this.searchQuery.trim()) this.doSearch();
            await this.loadHighlights();
        },

        async renderPageOverlay(pageWrap, canvas, page, scale, pageNum) {
            const cssVp = page.getViewport({ scale });

            const highlightLayerEl = document.createElement('div');
            highlightLayerEl.className = 'highlight-layer';
            highlightLayerEl.style.width  = cssVp.width + 'px';
            highlightLayerEl.style.height = cssVp.height + 'px';
            pageWrap.appendChild(highlightLayerEl);

            const textLayerEl = document.createElement('div');
            textLayerEl.className = 'textLayer';
            textLayerEl.style.width  = cssVp.width + 'px';
            textLayerEl.style.height = cssVp.height + 'px';
            textLayerEl.style.setProperty('--scale-factor', scale);
            pageWrap.appendChild(textLayerEl);

            const textContent = await page.getTextContent();
            const sortedTextContent = sortTextItemsForSelection(textContent);
            pageTextContents.set(pageNum, sortedTextContent.items.map(item => item.str).join(' '));

            await pdfjsLib.renderTextLayer({
                textContentSource: sortedTextContent,
                container: textLayerEl,
                viewport: cssVp,
            }).promise;

            pageOverlays.set(pageNum, { pageWrap, textLayerEl, highlightLayerEl });
            this.renderHighlightMarks(pageNum);

            pageWrap.addEventListener('mouseup', () => this.handleSelection(pageNum, pageWrap));
        },

        clearOverlays() {
            pageOverlays.clear();
            pageTextContents.clear();
        },

        renderHighlightMarks(pageNum) {
            const overlay = pageOverlays.get(pageNum);
            if (!overlay) return;
            overlay.highlightLayerEl.innerHTML = '';
            for (const h of this.highlights.filter(h => h.page_number === pageNum)) {
                for (const r of h.rects) {
                    const mark = document.createElement('div');
                    mark.className = 'highlight-mark';
                    mark.style.left   = (r.x * 100) + '%';
                    mark.style.top    = (r.y * 100) + '%';
                    mark.style.width  = (r.width * 100) + '%';
                    mark.style.height = (r.height * 100) + '%';
                    mark.title = 'Click to remove highlight';
                    mark.addEventListener('click', (e) => {
                        e.stopPropagation();
                        this.deleteHighlight(h.id);
                    });
                    overlay.highlightLayerEl.appendChild(mark);
                }
            }
        },

        async loadHighlights() {
            try {
                const r = await fetch(`/assignments/${assignmentId}/highlights`, {
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                });
                if (r.ok) {
                    this.highlights = await r.json();
                    for (const pg of pageOverlays.keys()) this.renderHighlightMarks(pg);
                }
            } catch (e) { console.error(e); }
        },

        handleSelection(pageNum, pageWrap) {
            const sel = window.getSelection();
            if (!sel || sel.isCollapsed || sel.rangeCount === 0) return;
            const range = sel.getRangeAt(0);
            if (!pageWrap.contains(range.commonAncestorContainer)) return;
            const text = sel.toString().trim();
            if (!text) return;
            const clientRects = Array.from(range.getClientRects()).filter(r => r.width > 0 && r.height > 0);
            if (!clientRects.length) return;

            const containerRect = pageWrap.getBoundingClientRect();
            const last = clientRects[clientRects.length - 1];
            this.selectionToolbar = {
                show: true,
                x: last.right,
                y: last.bottom,
                text,
                pageNum,
                rects: clientRects.map(r => ({
                    x: (r.left - containerRect.left) / containerRect.width,
                    y: (r.top - containerRect.top) / containerRect.height,
                    width: r.width / containerRect.width,
                    height: r.height / containerRect.height,
                })),
            };
        },

        clearSelectionToolbar() {
            this.selectionToolbar = { show: false, x: 0, y: 0, text: '', pageNum: null, rects: [] };
        },

        async saveHighlight(clear = true) {
            const t = this.selectionToolbar;
            if (!t.show) return;
            try {
                const r = await fetch(`/assignments/${assignmentId}/highlights`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({ page_number: t.pageNum, text: t.text, rects: t.rects, color: 'yellow' }),
                });
                if (r.ok) {
                    const h = await r.json();
                    this.highlights.push(h);
                    this.renderHighlightMarks(h.page_number);
                }
            } catch (e) { console.error(e); } finally {
                if (clear) {
                    window.getSelection()?.removeAllRanges();
                    this.clearSelectionToolbar();
                }
            }
        },

        addSelectionToNote(clear = true) {
            const t = this.selectionToolbar;
            if (!t.show) return;
            this.noteBody = (this.noteBody ? this.noteBody.trim() + '\n\n' : '') + `"${t.text}" (p. ${t.pageNum})\n`;
            this.notesOpen = true;
            if (!this.notesLoaded) this.loadNotes();
            if (clear) {
                window.getSelection()?.removeAllRanges();
                this.clearSelectionToolbar();
            }
        },

        async highlightAndAddToNote() {
            await this.saveHighlight(false);
            this.addSelectionToNote(false);
            window.getSelection()?.removeAllRanges();
            this.clearSelectionToolbar();
        },

        async deleteHighlight(id) {
            if (!confirm('Remove this highlight?')) return;
            try {
                await fetch(`/highlights/${id}`, {
                    method: 'DELETE',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                });
                const h = this.highlights.find(h => h.id === id);
                this.highlights = this.highlights.filter(h => h.id !== id);
                if (h) this.renderHighlightMarks(h.page_number);
            } catch (e) { console.error(e); }
        },

        doSearch() {
            const q = this.searchQuery.trim().toLowerCase();
            if (!q) { this.searchResults = []; return; }
            this.searchResults = this.pageTexts
                .map((text, i) => text.toLowerCase().includes(q) ? i + 1 : null)
                .filter(Boolean);
        },

        startNotesResize(e) {
            e.preventDefault();
            const startX = e.clientX;
            const startWidth = this.notesPanelWidth;
            const onMove = (ev) => {
                const newWidth = startWidth + (startX - ev.clientX);
                this.notesPanelWidth = Math.max(240, Math.min(newWidth, window.innerWidth - 320));
            };
            const onUp = () => {
                window.removeEventListener('mousemove', onMove);
                window.removeEventListener('mouseup', onUp);
            };
            window.addEventListener('mousemove', onMove);
            window.addEventListener('mouseup', onUp);
        },

        async loadNotes() {
            if (this.notesLoaded) return;
            try {
                const r = await fetch(`/assignments/${assignmentId}/reading-notes`, {
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                });
                if (r.ok) {
                    this.notes = await r.json();
                    this.notesLoaded = true;
                }
            } catch (e) { console.error(e); }
        },

        async addNote() {
            if (!this.noteBody.trim() || this.noteSaving) return;
            this.noteSaving = true;
            try {
                const r = await fetch(`/assignments/${assignmentId}/reading-notes`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({ body: this.noteBody.trim(), page_number: this.currentPage || null }),
                });
                if (r.ok) {
                    this.notes.push(await r.json());
                    this.noteBody = '';
                }
            } catch (e) { console.error(e); } finally { this.noteSaving = false; }
        },

        async deleteNote(id) {
            if (!confirm('Delete this note?')) return;
            try {
                await fetch(`/reading-notes/${id}`, {
                    method: 'DELETE',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                });
                this.notes = this.notes.filter(n => n.id !== id);
            } catch (e) { console.error(e); }
        },
        };
    });
});
</script>
@endpush
@endonce
