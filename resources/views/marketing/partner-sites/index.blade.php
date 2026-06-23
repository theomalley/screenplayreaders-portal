<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-center gap-3" x-data="{ showHelp: false }">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Partner Link Monitor</h2>
                <div class="relative">
                    <button type="button" @click="showHelp = !showHelp"
                            class="text-xs text-gray-400 hover:text-indigo-600 underline underline-offset-2">How it works</button>
                    <div x-show="showHelp" x-cloak @click.outside="showHelp = false" x-transition
                         class="absolute left-0 top-full mt-2 w-[420px] bg-white border border-gray-200 rounded-lg shadow-lg z-50 text-sm text-gray-600 p-5 space-y-3">
                        <h3 class="text-sm font-semibold text-gray-800 mb-2">How the Partner Link Monitor works</h3>
                        <p>Each partner site is checked on its configured interval. The monitor fetches the page, parses the HTML, and looks for any links pointing to <strong>screenplayreaders.com</strong>.</p>
                        <p>A site is <strong class="text-green-600">Up</strong> if at least one backlink is found, and <strong class="text-red-500">Down</strong> if the page errors or has no backlinks.</p>
                        <p class="font-medium text-gray-700 mt-1">Coupon auto-management</p>
                        <p>If a WooCommerce coupon code is assigned to a partner, the monitor enables or disables it automatically after each check:</p>
                        <ul class="list-disc pl-5 space-y-1 text-xs text-gray-500">
                            <li><strong>With an uptime threshold</strong> &mdash; coupon stays active while rolling uptime (last 20 checks) is at or above the threshold; paused when it drops below.</li>
                            <li><strong>Without a threshold</strong> &mdash; coupon is toggled on each individual check (active when up, paused when down).</li>
                        </ul>
                        <p class="text-xs text-gray-400 pt-1">Checks run via <code class="bg-gray-100 px-1 rounded">marketing:check-partner-links</code> every 5 min; only sites past their next-check time are processed.</p>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-3">
                {{-- Period dropdown --}}
                <form method="GET" action="{{ route('marketing.partner-sites.index') }}">
                    <select name="period" onchange="this.form.submit()"
                            class="text-sm border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        @foreach(\App\Http\Controllers\Marketing\PartnerSiteController::$PERIODS as $key => $label)
                            <option value="{{ $key }}" @selected($period === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </form>
                <button type="button"
                        onclick="window.dispatchEvent(new CustomEvent('open-partner-add'))"
                        class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">
                    + Add Partner
                </button>
            </div>
        </div>
    </x-slot>

    <div class="py-6"
         @open-partner-add.window="openAdd()"
         x-data="{
            // ---- modal state ----
            showModal: false,
            editing: null,
            form: { name: '', url: '', check_interval_minutes: 1440, active: true, notes: '', coupon_code: '', coupon_discount_type: 'percent', coupon_amount: '', coupon_uptime_threshold: '' },

            openAdd() {
                this.editing = null;
                this.form = { name: '', url: '', check_interval_minutes: 1440, active: true, notes: '', coupon_code: '', coupon_discount_type: 'percent', coupon_amount: '', coupon_uptime_threshold: '' };
                this.showModal = true;
            },
            openEdit(site) {
                this.editing = site;
                this.form = { ...site };
                this.showModal = true;
            },
            closeModal() { this.showModal = false; },

            // ---- per-row state ----
            expanded: {},
            checking: {},
            checkResults: {},  // site.id → latest check result after Check Now

            toggleExpand(id) {
                if (this.expanded[id]) {
                    this.expanded[id] = false;
                    return;
                }
                this.expanded[id] = true;
            },

            async checkNow(id) {
                this.checking[id] = true;
                try {
                    const r = await fetch('/marketing/partner-sites/' + id + '/check-now', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
                    });
                    const j = await r.json();
                    this.checkResults[id] = j.latest;
                    // If row is expanded, refresh history
                    if (this.expanded[id]) {
                        await this.loadHistory(id);
                    }
                } finally {
                    this.checking[id] = false;
                }
            },

            history: {},
            historyLoading: {},
            async loadHistory(id) {
                this.historyLoading[id] = true;
                const r = await fetch('/marketing/partner-sites/' + id + '/history', {
                    headers: { Accept: 'application/json' }
                });
                this.history[id] = await r.json();
                this.historyLoading[id] = false;
            },

            intervalLabel(mins) {
                if (mins < 60)   return mins + ' min';
                if (mins < 1440) return (mins / 60) + 'h';
                if (mins === 1440) return 'Daily';
                if (mins === 10080) return 'Weekly';
                return Math.round(mins / 1440) + ' days';
            },

            relBadge(link) {
                if (link.is_dofollow)  return { label: 'dofollow', cls: 'bg-green-100 text-green-700' };
                if (link.is_sponsored) return { label: 'sponsored', cls: 'bg-orange-100 text-orange-700' };
                if (link.is_ugc)       return { label: 'ugc', cls: 'bg-yellow-100 text-yellow-700' };
                return { label: 'nofollow', cls: 'bg-red-100 text-red-700' };
            }
         }">

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-4 px-4 py-2 bg-green-50 border border-green-200 text-green-700 text-sm rounded">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="mb-4 px-4 py-2 bg-red-50 border border-red-200 text-red-700 text-sm rounded">{{ session('error') }}</div>
            @endif

            @if($sites->isEmpty())
                <div class="text-center py-20 text-gray-400 text-sm">
                    <p class="mb-2 text-base font-medium text-gray-500">No partner sites yet.</p>
                    <p>Add a partner's URL and the monitor will check it for links back to screenplayreaders.com at your chosen interval.</p>
                </div>
            @else

            <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-gray-400 border-b border-gray-200 bg-gray-50">
                            <th class="px-4 py-3 font-medium">Partner</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Links Found</th>
                            <th class="px-4 py-3 font-medium">Uptime</th>
                            <th class="px-4 py-3 font-medium">Interval</th>
                            <th class="px-4 py-3 font-medium">Last Checked</th>
                            <th class="px-4 py-3 font-medium">Coupon</th>
                            <th class="px-4 py-3 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($sites as $site)
                        @php
                            $latest  = $site->latestCheck;
                            $siteStat = $stats[$site->id] ?? ['total' => 0, 'up' => 0, 'uptime' => null];
                        @endphp

                        {{-- Main row --}}
                        <tr class="hover:bg-gray-50 transition-colors"
                            :class="expanded[{{ $site->id }}] ? 'bg-indigo-50/40' : ''">

                            {{-- Partner name + URL --}}
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-800">{{ $site->name }}</div>
                                <a href="{{ $site->url }}" target="_blank" rel="noopener"
                                   class="text-xs text-gray-400 hover:text-indigo-600 hover:underline truncate block max-w-xs">
                                    {{ $site->url }}
                                </a>
                                @if(!$site->active)
                                    <span class="text-xs text-amber-600">Paused</span>
                                @endif
                            </td>

                            {{-- Status --}}
                            <td class="px-4 py-3">
                                <template x-if="checkResults[{{ $site->id }}] !== undefined">
                                    <span>
                                        <span x-show="checkResults[{{ $site->id }}]?.is_up"
                                              class="inline-flex items-center gap-1 text-xs font-medium text-green-700 bg-green-50 border border-green-200 rounded-full px-2 py-0.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-green-500 inline-block"></span> Up
                                        </span>
                                        <span x-show="!checkResults[{{ $site->id }}]?.is_up"
                                              class="inline-flex items-center gap-1 text-xs font-medium text-red-700 bg-red-50 border border-red-200 rounded-full px-2 py-0.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-red-500 inline-block"></span> Down
                                        </span>
                                    </span>
                                </template>
                                <template x-if="checkResults[{{ $site->id }}] === undefined">
                                    @if($latest)
                                        @if($latest->is_up)
                                            <span class="inline-flex items-center gap-1 text-xs font-medium text-green-700 bg-green-50 border border-green-200 rounded-full px-2 py-0.5">
                                                <span class="w-1.5 h-1.5 rounded-full bg-green-500 inline-block"></span> Up
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 text-xs font-medium text-red-700 bg-red-50 border border-red-200 rounded-full px-2 py-0.5">
                                                <span class="w-1.5 h-1.5 rounded-full bg-red-500 inline-block"></span> Down
                                            </span>
                                        @endif
                                    @else
                                        <span class="text-xs text-gray-400">—</span>
                                    @endif
                                </template>
                            </td>

                            {{-- Links found in latest check --}}
                            <td class="px-4 py-3">
                                <template x-if="checkResults[{{ $site->id }}] !== undefined">
                                    <span>
                                        <span x-show="checkResults[{{ $site->id }}]?.links_found?.length"
                                              class="text-sm font-medium text-gray-700"
                                              x-text="checkResults[{{ $site->id }}]?.links_found?.length + ' link(s)'"></span>
                                        <span x-show="!checkResults[{{ $site->id }}]?.links_found?.length"
                                              class="text-xs text-red-500">None</span>
                                    </span>
                                </template>
                                <template x-if="checkResults[{{ $site->id }}] === undefined">
                                    @if($latest && $latest->links_found)
                                        <span class="text-sm font-medium text-gray-700">{{ count($latest->links_found) }} link(s)</span>
                                        <div class="flex gap-1 flex-wrap mt-0.5">
                                            @php
                                                $dofollow = collect($latest->links_found)->where('is_dofollow', true)->count();
                                                $nofollow = collect($latest->links_found)->where('is_nofollow', true)->count();
                                                $sponsored = collect($latest->links_found)->where('is_sponsored', true)->count();
                                            @endphp
                                            @if($dofollow)
                                                <span class="text-xs px-1.5 py-0.5 bg-green-100 text-green-700 rounded">{{ $dofollow }} dofollow</span>
                                            @endif
                                            @if($nofollow)
                                                <span class="text-xs px-1.5 py-0.5 bg-red-100 text-red-700 rounded">{{ $nofollow }} nofollow</span>
                                            @endif
                                            @if($sponsored)
                                                <span class="text-xs px-1.5 py-0.5 bg-orange-100 text-orange-700 rounded">{{ $sponsored }} sponsored</span>
                                            @endif
                                        </div>
                                    @elseif($latest)
                                        <span class="text-xs text-red-500">None</span>
                                    @else
                                        <span class="text-xs text-gray-400">—</span>
                                    @endif
                                </template>
                            </td>

                            {{-- Uptime % --}}
                            <td class="px-4 py-3">
                                @if($siteStat['total'] > 0)
                                    @php $pct = $siteStat['uptime']; @endphp
                                    <span class="font-semibold text-sm {{ $pct >= 90 ? 'text-green-600' : ($pct >= 70 ? 'text-amber-600' : 'text-red-600') }}">
                                        {{ $pct }}%
                                    </span>
                                    <div class="w-24 h-1.5 bg-gray-100 rounded-full mt-1 overflow-hidden">
                                        <div class="h-full rounded-full {{ $pct >= 90 ? 'bg-green-500' : ($pct >= 70 ? 'bg-amber-400' : 'bg-red-400') }}"
                                             style="width: {{ $pct }}%"></div>
                                    </div>
                                    <p class="text-xs text-gray-400 mt-0.5">{{ $siteStat['up'] }}/{{ $siteStat['total'] }} checks</p>
                                @else
                                    <span class="text-xs text-gray-400">No data</span>
                                @endif
                            </td>

                            {{-- Check interval --}}
                            <td class="px-4 py-3 text-xs text-gray-500">
                                <span x-text="intervalLabel({{ $site->check_interval_minutes }})"></span>
                            </td>

                            {{-- Last checked --}}
                            <td class="px-4 py-3 text-xs text-gray-500">
                                <template x-if="checkResults[{{ $site->id }}] !== undefined">
                                    <span x-text="checkResults[{{ $site->id }}]?.checked_at || 'Just now'"></span>
                                </template>
                                <template x-if="checkResults[{{ $site->id }}] === undefined">
                                    {{ $latest ? $latest->checked_at->diffForHumans() : '—' }}
                                </template>
                                @if($latest && $latest->response_time_ms)
                                    <span class="text-gray-400">({{ $latest->response_time_ms }}ms)</span>
                                @endif
                            </td>

                            {{-- Coupon --}}
                            <td class="px-4 py-3 text-xs">
                                @if($site->coupon_code)
                                    <code class="font-mono text-gray-700">{{ $site->coupon_code }}</code>
                                    @if($site->coupon_amount !== null)
                                        <span class="ml-1 text-gray-500">
                                            ({{ $site->coupon_discount_type === 'fixed_cart' ? '$' . number_format($site->coupon_amount, 0) . ' off' : number_format($site->coupon_amount, 0) . '% off' }})
                                        </span>
                                    @endif
                                    @if($siteStat['total'] === 0)
                                        <span class="block mt-0.5 text-gray-400">not checked yet</span>
                                    @elseif($site->coupon_uptime_threshold !== null)
                                        @php
                                            $uptime   = $siteStat['uptime'] ?? 0;
                                            $thresh   = $site->coupon_uptime_threshold;
                                            $isActive = $uptime >= $thresh;
                                        @endphp
                                        @if($isActive)
                                            <span class="block mt-0.5 text-green-600 font-medium">active</span>
                                        @else
                                            <span class="block mt-0.5 text-red-400 font-medium">paused</span>
                                        @endif
                                        <span class="text-gray-400">{{ $uptime }}% / {{ $thresh }}% threshold</span>
                                    @else
                                        @if($latest && $latest->is_up)
                                            <span class="block mt-0.5 text-green-600 font-medium">active</span>
                                        @else
                                            <span class="block mt-0.5 text-red-400 font-medium">paused</span>
                                        @endif
                                        <span class="text-gray-400">per-check</span>
                                    @endif
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>

                            {{-- Actions --}}
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-2 flex-wrap">
                                    <button type="button"
                                            @click="checkNow({{ $site->id }})"
                                            :disabled="checking[{{ $site->id }}]"
                                            class="text-xs px-2 py-1 border border-gray-300 rounded hover:bg-gray-50 text-gray-600 disabled:opacity-50 whitespace-nowrap">
                                        <span x-show="!checking[{{ $site->id }}]">Check Now</span>
                                        <span x-show="checking[{{ $site->id }}]" x-cloak>Checking…</span>
                                    </button>
                                    <button type="button"
                                            @click="toggleExpand({{ $site->id }}); if (expanded[{{ $site->id }}]) loadHistory({{ $site->id }})"
                                            class="text-xs px-2 py-1 border border-gray-300 rounded hover:bg-gray-50 text-gray-600 whitespace-nowrap">
                                        <span x-text="expanded[{{ $site->id }}] ? 'Hide History' : 'History'"></span>
                                    </button>
                                    <button type="button"
                                            @click="openEdit(@js(['id' => $site->id, 'name' => $site->name, 'url' => $site->url, 'check_interval_minutes' => $site->check_interval_minutes, 'active' => $site->active, 'notes' => $site->notes ?? '', 'coupon_code' => $site->coupon_code ?? '', 'coupon_discount_type' => $site->coupon_discount_type ?? 'percent', 'coupon_amount' => $site->coupon_amount, 'coupon_uptime_threshold' => $site->coupon_uptime_threshold]))"
                                            class="text-xs text-indigo-600 hover:underline">Edit</button>
                                    <form action="{{ route('marketing.partner-sites.destroy', $site) }}" method="POST" class="inline"
                                          onsubmit="return confirm('Delete {{ addslashes($site->name) }} and all its check history?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-xs text-red-400 hover:underline">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>

                        {{-- Expanded history row --}}
                        <tr x-show="expanded[{{ $site->id }}]" x-cloak>
                            <td colspan="8" class="px-4 pb-4 pt-0 bg-gray-50">
                                <div class="border border-gray-200 rounded-lg overflow-hidden">
                                    <div class="px-4 py-2 bg-white border-b border-gray-200 flex items-center justify-between">
                                        <span class="text-xs font-medium text-gray-600">Last 50 checks — {{ $site->name }}</span>
                                        <button type="button" @click="loadHistory({{ $site->id }})"
                                                class="text-xs text-indigo-600 hover:underline">Refresh</button>
                                    </div>

                                    <div x-show="historyLoading[{{ $site->id }}]" class="px-4 py-6 text-center text-xs text-gray-400">Loading…</div>

                                    <div x-show="!historyLoading[{{ $site->id }}] && (!history[{{ $site->id }}] || history[{{ $site->id }}].length === 0)"
                                         class="px-4 py-6 text-center text-xs text-gray-400">No checks recorded yet.</div>

                                    <template x-if="!historyLoading[{{ $site->id }}] && history[{{ $site->id }}]?.length">
                                        <div class="overflow-x-auto max-h-72 overflow-y-auto">
                                            <table class="w-full text-xs">
                                                <thead class="sticky top-0 bg-gray-50 z-10">
                                                    <tr class="text-left text-gray-400 border-b border-gray-200">
                                                        <th class="px-3 py-2 font-medium">Checked</th>
                                                        <th class="px-3 py-2 font-medium">Status</th>
                                                        <th class="px-3 py-2 font-medium">HTTP</th>
                                                        <th class="px-3 py-2 font-medium">Time</th>
                                                        <th class="px-3 py-2 font-medium">Links Found</th>
                                                        <th class="px-3 py-2 font-medium">Error</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-100 bg-white">
                                                    <template x-for="chk in history[{{ $site->id }}]" :key="chk.checked_at">
                                                        <tr>
                                                            <td class="px-3 py-2 text-gray-500 whitespace-nowrap" x-text="chk.checked_at"></td>
                                                            <td class="px-3 py-2">
                                                                <span x-show="chk.is_up" class="text-green-600 font-medium">✓ Up</span>
                                                                <span x-show="!chk.is_up" class="text-red-500 font-medium">✗ Down</span>
                                                            </td>
                                                            <td class="px-3 py-2 text-gray-500" x-text="chk.http_status || '—'"></td>
                                                            <td class="px-3 py-2 text-gray-500" x-text="chk.response_time_ms ? chk.response_time_ms + 'ms' : '—'"></td>
                                                            <td class="px-3 py-2">
                                                                <template x-if="chk.links_found && chk.links_found.length">
                                                                    <div class="space-y-0.5">
                                                                        <template x-for="(lnk, li) in chk.links_found" :key="li">
                                                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                                                <span class="text-xs px-1.5 py-0.5 rounded font-medium"
                                                                                      :class="relBadge(lnk).cls"
                                                                                      x-text="relBadge(lnk).label"></span>
                                                                                <a :href="lnk.href" target="_blank" rel="noopener"
                                                                                   class="text-indigo-600 hover:underline truncate max-w-[200px]"
                                                                                   x-text="lnk.anchor_text || lnk.href"></a>
                                                                            </div>
                                                                        </template>
                                                                    </div>
                                                                </template>
                                                                <template x-if="!chk.links_found || !chk.links_found.length">
                                                                    <span class="text-red-400">None</span>
                                                                </template>
                                                            </td>
                                                            <td class="px-3 py-2 text-red-400 max-w-xs truncate" x-text="chk.error_message || ''"></td>
                                                        </tr>
                                                    </template>
                                                </tbody>
                                            </table>
                                        </div>
                                    </template>
                                </div>
                            </td>
                        </tr>

                        @endforeach
                    </tbody>
                </table>
            </div>

            @endif {{-- /sites.isEmpty --}}

        </div>

        {{-- Add / Edit Modal --}}
        <div x-show="showModal" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
             @keydown.escape.window="closeModal()">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md" @click.stop>
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200">
                    <h3 class="text-sm font-semibold text-gray-800" x-text="editing ? 'Edit Partner Site' : 'Add Partner Site'"></h3>
                    <button type="button" @click="closeModal()" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
                </div>

                {{-- Add form --}}
                <template x-if="!editing">
                    <form action="{{ route('marketing.partner-sites.store') }}" method="POST" class="p-5 space-y-4">
                        @csrf
                        @include('marketing.partner-sites._form')
                        <div class="flex justify-end gap-2 pt-2">
                            <button type="button" @click="closeModal()"
                                    class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded hover:bg-gray-50">Cancel</button>
                            <button type="submit"
                                    class="px-4 py-2 text-sm bg-indigo-600 text-white rounded hover:bg-indigo-700">Add Partner</button>
                        </div>
                    </form>
                </template>

                {{-- Edit form (separate so action URL can be dynamic) --}}
                <template x-if="editing">
                    <div class="p-5 space-y-4" x-data>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Partner Name</label>
                            <input type="text" x-model="form.name" required maxlength="255"
                                   class="block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Partner Page URL</label>
                            <input type="url" x-model="form.url" required maxlength="500"
                                   placeholder="https://partnersite.com/about"
                                   class="block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Check Interval (minutes)</label>
                            <div class="flex gap-2 items-center">
                                <input type="number" x-model.number="form.check_interval_minutes" min="5" max="43200" required
                                       class="w-28 border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <span class="text-xs text-gray-400">
                                    Common: 60 (hourly) · 1440 (daily) · 10080 (weekly)
                                </span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="checkbox" x-model="form.active" id="edit_active"
                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                            <label for="edit_active" class="text-sm text-gray-700">Active (monitoring enabled)</label>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">WooCommerce Coupon Code <span class="text-gray-400">(optional)</span></label>
                            <input type="text" x-model="form.coupon_code" maxlength="255"
                                   placeholder="e.g. PARTNER20"
                                   class="block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <p class="mt-1 text-xs text-gray-400">Created automatically in WooCommerce if it doesn't exist yet. Always set as combinable with other coupons.</p>
                        </div>
                        <div x-show="form.coupon_code" class="space-y-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Discount</label>
                                <div class="flex gap-2 items-center">
                                    <input type="number" x-model.number="form.coupon_amount"
                                           min="0" max="100" step="0.01" placeholder="0"
                                           class="w-24 border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <select x-model="form.coupon_discount_type"
                                            class="border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="percent">% off</option>
                                        <option value="fixed_cart">$ off (fixed)</option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Uptime Threshold <span class="text-gray-400">(optional, %)</span></label>
                                <div class="flex gap-2 items-center">
                                    <input type="number" x-model.number="form.coupon_uptime_threshold"
                                           min="0" max="100" step="1" placeholder="e.g. 75"
                                           class="w-24 border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <span class="text-xs text-gray-400">%</span>
                                </div>
                                <p class="mt-1 text-xs text-gray-400">Coupon stays active while rolling uptime is at or above this value. Leave empty to toggle on every individual check.</p>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Notes <span class="text-gray-400">(optional)</span></label>
                            <textarea x-model="form.notes" rows="2" maxlength="1000"
                                      class="block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <button type="button" @click="closeModal()"
                                    class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded hover:bg-gray-50">Cancel</button>
                            <button type="button"
                                    @click="
                                        const fd = new FormData();
                                        fd.append('_token', document.querySelector('meta[name=csrf-token]').content);
                                        fd.append('_method', 'PATCH');
                                        fd.append('name', form.name);
                                        fd.append('url', form.url);
                                        fd.append('check_interval_minutes', form.check_interval_minutes);
                                        fd.append('active', form.active ? '1' : '0');
                                        fd.append('notes', form.notes || '');
                                        fd.append('coupon_code', form.coupon_code || '');
                                        fd.append('coupon_discount_type', form.coupon_discount_type || 'percent');
                                        fd.append('coupon_amount', form.coupon_amount !== null && form.coupon_amount !== '' ? form.coupon_amount : '');
                                        fd.append('coupon_uptime_threshold', form.coupon_uptime_threshold !== null && form.coupon_uptime_threshold !== '' ? form.coupon_uptime_threshold : '');
                                        fetch('/marketing/partner-sites/' + editing.id, { method: 'POST', body: fd })
                                            .then(() => window.location.reload());
                                    "
                                    class="px-4 py-2 text-sm bg-indigo-600 text-white rounded hover:bg-indigo-700">
                                Save Changes
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>

    </div>
</x-app-layout>
