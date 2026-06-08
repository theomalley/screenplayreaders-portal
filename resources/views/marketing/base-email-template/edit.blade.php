<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Base Email Template</h2>
            <div class="flex items-center gap-2">
                <form action="{{ route('marketing.base-email-template.reset') }}" method="POST"
                      onsubmit="return confirm('Reset to the code default? Any saved customisations will be lost.')">
                    @csrf
                    <button type="submit"
                            class="px-3 py-1.5 text-sm border border-gray-300 rounded text-gray-600 hover:bg-gray-50">
                        Reset to default
                    </button>
                </form>
            </div>
        </div>
    </x-slot>

    @php
        // Variables the template can use — shown in the reference panel
        $vars = [
            ['token' => '$preheader',       'type' => 'escaped',  'desc' => 'Inbox preview text'],
            ['token' => '$headlineTop',     'type' => 'escaped',  'desc' => 'Top headline'],
            ['token' => '$paragraphTop1',   'type' => 'raw HTML', 'desc' => 'First body paragraph (after "Hi [name] -")'],
            ['token' => '$paragraphTop2',   'type' => 'raw HTML', 'desc' => 'Second body paragraph'],
            ['token' => '$couponCode',      'type' => 'escaped',  'desc' => 'Coupon code string (empty = no coupon)'],
            ['token' => '$couponExpiry',    'type' => 'escaped',  'desc' => 'Formatted expiry date, e.g. "July 15, 2026"'],
            ['token' => '$couponFinePrint', 'type' => 'escaped',  'desc' => '"Coupon expires …" fine-print line'],
            ['token' => '$url1',            'type' => 'escaped',  'desc' => 'CTA button URL'],
            ['token' => '$headlineBottom',  'type' => 'escaped',  'desc' => 'Bottom headline (empty = hidden)'],
            ['token' => '$paragraphBottom', 'type' => 'raw HTML', 'desc' => 'Bottom paragraph'],
            ['token' => '$imageUrl',        'type' => 'escaped',  'desc' => 'Promotional image URL (empty = hidden)'],
            ['token' => '$preview',         'type' => 'bool',     'desc' => 'true = show placeholder links; false = real MailerLite merge tags'],
        ];
    @endphp

    <div class="py-6"
         x-data="{
            template: @js($template),
            previewHtml: '',
            previewView: 'desktop',
            previewLoading: false,
            showVars: false,

            async refreshPreview() {
                this.previewLoading = true;
                const r = await fetch('{{ route('marketing.base-email-template.preview') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({ template: this.template })
                });
                this.previewHtml = await r.text();
                this.previewLoading = false;
            },

            _debounce: null,
            debouncedRefresh() {
                clearTimeout(this._debounce);
                this._debounce = setTimeout(() => this.refreshPreview(), 600);
            }
         }"
         x-init="refreshPreview()">

        <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-4 px-4 py-2 bg-green-50 border border-green-200 text-green-700 text-sm rounded">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="mb-4 px-4 py-2 bg-red-50 border border-red-200 text-red-700 text-sm rounded">{{ session('error') }}</div>
            @endif

            <div class="mb-4 px-3 py-2 bg-blue-50 border border-blue-200 rounded text-xs text-blue-800 leading-relaxed">
                <strong>This is the structural template used by all campaigns that don't have custom HTML.</strong>
                It's Blade syntax — you can use <code>@{{ $variable }}</code>, <code>@{!! $rawHtml !!}</code>, and <code>@@if / @@endif</code> directives.
                Changes here affect every future send; existing custom-HTML campaigns are unaffected.
            </div>

            <div class="flex flex-col lg:flex-row gap-6 lg:items-start">

                {{-- LEFT: editor --}}
                <div class="flex-1 min-w-0 space-y-4">

                    {{-- Variable reference toggle --}}
                    <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                        <button type="button" @click="showVars = !showVars"
                                class="w-full flex items-center justify-between px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            <span>Available variables</span>
                            <svg class="w-4 h-4 text-gray-400 transition-transform" :class="showVars ? 'rotate-180' : ''"
                                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div x-show="showVars" x-cloak class="border-t border-gray-100">
                            <table class="w-full text-xs">
                                <thead>
                                    <tr class="bg-gray-50 text-gray-500 uppercase tracking-wide text-left">
                                        <th class="px-4 py-2 font-medium">Variable</th>
                                        <th class="px-4 py-2 font-medium">Type</th>
                                        <th class="px-4 py-2 font-medium">Description</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach($vars as $v)
                                    <tr>
                                        <td class="px-4 py-2 font-mono text-indigo-700">{{ $v['token'] }}</td>
                                        <td class="px-4 py-2 text-gray-500">{{ $v['type'] }}</td>
                                        <td class="px-4 py-2 text-gray-600">{{ $v['desc'] }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Blade editor --}}
                    <form action="{{ route('marketing.base-email-template.update') }}" method="POST">
                        @csrf
                        <textarea name="template"
                                  x-model="template"
                                  @input="debouncedRefresh()"
                                  @keydown.tab.prevent="
                                      const s = $el.selectionStart;
                                      const e = $el.selectionEnd;
                                      $el.value = $el.value.substring(0, s) + '    ' + $el.value.substring(e);
                                      $el.selectionStart = $el.selectionEnd = s + 4;
                                      template = $el.value;
                                  "
                                  rows="42"
                                  spellcheck="false"
                                  class="block w-full font-mono text-xs border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 resize-y leading-relaxed"></textarea>

                        <div class="mt-3 flex items-center gap-3">
                            <button type="submit"
                                    class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">
                                Save Template
                            </button>
                            <span class="text-xs text-gray-400"
                                  x-text="template.length.toLocaleString() + ' chars'"></span>
                        </div>
                    </form>

                </div>

                {{-- RIGHT: preview --}}
                <div class="w-full lg:w-[480px] lg:shrink-0 lg:sticky lg:top-6">
                    <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                        <div class="flex items-center gap-2 px-3 py-2 border-b border-gray-200 bg-gray-50">
                            <span class="text-xs font-medium text-gray-500 mr-1">Preview</span>
                            <button type="button" @click="previewView='desktop'"
                                    :class="previewView==='desktop' ? 'bg-gray-800 text-white' : 'bg-white text-gray-600 border border-gray-300'"
                                    class="px-2 py-1 text-xs rounded transition-colors">Desktop</button>
                            <button type="button" @click="previewView='mobile'"
                                    :class="previewView==='mobile' ? 'bg-gray-800 text-white' : 'bg-white text-gray-600 border border-gray-300'"
                                    class="px-2 py-1 text-xs rounded transition-colors">Mobile</button>
                            <span class="text-xs text-gray-400 italic ml-1">sample data</span>
                            <button type="button" @click="refreshPreview()"
                                    :disabled="previewLoading"
                                    class="ml-auto px-3 py-1 text-xs bg-indigo-600 text-white rounded hover:bg-indigo-700 disabled:opacity-50">
                                <span x-show="!previewLoading">Refresh</span>
                                <span x-show="previewLoading" x-cloak>Refreshing…</span>
                            </button>
                        </div>
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
                </div>

            </div>
        </div>
    </div>

</x-app-layout>
