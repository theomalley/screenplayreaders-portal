{{--
    Reader followup question row.
    Variables: $fq (FollowupQuestion), $fqA (Assignment), $fqDeadline (Carbon|null), $appTimezone
--}}
<div x-data="{ open: false, response: '', submitting: false, done: false, csrf: document.querySelector('meta[name=csrf-token]')?.content ?? '' }"
     x-show="!done"
     class="mb-3 rounded-lg border-2 {{ $fq->status === 'answered' ? 'border-green-300 bg-green-50' : 'border-amber-300 bg-amber-50' }}">

    {{-- Row header (clickable) --}}
    <div @click="open = !open"
         class="flex items-center gap-3 px-4 py-3 cursor-pointer">
        <div class="flex-1 min-w-0">
            <div class="text-sm font-semibold text-gray-800 flex items-center gap-2 flex-wrap">
                <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-400 text-amber-900 uppercase leading-none">Followup</span>
                {{ $fqA?->script_title ?? '—' }}
            </div>
            <div class="text-xs text-gray-500 mt-0.5">
                {{ $fqA?->order_number }} · {{ ucwords(str_replace('_', ' ', $fqA?->assignment_type ?? '')) }}
            </div>
        </div>

        @if ($fqDeadline && $fq->status === 'unanswered')
            <div x-data="rushCountdown('{{ $fqDeadline->utc()->toIso8601String() }}', @js($fqDeadline->setTimezone($appTimezone)->format('M j, g:ia')))"
                 x-text="display" :class="overdue ? 'rush-overdue' : 'text-amber-700'" class="text-xs shrink-0"></div>
        @elseif ($fq->status === 'answered')
            <span class="text-xs text-green-700 font-medium shrink-0">Response submitted</span>
        @endif

        <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 text-gray-400 shrink-0 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </div>

    {{-- Expandable response area --}}
    <div x-show="open" x-cloak class="border-t border-amber-200 px-4 py-3 space-y-3">
        <div>
            <p class="text-xs font-medium text-gray-600 mb-1">Customer's questions:</p>
            <div class="text-sm text-gray-800 whitespace-pre-wrap bg-white border border-gray-200 rounded px-3 py-2">{{ $fq->questionsForReader() }}</div>
        </div>

        @if ($fq->status === 'unanswered')
            <div>
                <p class="text-xs font-medium text-gray-600 mb-1">Your response:</p>
                <div x-data="{
                    wrap(b,a){const t=this.$refs.ta,s=t.selectionStart,e=t.selectionEnd,v=t.value.slice(s,e);t.value=t.value.slice(0,s)+b+v+a+t.value.slice(e);t.selectionStart=s+b.length;t.selectionEnd=s+b.length+v.length;t.focus()},
                    link(){const u=prompt('URL (include https://)');if(u)this.wrap('<a href=\''+u+'\'>', '</a>')}
                }">
                    <div class="flex items-center gap-1 mb-1">
                        <button type="button" @click="wrap('<b>','</b>')" class="px-2 py-0.5 text-xs font-bold border border-gray-300 rounded bg-white hover:bg-gray-100 leading-5">B</button>
                        <button type="button" @click="wrap('<i>','</i>')" class="px-2 py-0.5 text-xs italic border border-gray-300 rounded bg-white hover:bg-gray-100 leading-5">I</button>
                        <button type="button" @click="link()" class="px-2 py-0.5 text-xs border border-gray-300 rounded bg-white hover:bg-gray-100 leading-5">Link</button>
                    </div>
                    <textarea x-ref="ta" x-model="response" rows="6"
                              class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-amber-400 resize-y bg-white"
                              placeholder="Type your response here…"></textarea>
                </div>
                <div class="mt-2 flex items-center gap-2">
                    <button type="button"
                            :disabled="submitting || response.trim() === ''"
                            @click="
                                submitting = true;
                                fetch('{{ route('followups.respond', $fq) }}', {
                                    method: 'POST',
                                    headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                                    body: JSON.stringify({ response: response })
                                }).then(r => { if (r.ok) done = true; else submitting = false; })
                                  .catch(() => { submitting = false; })
                            "
                            :class="submitting || response.trim() === '' ? 'opacity-50 cursor-not-allowed' : 'hover:bg-amber-600'"
                            class="px-4 py-1.5 bg-amber-500 text-white text-sm font-semibold rounded transition">
                        <span x-text="submitting ? 'Submitting…' : 'Submit Response'"></span>
                    </button>
                </div>
            </div>
        @elseif ($fq->status === 'answered')
            <div>
                <p class="text-xs font-medium text-gray-600 mb-1">Your submitted response:</p>
                <div class="text-sm text-gray-700 whitespace-pre-wrap bg-white border border-gray-200 rounded px-3 py-2">{{ $fq->reader_response }}</div>
            </div>
        @endif
    </div>
</div>
