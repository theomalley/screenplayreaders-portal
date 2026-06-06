<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Email Campaigns</h2>
            <a href="{{ route('marketing.email-campaigns.create') }}"
               class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">
                + New Campaign
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-4 px-4 py-2 bg-green-50 border border-green-200 text-green-700 text-sm rounded">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="mb-4 px-4 py-2 bg-red-50 border border-red-200 text-red-700 text-sm rounded">{{ session('error') }}</div>
            @endif

            <div x-data="{ tab: '{{ $queued->count() ? 'queue' : ($drafts->count() ? 'drafts' : 'sent') }}' }">

                {{-- Tabs --}}
                <div class="flex gap-1 border-b border-gray-200 mb-6">
                    <button @click="tab='queue'"
                            :class="tab==='queue' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors">
                        Queue
                        @if($queued->count())
                            <span class="ml-1.5 px-1.5 py-0.5 text-xs bg-indigo-100 text-indigo-700 rounded-full">{{ $queued->count() }}</span>
                        @endif
                    </button>
                    <button @click="tab='drafts'"
                            :class="tab==='drafts' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors">
                        Drafts
                        @if($drafts->count())
                            <span class="ml-1.5 px-1.5 py-0.5 text-xs bg-gray-100 text-gray-600 rounded-full">{{ $drafts->count() }}</span>
                        @endif
                    </button>
                    <button @click="tab='sent'"
                            :class="tab==='sent' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors">
                        Sent
                        @if($sent->count())
                            <span class="ml-1.5 px-1.5 py-0.5 text-xs bg-gray-100 text-gray-600 rounded-full">{{ $sent->count() }}</span>
                        @endif
                    </button>
                </div>

                {{-- QUEUE TAB --}}
                <div x-show="tab==='queue'" x-cloak
                     x-data="{
                        campaigns: @js($queued->map(fn($c) => ['id' => $c->id, 'name' => $c->campaign_name, 'subject' => $c->subject_line, 'scheduled' => $c->scheduled_at?->format('M j, Y g:i A')])->values()->toArray()),
                        dragging: null,
                        dragOver: null,
                        start(idx)  { this.dragging = idx; },
                        enter(idx)  { this.dragOver = idx; },
                        end()       { this.dragging = null; this.dragOver = null; },
                        drop(targetIdx) {
                            if (this.dragging === null || this.dragging === targetIdx) { this.end(); return; }
                            const item = this.campaigns.splice(this.dragging, 1)[0];
                            this.campaigns.splice(targetIdx, 0, item);
                            this.saveOrder();
                            this.end();
                        },
                        saveOrder() {
                            fetch('{{ route('marketing.email-campaigns.reorder') }}', {
                                method: 'PATCH',
                                headers: {
                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({ order: this.campaigns.map(c => c.id) })
                            });
                        }
                     }">

                    @if($queued->isEmpty())
                        <p class="text-sm text-gray-400 py-8 text-center">No campaigns in the queue. Add one from Drafts or create a new campaign.</p>
                    @else
                        <p class="text-xs text-gray-400 mb-3">Drag rows to reorder the send queue.</p>
                        <div class="space-y-2">
                            <template x-for="(campaign, idx) in campaigns" :key="campaign.id">
                                <div draggable="true"
                                     @dragstart="start(idx)"
                                     @dragenter.prevent="enter(idx)"
                                     @dragover.prevent
                                     @drop.prevent="drop(idx)"
                                     @dragend="end()"
                                     :class="{
                                        'opacity-40': dragging === idx,
                                        'ring-2 ring-indigo-400': dragOver === idx && dragging !== idx
                                     }"
                                     class="flex items-center gap-3 bg-white border border-gray-200 rounded-lg px-4 py-3 cursor-grab select-none transition-all">
                                    <span class="text-gray-300 shrink-0">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M9 5a1 1 0 110 2 1 1 0 010-2zm6 0a1 1 0 110 2 1 1 0 010-2zM9 11a1 1 0 110 2 1 1 0 010-2zm6 0a1 1 0 110 2 1 1 0 010-2zM9 17a1 1 0 110 2 1 1 0 010-2zm6 0a1 1 0 110 2 1 1 0 010-2z"/>
                                        </svg>
                                    </span>
                                    <span class="text-xs font-medium text-gray-400 w-5 text-center" x-text="idx + 1"></span>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-800 truncate" x-text="campaign.name"></p>
                                        <p class="text-xs text-gray-400 truncate" x-text="campaign.subject || '(no subject)'"></p>
                                    </div>
                                    <div class="shrink-0 text-right">
                                        <p class="text-xs text-gray-500" x-text="campaign.scheduled || 'No schedule'"></p>
                                    </div>
                                    <div class="shrink-0 flex items-center gap-2">
                                        {{-- These action links use the campaign ID from the original $queued collection --}}
                                        <a :href="'/marketing/email-campaigns/' + campaign.id + '/edit'"
                                           class="text-xs text-indigo-600 hover:underline">Edit</a>
                                        <form :action="'/marketing/email-campaigns/' + campaign.id + '/status'" method="POST" class="inline">
                                            @csrf
                                            <input type="hidden" name="status" value="paused">
                                            <button type="submit" class="text-xs text-amber-600 hover:underline">Pause</button>
                                        </form>
                                    </div>
                                </div>
                            </template>
                        </div>
                    @endif
                </div>

                {{-- DRAFTS TAB --}}
                <div x-show="tab==='drafts'" x-cloak>
                    @if($drafts->isEmpty())
                        <p class="text-sm text-gray-400 py-8 text-center">No drafts. <a href="{{ route('marketing.email-campaigns.create') }}" class="text-indigo-600 hover:underline">Create one.</a></p>
                    @else
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach($drafts as $campaign)
                                <div class="bg-white border border-gray-200 rounded-lg p-4 flex flex-col gap-2">
                                    <div class="flex items-start justify-between gap-2">
                                        <p class="text-sm font-medium text-gray-800 leading-snug">{{ $campaign->campaign_name }}</p>
                                        @if($campaign->status === 'paused')
                                            <span class="shrink-0 text-xs px-1.5 py-0.5 bg-amber-100 text-amber-700 rounded">Paused</span>
                                        @else
                                            <span class="shrink-0 text-xs px-1.5 py-0.5 bg-gray-100 text-gray-500 rounded">Draft</span>
                                        @endif
                                    </div>
                                    <p class="text-xs text-gray-400 truncate">{{ $campaign->subject_line ?: '(no subject)' }}</p>
                                    @if($campaign->test_sent_at)
                                        <p class="text-xs text-green-600">Test sent {{ $campaign->test_sent_at->diffForHumans() }}</p>
                                    @endif
                                    <div class="flex items-center gap-3 mt-auto pt-2 border-t border-gray-100 flex-wrap">
                                        <a href="{{ route('marketing.email-campaigns.edit', $campaign) }}"
                                           class="text-xs text-indigo-600 hover:underline">Edit</a>
                                        <form action="{{ route('marketing.email-campaigns.status', $campaign) }}" method="POST" class="inline">
                                            @csrf
                                            <input type="hidden" name="status" value="queued">
                                            <button type="submit" class="text-xs text-green-600 hover:underline">Add to Queue</button>
                                        </form>
                                        <form action="{{ route('marketing.email-campaigns.duplicate', $campaign) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="text-xs text-gray-500 hover:underline">Duplicate</button>
                                        </form>
                                        <form action="{{ route('marketing.email-campaigns.destroy', $campaign) }}" method="POST" class="inline ml-auto"
                                              onsubmit="return confirm('Delete this draft?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-xs text-red-400 hover:underline">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- SENT TAB --}}
                <div x-show="tab==='sent'" x-cloak>
                    @if($sent->isEmpty())
                        <p class="text-sm text-gray-400 py-8 text-center">No sent campaigns yet.</p>
                    @else
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs text-gray-400 border-b border-gray-200">
                                    <th class="pb-2 font-medium">Campaign</th>
                                    <th class="pb-2 font-medium">Subject</th>
                                    <th class="pb-2 font-medium">Coupon</th>
                                    <th class="pb-2 font-medium">Sent</th>
                                    <th class="pb-2 font-medium"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($sent as $campaign)
                                <tr>
                                    <td class="py-2.5 pr-4 font-medium text-gray-800">{{ $campaign->campaign_name }}</td>
                                    <td class="py-2.5 pr-4 text-gray-500 max-w-xs truncate">{{ $campaign->subject_line }}</td>
                                    <td class="py-2.5 pr-4">
                                        @if($campaign->coupon_code)
                                            <code class="text-xs bg-gray-100 px-1.5 py-0.5 rounded font-mono">{{ $campaign->coupon_code }}</code>
                                        @else
                                            <span class="text-gray-300">—</span>
                                        @endif
                                    </td>
                                    <td class="py-2.5 pr-4 text-gray-500 whitespace-nowrap">
                                        {{ $campaign->live_sent_at?->format('M j, Y') ?? '—' }}
                                    </td>
                                    <td class="py-2.5 text-right">
                                        <div class="flex items-center justify-end gap-3">
                                            <a href="{{ route('marketing.email-campaigns.edit', $campaign) }}"
                                               class="text-xs text-indigo-600 hover:underline">View</a>
                                            <form action="{{ route('marketing.email-campaigns.duplicate', $campaign) }}" method="POST" class="inline">
                                                @csrf
                                                <button type="submit" class="text-xs text-gray-500 hover:underline">Duplicate</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>

            </div>{{-- /x-data tabs --}}
        </div>
    </div>
</x-app-layout>
