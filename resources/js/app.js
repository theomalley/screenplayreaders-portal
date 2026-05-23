import Alpine from 'alpinejs';

window.Alpine = Alpine;

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

Alpine.data('pdfViewer', (url) => ({
    open: false,
    url: url,
    _pdf: null,
    currentPage: 1,
    totalPages: 0,
    loading: false,

    async openViewer() {
        this.open = true;
        await this.$nextTick();
        this.$refs.modal.focus();
        if (!this._pdf) await this.loadPdf();
    },

    async loadPdf() {
        this.loading = true;
        try {
            await ensurePdfJs();
            this._pdf = await pdfjsLib.getDocument({
                url: this.url,
                withCredentials: true,
            }).promise;
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
            const scale = Math.min(maxW / base.width, 2.0);
            const vp = page.getViewport({ scale });
            const canvas = this.$refs.canvas;
            canvas.width  = vp.width;
            canvas.height = vp.height;
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
}));

Alpine.start();
