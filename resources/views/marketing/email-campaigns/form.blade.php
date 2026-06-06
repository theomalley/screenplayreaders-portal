<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('marketing.email-campaigns.index') }}" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ $campaign->exists ? $campaign->campaign_name : 'New Campaign' }}
                </h2>
                @if($campaign->exists)
                    @php
                        $badge = match($campaign->status) {
                            'queued' => 'bg-indigo-100 text-indigo-700',
                            'sent'   => 'bg-green-100 text-green-700',
                            'paused' => 'bg-amber-100 text-amber-700',
                            default  => 'bg-gray-100 text-gray-500',
                        };
                    @endphp
                    <span class="text-xs px-2 py-0.5 rounded-full {{ $badge }} font-medium">{{ ucfirst($campaign->status) }}</span>
                @endif
            </div>
            @if($campaign->exists)
                <div class="flex items-center gap-2">
                    {{-- Test send --}}
                    <form action="{{ route('marketing.email-campaigns.send-test', $campaign) }}" method="POST">
                        @csrf
                        <button type="submit" class="px-3 py-1.5 text-sm bg-white border border-gray-300 rounded hover:bg-gray-50 text-gray-700">
                            Send Test Email
                        </button>
                    </form>
                    {{-- Send Live / Schedule --}}
                    @if($campaign->status !== 'sent')
                        <form action="{{ route('marketing.email-campaigns.send-live', $campaign) }}" method="POST">
                            @csrf
                            <button type="submit"
                                    class="px-3 py-1.5 text-sm bg-green-600 text-white rounded hover:bg-green-700"
                                    onclick="return confirm('{{ $campaign->scheduled_at && $campaign->scheduled_at->isFuture() ? 'Schedule this campaign in MailerLite?' : 'Send this campaign to subscribers NOW?' }}')">
                                {{ $campaign->scheduled_at && $campaign->scheduled_at->isFuture() ? 'Schedule in MailerLite' : 'Send Live Now' }}
                            </button>
                        </form>
                    @endif
                </div>
            @endif
        </div>
    </x-slot>

    @php
        $isEdit      = $campaign->exists;
        $formAction  = $isEdit
            ? route('marketing.email-campaigns.update', $campaign)
            : route('marketing.email-campaigns.store');
        // Fallback comma-separated IDs for the text input when WC products can't be fetched
        $productIds  = is_array($campaign->coupon_product_ids)
            ? implode(',', $campaign->coupon_product_ids)
            : ($campaign->coupon_product_ids ?? '');
        // For the multi-select, resolve pre-selected IDs from old() if present
        $selectedProductIds = old('coupon_product_ids')
            ? array_filter(array_map('trim', explode(',', old('coupon_product_ids'))))
            : (array) ($campaign->coupon_product_ids ?? []);
    @endphp

    <div class="py-6">
        <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-4 px-4 py-2 bg-green-50 border border-green-200 text-green-700 text-sm rounded">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="mb-4 px-4 py-2 bg-red-50 border border-red-200 text-red-700 text-sm rounded">{{ session('error') }}</div>
            @endif
            @if($campaign->exists && $campaign->test_sent_at)
                <div class="mb-4 px-4 py-2 bg-blue-50 border border-blue-200 text-blue-700 text-sm rounded">
                    Last test sent {{ $campaign->test_sent_at->diffForHumans() }} — edit freely, then send test again or go live.
                </div>
            @endif

            <form id="campaign-form"
                  action="{{ $formAction }}"
                  method="POST"
                  enctype="multipart/form-data"
                  x-data="{
                    couponCode: '{{ old('coupon_code', $campaign->coupon_code ?? '') }}',
                    scheduledAt: '{{ old('scheduled_at', $campaign->scheduled_at?->format('Y-m-d\TH:i') ?? '') }}',
                    couponDays: {{ old('coupon_duration_days', $campaign->coupon_duration_days ?? 0) }},
                    imageUrl: '{{ old('image_url', $campaign->image_url ?? '') }}',
                    imagePath: '{{ old('image_path', $campaign->image_path ?? '') }}',
                    imageUploading: false,

                    activeTab: 'fields',
                    htmlSource: @js(old('custom_html', $campaign->custom_html ?? '')),

                    previewView: 'desktop',
                    previewLoading: false,
                    previewHtml: @js($initialHtml),

                    get expiryPreview() {
                        if (!this.scheduledAt || !this.couponDays) return '';
                        const d = new Date(this.scheduledAt);
                        d.setDate(d.getDate() + parseInt(this.couponDays));
                        return d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
                    },
                    generateCode() {
                        const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
                        let code = 'SR-';
                        for (let i = 0; i < 6; i++) code += chars[Math.floor(Math.random() * chars.length)];
                        this.couponCode = code;
                    },
                    async uploadImage(input) {
                        if (!input.files[0]) return;
                        this.imageUploading = true;
                        const fd = new FormData();
                        fd.append('image', input.files[0]);
                        fd.append('_token', document.querySelector('meta[name=csrf-token]').content);
                        const r = await fetch('{{ route('marketing.email-campaigns.upload-image') }}', { method: 'POST', body: fd });
                        const j = await r.json();
                        this.imageUrl  = j.url;
                        this.imagePath = j.path;
                        this.imageUploading = false;
                    },
                    async refreshPreview() {
                        this.previewLoading = true;
                        const form = document.getElementById('campaign-form');
                        const data = new FormData(form);
                        data.delete('_method');
                        const r = await fetch('{{ route('marketing.email-campaigns.preview') }}', {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                            body: data
                        });
                        this.previewHtml = await r.text();
                        this.previewLoading = false;
                    },
                    async generateFromFields() {
                        this.previewLoading = true;
                        const form = document.getElementById('campaign-form');
                        const data = new FormData(form);
                        data.delete('_method');
                        data.delete('custom_html');
                        const r = await fetch('{{ route('marketing.email-campaigns.preview') }}', {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                            body: data
                        });
                        const html = await r.text();
                        this.htmlSource = html;
                        this.previewHtml = html;
                        this.previewLoading = false;
                    },

                    // --- Template library ---
                    templates: @js($emailTemplates),
                    showSaveModal: false,
                    templateName: '',
                    showLoadDropdown: false,

                    async saveAsTemplate() {
                        if (!this.templateName.trim() || !this.htmlSource) return;
                        const r = await fetch('{{ route('marketing.email-templates.store') }}', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ name: this.templateName, html: this.htmlSource })
                        });
                        if (r.ok) {
                            const j = await r.json();
                            this.templates.unshift(j);
                            this.showSaveModal = false;
                            this.templateName = '';
                        }
                    },

                    async loadTemplate(id) {
                        const tpl = this.templates.find(t => t.id === id);
                        if (!tpl) return;
                        if (this.htmlSource && !confirm('Replace current HTML with this template?')) return;
                        const r = await fetch('/marketing/email-templates/' + id, {
                            headers: { 'Accept': 'application/json' }
                        });
                        const j = await r.json();
                        this.htmlSource = j.html;
                        this.previewHtml = j.html;
                        this.showLoadDropdown = false;
                    },

                    async deleteTemplate(id, name) {
                        if (!confirm('Delete template ' + name + '?')) return;
                        const r = await fetch('/marketing/email-templates/' + id, {
                            method: 'DELETE',
                            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
                        });
                        if (r.ok) this.templates = this.templates.filter(t => t.id !== id);
                    }
                  }"
                  x-init="refreshPreview()">
                @csrf
                @if($isEdit) @method('PATCH') @endif

                <div class="flex flex-col lg:flex-row gap-6 lg:items-start">

                    {{-- LEFT COLUMN: form fields --}}
                    <div class="flex-1 min-w-0">

                        {{-- Tab bar: Fields / HTML Source --}}
                        <div class="flex items-center gap-1 border-b border-gray-200 mb-5">
                            <button type="button" @click="activeTab='fields'"
                                    :class="activeTab==='fields' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                    class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors">
                                Fields
                            </button>
                            <button type="button" @click="activeTab='html'"
                                    :class="activeTab==='html' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                    class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors flex items-center gap-2">
                                HTML Source
                                <span x-show="htmlSource" x-cloak
                                      class="text-xs px-1.5 py-0.5 bg-amber-100 text-amber-700 rounded-full font-normal">custom</span>
                            </button>
                        </div>

                        {{-- Custom HTML active warning (shown when on Fields tab but custom HTML is set) --}}
                        <div x-show="activeTab === 'fields' && htmlSource" x-cloak
                             class="mb-4 px-3 py-2 bg-amber-50 border border-amber-200 rounded text-xs text-amber-700 flex items-center justify-between gap-3">
                            <span>Custom HTML is active — field edits won't affect the email unless you clear or regenerate the HTML.</span>
                            <button type="button" @click="activeTab='html'"
                                    class="shrink-0 text-xs text-amber-700 underline hover:text-amber-900">Edit HTML</button>
                        </div>

                        {{-- FIELDS view --}}
                        <div x-show="activeTab === 'fields'" class="space-y-5">

                        {{-- Card: Campaign Info --}}
                        <div class="bg-white border border-gray-200 rounded-lg p-5 space-y-4">
                            <h3 class="text-sm font-semibold text-gray-700">Campaign</h3>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="col-span-2">
                                    <x-input-label for="campaign_name" value="Name (internal)" />
                                    <input type="text" id="campaign_name" name="campaign_name"
                                           value="{{ old('campaign_name', $campaign->campaign_name) }}"
                                           required
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <x-input-error :messages="$errors->get('campaign_name')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label for="status" value="Status" />
                                    <select id="status" name="status"
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="draft"  {{ old('status', $campaign->status) === 'draft'  ? 'selected' : '' }}>Draft</option>
                                        <option value="queued" {{ old('status', $campaign->status) === 'queued' ? 'selected' : '' }}>Queued</option>
                                        <option value="paused" {{ old('status', $campaign->status) === 'paused' ? 'selected' : '' }}>Paused</option>
                                    </select>
                                </div>
                                <div>
                                    <x-input-label for="scheduled_at" value="Scheduled Send" />
                                    <input type="datetime-local" id="scheduled_at" name="scheduled_at"
                                           x-model="scheduledAt"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                            </div>
                        </div>

                        {{-- Card: Email Basics --}}
                        <div class="bg-white border border-gray-200 rounded-lg p-5 space-y-4">
                            <h3 class="text-sm font-semibold text-gray-700">Email Basics</h3>
                            <div>
                                <x-input-label for="subject_line" value="Subject Line" />
                                <input type="text" id="subject_line" name="subject_line"
                                       value="{{ old('subject_line', $campaign->subject_line) }}"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div>
                                <x-input-label for="preheader" value="Preheader (preview text)" />
                                <input type="text" id="preheader" name="preheader"
                                       value="{{ old('preheader', $campaign->preheader) }}"
                                       maxlength="500"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div>
                                <x-input-label for="mailerlite_group_id" value="MailerLite Subscriber Group" />
                                @if(!empty($mailerliteGroups))
                                    <select id="mailerlite_group_id" name="mailerlite_group_id"
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">— select group —</option>
                                        @foreach($mailerliteGroups as $group)
                                            <option value="{{ $group['id'] }}"
                                                    {{ old('mailerlite_group_id', $campaign->mailerlite_group_id) == $group['id'] ? 'selected' : '' }}>
                                                {{ $group['name'] }} ({{ number_format($group['active_count'] ?? 0) }})
                                            </option>
                                        @endforeach
                                    </select>
                                @else
                                    <input type="text" id="mailerlite_group_id" name="mailerlite_group_id"
                                           value="{{ old('mailerlite_group_id', $campaign->mailerlite_group_id) }}"
                                           placeholder="Group ID (MailerLite API key not configured)"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @endif
                            </div>
                        </div>

                        {{-- Card: Content Top --}}
                        <div class="bg-white border border-gray-200 rounded-lg p-5 space-y-4">
                            <h3 class="text-sm font-semibold text-gray-700">Content — Top</h3>
                            <div>
                                <x-input-label for="headline_top" value="Headline" />
                                <input type="text" id="headline_top" name="headline_top"
                                       value="{{ old('headline_top', $campaign->headline_top) }}"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div>
                                <x-input-label for="paragraph_top1" value="Paragraph 1" />
                                <p class="text-xs text-gray-400 mt-0.5 mb-1">HTML supported. After "Hi [name] -"</p>
                                <textarea id="paragraph_top1" name="paragraph_top1" rows="4"
                                          class="block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('paragraph_top1', $campaign->paragraph_top1) }}</textarea>
                            </div>
                            <div>
                                <x-input-label for="paragraph_top2" value="Paragraph 2" />
                                <textarea id="paragraph_top2" name="paragraph_top2" rows="3"
                                          class="block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('paragraph_top2', $campaign->paragraph_top2) }}</textarea>
                            </div>
                            <div>
                                <x-input-label for="url1" value="CTA Button URL" />
                                <input type="url" id="url1" name="url1"
                                       value="{{ old('url1', $campaign->url1) }}"
                                       placeholder="https://screenplayreaders.com/..."
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                        </div>

                        {{-- Card: Promo Image --}}
                        <div class="bg-white border border-gray-200 rounded-lg p-5 space-y-4">
                            <h3 class="text-sm font-semibold text-gray-700">Promotional Image <span class="text-gray-400 font-normal">(optional)</span></h3>

                            <input type="hidden" name="image_url"  x-model="imageUrl">
                            <input type="hidden" name="image_path" x-model="imagePath">

                            {{-- Upload area --}}
                            <div class="flex items-center gap-4">
                                <label class="cursor-pointer flex items-center gap-2 px-3 py-2 border border-gray-300 rounded text-sm text-gray-600 hover:bg-gray-50">
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                    <span x-text="imageUploading ? 'Uploading...' : 'Upload image'"></span>
                                    <input type="file" accept="image/*" class="sr-only" @change="uploadImage($el)" :disabled="imageUploading">
                                </label>
                                <span class="text-gray-400 text-sm">or</span>
                                <input type="text" x-model="imageUrl"
                                       placeholder="Paste image URL"
                                       class="flex-1 border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>

                            {{-- Thumbnail preview --}}
                            <div x-show="imageUrl" x-cloak class="relative inline-block">
                                <img :src="imageUrl" alt="" class="max-h-32 rounded border border-gray-200">
                                <button type="button" @click="imageUrl=''; imagePath='';"
                                        class="absolute -top-2 -right-2 w-5 h-5 bg-red-500 text-white rounded-full text-xs flex items-center justify-center hover:bg-red-600">✕</button>
                            </div>
                        </div>

                        {{-- Card: Coupon --}}
                        <div class="bg-white border border-gray-200 rounded-lg p-5 space-y-4">
                            <h3 class="text-sm font-semibold text-gray-700">Coupon</h3>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="col-span-2">
                                    <x-input-label for="coupon_code" value="Coupon Code" />
                                    <div class="mt-1 flex gap-2">
                                        <input type="text" id="coupon_code" name="coupon_code"
                                               x-model="couponCode"
                                               placeholder="e.g. SR-SUMMER25"
                                               class="flex-1 border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500 uppercase">
                                        <button type="button" @click="generateCode()"
                                                class="shrink-0 px-3 py-1.5 text-xs bg-gray-100 border border-gray-300 rounded hover:bg-gray-200 text-gray-600">
                                            Auto-generate
                                        </button>
                                    </div>
                                    @if($campaign->woo_coupon_id)
                                        <p class="mt-1 text-xs text-green-600">WooCommerce coupon created (ID: {{ $campaign->woo_coupon_id }})</p>
                                    @endif
                                </div>
                                <div>
                                    <x-input-label for="coupon_amount" value="Amount" />
                                    <input type="number" id="coupon_amount" name="coupon_amount"
                                           value="{{ old('coupon_amount', $campaign->coupon_amount) }}"
                                           step="0.01" min="0"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <x-input-label for="coupon_type" value="Type" />
                                    <select id="coupon_type" name="coupon_type"
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="percent"   {{ old('coupon_type', $campaign->coupon_type) === 'percent'   ? 'selected' : '' }}>Percent (%)</option>
                                        <option value="fixed_cart"{{ old('coupon_type', $campaign->coupon_type) === 'fixed_cart' ? 'selected' : '' }}>Fixed ($)</option>
                                    </select>
                                </div>
                                <div>
                                    <x-input-label for="coupon_duration_days" value="Expires after (days)" />
                                    <input type="number" id="coupon_duration_days" name="coupon_duration_days"
                                           x-model.number="couponDays"
                                           min="1" placeholder="e.g. 7"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <p class="mt-1 text-xs text-indigo-600" x-show="expiryPreview" x-text="'Expires: ' + expiryPreview"></p>
                                </div>
                                <div class="col-span-2"
                                     x-data="{
                                        open: false,
                                        search: '',
                                        selected: @js(array_values(array_map('strval', $selectedProductIds))),
                                        products: @js($wooProducts),
                                        get filtered() {
                                            if (!this.search) return this.products;
                                            const q = this.search.toLowerCase();
                                            return this.products.filter(p =>
                                                p.name.toLowerCase().includes(q) || String(p.id).includes(q)
                                            );
                                        },
                                        toggle(id) {
                                            id = String(id);
                                            this.selected.includes(id)
                                                ? this.selected = this.selected.filter(s => s !== id)
                                                : this.selected.push(id);
                                        },
                                        isSelected(id) { return this.selected.includes(String(id)); },
                                        label(id) {
                                            const p = this.products.find(p => String(p.id) === String(id));
                                            return p ? p.name : 'ID ' + id;
                                        },
                                        get commaIds() { return this.selected.join(','); }
                                     }">
                                    <x-input-label value="Restrict Coupon to Products" />
                                    <p class="text-xs text-gray-400 mb-1">Leave empty to apply sitewide.</p>

                                    {{-- Hidden field that stores the comma-separated IDs --}}
                                    <input type="hidden" name="coupon_product_ids" :value="commaIds">

                                    @if(!empty($wooProducts))
                                        {{-- Selected tags --}}
                                        <div class="flex flex-wrap gap-1 mb-1.5 min-h-[24px]">
                                            <template x-for="id in selected" :key="id">
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-indigo-100 text-indigo-700 rounded text-xs">
                                                    <span x-text="label(id)"></span>
                                                    <button type="button" @click="toggle(id)"
                                                            class="text-indigo-400 hover:text-indigo-700 leading-none">&times;</button>
                                                </span>
                                            </template>
                                        </div>

                                        {{-- Dropdown trigger --}}
                                        <div class="relative">
                                            <button type="button"
                                                    @click="open = !open"
                                                    class="w-full flex items-center justify-between px-3 py-2 border border-gray-300 rounded-md text-sm text-gray-600 bg-white hover:bg-gray-50 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                                <span x-text="selected.length ? selected.length + ' product(s) selected' : 'Select products…'"></span>
                                                <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                                </svg>
                                            </button>

                                            <div x-show="open" x-cloak
                                                 @click.outside="open = false"
                                                 class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg">
                                                <div class="p-2 border-b border-gray-100">
                                                    <input type="text" x-model="search"
                                                           placeholder="Search products…"
                                                           @click.stop
                                                           class="w-full border-gray-300 rounded text-xs focus:border-indigo-500 focus:ring-indigo-500 py-1.5">
                                                </div>
                                                <div class="max-h-52 overflow-y-auto">
                                                    <template x-for="product in filtered" :key="product.id">
                                                        <label class="flex items-center gap-2.5 px-3 py-2 hover:bg-gray-50 cursor-pointer">
                                                            <input type="checkbox"
                                                                   :checked="isSelected(product.id)"
                                                                   @change="toggle(product.id)"
                                                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 shrink-0">
                                                            <span class="text-sm text-gray-700 leading-tight">
                                                                <span x-text="product.name"></span>
                                                                <span class="text-gray-400 text-xs ml-1" x-text="product.price ? '($' + product.price + ')' : ''"></span>
                                                                <span class="text-gray-300 text-xs ml-1" x-text="'#' + product.id"></span>
                                                            </span>
                                                        </label>
                                                    </template>
                                                    <p x-show="filtered.length === 0" class="px-3 py-2 text-xs text-gray-400">No products match.</p>
                                                </div>
                                                <div class="p-2 border-t border-gray-100 flex justify-between">
                                                    <button type="button" @click="selected = []" class="text-xs text-gray-400 hover:text-gray-600">Clear all</button>
                                                    <button type="button" @click="open = false" class="text-xs text-indigo-600 hover:underline">Done</button>
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        {{-- Fallback text input when WC products can't be fetched --}}
                                        <input type="text" name="coupon_product_ids"
                                               value="{{ old('coupon_product_ids', $productIds) }}"
                                               placeholder="e.g. 55560,55561 (blank = sitewide)"
                                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Card: Content Bottom --}}
                        <div class="bg-white border border-gray-200 rounded-lg p-5 space-y-4">
                            <h3 class="text-sm font-semibold text-gray-700">Content — Bottom</h3>
                            <div>
                                <x-input-label for="headline_bottom" value="Headline" />
                                <input type="text" id="headline_bottom" name="headline_bottom"
                                       value="{{ old('headline_bottom', $campaign->headline_bottom) }}"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div>
                                <x-input-label for="paragraph_bottom" value="Paragraph" />
                                <textarea id="paragraph_bottom" name="paragraph_bottom" rows="4"
                                          class="block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('paragraph_bottom', $campaign->paragraph_bottom) }}</textarea>
                            </div>
                        </div>

                        {{-- Save / Delete row --}}
                        <div class="flex items-center justify-between">
                            <button type="submit"
                                    class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">
                                {{ $isEdit ? 'Save Changes' : 'Create Campaign' }}
                            </button>
                            <div class="flex items-center gap-3">
                                @if($isEdit && $campaign->status !== 'sent')
                                    <form action="{{ route('marketing.email-campaigns.duplicate', $campaign) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="text-sm text-gray-500 hover:text-gray-700">Duplicate</button>
                                    </form>
                                    <form action="{{ route('marketing.email-campaigns.destroy', $campaign) }}" method="POST"
                                          onsubmit="return confirm('Delete this campaign?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-sm text-red-400 hover:text-red-600">Delete</button>
                                    </form>
                                @endif
                            </div>
                        </div>

                        </div>{{-- /fields view --}}

                        {{-- HTML SOURCE view --}}
                        <div x-show="activeTab === 'html'" x-cloak class="space-y-4">

                            {{-- Toolbar --}}
                            <div class="flex items-center gap-2 flex-wrap">
                                <button type="button" @click="generateFromFields()"
                                        :disabled="previewLoading"
                                        class="px-3 py-1.5 text-sm bg-indigo-600 text-white rounded hover:bg-indigo-700 disabled:opacity-50">
                                    <span x-show="!previewLoading">Generate from fields</span>
                                    <span x-show="previewLoading" x-cloak>Generating…</span>
                                </button>

                                {{-- Load Template dropdown --}}
                                <div class="relative">
                                    <button type="button" @click="showLoadDropdown = !showLoadDropdown"
                                            class="px-3 py-1.5 text-sm border border-gray-300 rounded text-gray-600 hover:bg-gray-50 flex items-center gap-1.5">
                                        Load Template
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </button>
                                    <div x-show="showLoadDropdown" x-cloak
                                         @click.outside="showLoadDropdown = false"
                                         class="absolute z-30 mt-1 left-0 w-64 bg-white border border-gray-200 rounded-md shadow-lg">
                                        <template x-if="templates.length === 0">
                                            <p class="px-3 py-3 text-xs text-gray-400">No templates saved yet.</p>
                                        </template>
                                        <template x-for="tpl in templates" :key="tpl.id">
                                            <div class="flex items-center gap-2 px-3 py-2 hover:bg-gray-50 border-b border-gray-100 last:border-0">
                                                <button type="button"
                                                        @click="loadTemplate(tpl.id)"
                                                        class="flex-1 text-left text-sm text-gray-700 truncate"
                                                        x-text="tpl.name"></button>
                                                <button type="button"
                                                        @click.stop="deleteTemplate(tpl.id, tpl.name)"
                                                        class="shrink-0 text-xs text-red-400 hover:text-red-600 leading-none">&times;</button>
                                            </div>
                                        </template>
                                    </div>
                                </div>

                                {{-- Save as Template --}}
                                <button type="button" @click="showSaveModal = true"
                                        x-show="htmlSource" x-cloak
                                        class="px-3 py-1.5 text-sm border border-gray-300 rounded text-gray-600 hover:bg-gray-50">
                                    Save as Template
                                </button>

                                <button type="button" @click="htmlSource = ''; refreshPreview()"
                                        x-show="htmlSource" x-cloak
                                        class="px-3 py-1.5 text-sm border border-red-200 rounded text-red-400 hover:bg-red-50">
                                    Clear
                                </button>

                                <span class="ml-auto text-xs text-gray-400"
                                      x-text="htmlSource ? htmlSource.length.toLocaleString() + ' chars' : ''"></span>
                            </div>

                            {{-- Empty state --}}
                            <div x-show="!htmlSource"
                                 class="px-3 py-2 bg-gray-50 border border-gray-200 rounded text-xs text-gray-500">
                                No custom HTML yet. Click "Generate from fields" to create an editable copy of the template, or paste HTML directly below.
                            </div>

                            {{-- Code editor --}}
                            <textarea name="custom_html"
                                      x-model="htmlSource"
                                      @keydown.tab.prevent="
                                          const s = $el.selectionStart;
                                          const e = $el.selectionEnd;
                                          $el.value = $el.value.substring(0, s) + '    ' + $el.value.substring(e);
                                          $el.selectionStart = $el.selectionEnd = s + 4;
                                          htmlSource = $el.value;
                                      "
                                      rows="32"
                                      placeholder="Paste or generate HTML here…"
                                      spellcheck="false"
                                      class="block w-full font-mono text-xs border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 resize-y leading-relaxed"></textarea>

                            {{-- Save / Delete row (so user doesn't need to switch tabs) --}}
                            <div class="flex items-center justify-between">
                                <button type="submit"
                                        class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">
                                    {{ $isEdit ? 'Save Changes' : 'Create Campaign' }}
                                </button>
                                <div class="flex items-center gap-3">
                                    @if($isEdit && $campaign->status !== 'sent')
                                        <form action="{{ route('marketing.email-campaigns.duplicate', $campaign) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="text-sm text-gray-500 hover:text-gray-700">Duplicate</button>
                                        </form>
                                        <form action="{{ route('marketing.email-campaigns.destroy', $campaign) }}" method="POST"
                                              onsubmit="return confirm('Delete this campaign?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-sm text-red-400 hover:text-red-600">Delete</button>
                                        </form>
                                    @endif
                                </div>
                            </div>

                        </div>{{-- /html source view --}}

                    </div>{{-- /left column --}}

                    {{-- RIGHT COLUMN: live preview (below form on mobile, sticky sidebar on desktop) --}}
                    <div class="w-full lg:w-[480px] lg:shrink-0 lg:sticky lg:top-6">

                        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                            {{-- Preview toolbar --}}
                            <div class="flex items-center gap-2 px-3 py-2 border-b border-gray-200 bg-gray-50">
                                <span class="text-xs font-medium text-gray-500 mr-1">Preview</span>
                                <button type="button" @click="previewView='desktop'"
                                        :class="previewView==='desktop' ? 'bg-gray-800 text-white' : 'bg-white text-gray-600 border border-gray-300'"
                                        class="px-2 py-1 text-xs rounded transition-colors">Desktop</button>
                                <button type="button" @click="previewView='mobile'"
                                        :class="previewView==='mobile' ? 'bg-gray-800 text-white' : 'bg-white text-gray-600 border border-gray-300'"
                                        class="px-2 py-1 text-xs rounded transition-colors">Mobile</button>
                                <button type="button" @click="refreshPreview()"
                                        :disabled="previewLoading"
                                        class="ml-auto px-3 py-1 text-xs bg-indigo-600 text-white rounded hover:bg-indigo-700 disabled:opacity-50">
                                    <span x-show="!previewLoading">Refresh</span>
                                    <span x-show="previewLoading" x-cloak>Refreshing…</span>
                                </button>
                            </div>

                            {{-- Preview iframe — full viewport height on desktop, fixed 500px on mobile --}}
                            <div class="overflow-auto bg-gray-100 p-2 h-[500px] lg:h-[calc(100vh-180px)]">
                                <div :class="previewView === 'mobile' ? 'w-[390px] mx-auto' : 'w-full'">
                                    <iframe
                                        :srcdoc="previewHtml"
                                        sandbox="allow-same-origin"
                                        class="w-full bg-white rounded border border-gray-200 h-[460px] lg:h-[calc(100vh-220px)]"
                                        scrolling="yes">
                                    </iframe>
                                </div>
                            </div>
                        </div>

                    </div>{{-- /right column --}}

                </div>{{-- /flex columns --}}

                {{-- Save as Template modal --}}
                <div x-show="showSaveModal" x-cloak
                     class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
                     @keydown.escape.window="showSaveModal = false">
                    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-sm mx-4" @click.stop>
                        <h3 class="text-sm font-semibold text-gray-800 mb-3">Save as Template</h3>
                        <input type="text"
                               x-model="templateName"
                               placeholder="Template name…"
                               @keydown.enter="saveAsTemplate()"
                               x-effect="if (showSaveModal) $nextTick(() => $el.focus())"
                               class="block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500 mb-4">
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="showSaveModal = false"
                                    class="px-3 py-1.5 text-sm text-gray-600 border border-gray-300 rounded hover:bg-gray-50">
                                Cancel
                            </button>
                            <button type="button" @click="saveAsTemplate()"
                                    :disabled="!templateName.trim()"
                                    class="px-3 py-1.5 text-sm bg-indigo-600 text-white rounded hover:bg-indigo-700 disabled:opacity-50">
                                Save Template
                            </button>
                        </div>
                    </div>
                </div>

            </form>

        </div>
    </div>
</x-app-layout>
