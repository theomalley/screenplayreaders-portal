<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">URL Builder</h2>
    </x-slot>

    <div class="py-8" x-data="urlBuilder()">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-8">

            {{-- ── Script Coverage URL ──────────────────────────── --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="text-base font-semibold text-gray-800">Script Coverage Form URL</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        Build a shareable link that pre-fills the script coverage order form and optionally applies a coupon.
                        When both <span class="font-medium text-gray-700">Readers</span> and <span class="font-medium text-gray-700">Page Count</span> are chosen the cart adds automatically when the customer lands on the page.
                    </p>
                </div>

                <div class="p-6">
                    <div class="flex flex-col lg:flex-row gap-8">

                        {{-- LEFT: controls --}}
                        <div class="flex-1 min-w-0 max-w-lg space-y-4">

                            <div>
                                <label for="srub-base" class="block text-sm font-medium text-gray-700 mb-1">Base URL</label>
                                <input type="text" id="srub-base" x-model="base"
                                    class="w-full rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                <p class="mt-1 text-xs text-gray-400">Page containing the script coverage form shortcode.</p>
                            </div>

                            <div>
                                <label for="srub-readers" class="block text-sm font-medium text-gray-700 mb-1">Readers</label>
                                <select id="srub-readers" x-model="readers" @change="build()"
                                    class="w-full rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">— not pre-filled —</option>
                                    <option value="1">1 Reader — $159</option>
                                    <option value="2">2 Readers — $249 (save $69)</option>
                                    <option value="3">3 Readers — $349 (save $128)</option>
                                </select>
                            </div>

                            <div>
                                <label for="srub-pagecount" class="block text-sm font-medium text-gray-700 mb-1">Page Count</label>
                                <select id="srub-pagecount" x-model="pagecount" @change="build()"
                                    class="w-full rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">— not pre-filled —</option>
                                    <option value="up_to_120">Up to 120 pages (free)</option>
                                    <option value="121_160">121–160 pages (+$20 per reader)</option>
                                </select>
                            </div>

                            <div>
                                <label for="srub-turnaround" class="block text-sm font-medium text-gray-700 mb-1">Turnaround</label>
                                <select id="srub-turnaround" x-model="turnaround" @change="build()"
                                    class="w-full rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">— not pre-filled —</option>
                                    <option value="std3">Standard (free)</option>
                                    <option value="rush1">24-Hour Rush — 1 reader (+$97)</option>
                                    <option value="rush2">24-Hour Rush — 2 readers (+$149)</option>
                                    <option value="rush3">24-Hour Rush — 3 readers (+$197)</option>
                                </select>
                            </div>

                            <div>
                                <label for="srub-requests" class="block text-sm font-medium text-gray-700 mb-1">Reader Request</label>
                                <select id="srub-requests" x-model="requests" @change="build()"
                                    class="w-full rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">— not pre-filled —</option>
                                    <option value="first_available">First available (free)</option>
                                    <option value="req1">Request 1 specific reader (+$50)</option>
                                    <option value="req2">Request 2 specific readers (+$100)</option>
                                    <option value="req3">Request 3 specific readers (+$150)</option>
                                </select>
                            </div>

                            <div class="pt-2 border-t border-gray-100">
                                <label for="srub-coupon" class="block text-sm font-medium text-gray-700 mb-1">Coupon Code</label>
                                <input type="text" id="srub-coupon" x-model="coupon" @input="build()" placeholder="e.g. SAVE20" autocomplete="off"
                                    class="w-full rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                <p class="mt-1 text-xs text-gray-400">Auto-applied when the customer reaches cart or checkout. 1-day session.</p>
                            </div>
                        </div>

                        {{-- RIGHT: output --}}
                        <div class="flex-1 min-w-0 space-y-4">

                            <div x-show="autoAdd" x-cloak
                                class="rounded border px-4 py-3 text-sm bg-amber-50 border-amber-300 text-amber-800">
                                <span class="font-semibold">Auto-add to cart</span> — Both Readers and Page Count are set. The customer's cart will be populated automatically when they open this link.
                            </div>

                            <div x-show="!autoAdd && anyBundler" x-cloak
                                class="rounded border px-4 py-3 text-sm bg-blue-50 border-blue-200 text-blue-700">
                                <span class="font-semibold">Form pre-fill</span> — The dropdowns will be pre-selected but the customer still clicks "Add to cart."
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Generated URL</label>
                                <textarea readonly rows="4" x-ref="coverageOutput" x-text="url"
                                    class="w-full rounded border-gray-300 bg-gray-50 font-mono text-sm text-gray-700 focus:border-indigo-500 focus:ring-indigo-500 resize-y"
                                    placeholder="Fill in options on the left to generate a URL..."></textarea>
                            </div>

                            <div class="flex items-center gap-3">
                                <button type="button" @click="copyUrl($refs.coverageOutput)"
                                    class="inline-flex items-center px-3 py-1.5 bg-indigo-600 border border-transparent rounded text-xs font-medium text-white hover:bg-indigo-700 transition">
                                    Copy to clipboard
                                </button>
                                <span x-show="coverageCopied" x-cloak x-transition class="text-sm font-semibold text-green-600">Copied!</span>
                            </div>

                            <div class="mt-4 rounded border border-gray-200 bg-white px-4 py-3 text-sm text-gray-600 leading-relaxed">
                                <p class="font-semibold text-gray-700 mb-2">How the link behaves</p>
                                <ul class="list-disc pl-4 space-y-1">
                                    <li><span class="font-medium">Readers + Page Count set</span> — cart populates automatically on page load, redirects to cart.</li>
                                    <li><span class="font-medium">Only some fields set</span> — form dropdowns are pre-selected; customer clicks "Add to cart."</li>
                                    <li><span class="font-medium">Coupon</span> — stashed in session and cookie (1-day), auto-applied at cart or checkout.</li>
                                    <li>If the customer manually removes the coupon it won't re-apply — but clicking the link again will.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── Upload URL Generator ─────────────────────────── --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="text-base font-semibold text-gray-800">Upload URL Generator</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        Enter a WooCommerce order ID to generate the customer's script upload URL.
                    </p>
                </div>

                <div class="p-6">
                    <div class="flex flex-col lg:flex-row gap-8">

                        {{-- LEFT: input --}}
                        <div class="flex-1 min-w-0 max-w-lg space-y-4">
                            <div>
                                <label for="srup-order-id" class="block text-sm font-medium text-gray-700 mb-1">Order ID</label>
                                <input type="number" id="srup-order-id" x-model="uploadOrderId" @keydown.enter="lookupUploadUrl()" placeholder="e.g. 56929" min="1" step="1" autocomplete="off"
                                    class="w-full rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                <p class="mt-1 text-xs text-gray-400">The WooCommerce order number (without #).</p>
                            </div>

                            <div class="flex items-center gap-3">
                                <button type="button" @click="lookupUploadUrl()" :disabled="uploadLoading"
                                    class="inline-flex items-center px-3 py-1.5 bg-indigo-600 border border-transparent rounded text-xs font-medium text-white hover:bg-indigo-700 transition disabled:opacity-50 disabled:cursor-not-allowed">
                                    Generate Upload URL
                                </button>
                                <svg x-show="uploadLoading" x-cloak class="animate-spin h-4 w-4 text-indigo-500" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                            </div>

                            <div x-show="uploadError" x-cloak class="text-sm font-semibold text-red-600" x-text="uploadError"></div>
                        </div>

                        {{-- RIGHT: result --}}
                        <div class="flex-1 min-w-0 space-y-4" x-show="uploadResult" x-cloak>

                            <div class="rounded border border-gray-200 bg-white px-4 py-3 text-sm text-gray-600 leading-relaxed">
                                <p><span class="font-semibold text-gray-700">Order:</span> #<span x-text="uploadOrderId"></span>
                                    <span class="text-gray-400" x-text="'(' + (uploadResult?.status || '') + ')'"></span></p>
                                <p><span class="font-semibold text-gray-700">Customer:</span> <span x-text="uploadResult?.customer || ''"></span>
                                    <template x-if="uploadResult?.email">
                                        <span class="text-gray-400">&lt;<span x-text="uploadResult.email"></span>&gt;</span>
                                    </template>
                                </p>
                                <p><span class="font-semibold text-gray-700">Products:</span> <span x-text="(uploadResult?.products || []).join(', ')"></span></p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Upload URL</label>
                                <textarea readonly rows="3" x-ref="uploadOutput" x-text="uploadResult?.url || ''"
                                    class="w-full rounded border-gray-300 bg-gray-50 font-mono text-sm text-gray-700 focus:border-indigo-500 focus:ring-indigo-500 resize-y"></textarea>
                            </div>

                            <div class="flex items-center gap-3">
                                <button type="button" @click="copyUrl($refs.uploadOutput)"
                                    class="inline-flex items-center px-3 py-1.5 bg-indigo-600 border border-transparent rounded text-xs font-medium text-white hover:bg-indigo-700 transition">
                                    Copy to clipboard
                                </button>
                                <span x-show="uploadCopied" x-cloak x-transition class="text-sm font-semibold text-green-600">Copied!</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
    function urlBuilder() {
        return {
            base: 'https://screenplayreaders.com/script-coverage-service/',
            readers: '',
            pagecount: '',
            turnaround: '',
            requests: '',
            coupon: '',
            url: '',
            autoAdd: false,
            anyBundler: false,
            coverageCopied: false,

            uploadOrderId: '',
            uploadLoading: false,
            uploadError: '',
            uploadResult: null,
            uploadCopied: false,

            init() {
                this.$watch('base', () => this.build());
                this.build();
            },

            build() {
                let baseVal = (this.base || '').trim().replace(/\/+$/, '') + '/';
                let parts = [];

                if (this.readers)    parts.push('readers=' + encodeURIComponent(this.readers));
                if (this.pagecount)  parts.push('pagecount=' + encodeURIComponent(this.pagecount));
                if (this.turnaround) parts.push('turnaround=' + encodeURIComponent(this.turnaround));
                if (this.requests)   parts.push('requests=' + encodeURIComponent(this.requests));

                let couponVal = (this.coupon || '').trim();
                if (couponVal) parts.push('coupon=' + encodeURIComponent(couponVal));

                this.url = parts.length ? baseVal + '?' + parts.join('&') : baseVal;

                this.autoAdd = this.readers !== '' && this.pagecount !== '';
                this.anyBundler = parts.length > 0 && (this.readers !== '' || this.pagecount !== '' || this.turnaround !== '' || this.requests !== '');
            },

            async lookupUploadUrl() {
                let orderId = (this.uploadOrderId || '').toString().trim();
                if (!orderId) {
                    this.uploadError = 'Please enter an order ID.';
                    this.uploadResult = null;
                    return;
                }

                this.uploadError = '';
                this.uploadResult = null;
                this.uploadLoading = true;

                try {
                    let resp = await fetch('{{ route("url-builder.upload-lookup") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ order_id: orderId })
                    });

                    let data = await resp.json();

                    if (!resp.ok || data.error) {
                        this.uploadError = data.error || data.message || 'Lookup failed.';
                    } else {
                        this.uploadResult = data;
                    }
                } catch (e) {
                    this.uploadError = 'Request failed. Please try again.';
                } finally {
                    this.uploadLoading = false;
                }
            },

            copyUrl(textarea) {
                let val = textarea.textContent || textarea.value;
                if (!val) return;

                let isUpload = textarea === this.$refs.uploadOutput;

                if (navigator.clipboard) {
                    navigator.clipboard.writeText(val).then(() => {
                        if (isUpload) {
                            this.uploadCopied = true;
                            setTimeout(() => this.uploadCopied = false, 2500);
                        } else {
                            this.coverageCopied = true;
                            setTimeout(() => this.coverageCopied = false, 2500);
                        }
                    });
                } else {
                    textarea.select();
                    document.execCommand('copy');
                    if (isUpload) {
                        this.uploadCopied = true;
                        setTimeout(() => this.uploadCopied = false, 2500);
                    } else {
                        this.coverageCopied = true;
                        setTimeout(() => this.coverageCopied = false, 2500);
                    }
                }
            }
        };
    }
    </script>
</x-app-layout>
