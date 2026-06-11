{{-- Over 120 / Over 160 page-count flag — opens a HelpScout draft via a saved reply --}}
@php
    $pageFlag = $assignment->status === 'incoming' ? $assignment->pageCountFlag() : null;
@endphp
@if ($pageFlag)
    @php
        $flagRoute = $pageFlag === \App\Models\Assignment::PAGE_FLAG_OVER_160
            ? route('assignments.over-160', $assignment)
            : route('assignments.over-120', $assignment);
        $flagLabel = $pageFlag === \App\Models\Assignment::PAGE_FLAG_OVER_160 ? 'Over 160' : 'Over 120';
    @endphp
    <span x-data="{ busy: false, sent: false, err: '' }">
        <button type="button"
                :disabled="busy || sent"
                @click.stop="
                    busy = true; err = '';
                    fetch(@js($flagRoute), {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            'Accept': 'application/json',
                        }
                    })
                    .then(r => r.json())
                    .then(d => {
                        busy = false;
                        if (d.error) { err = d.error; }
                        else { sent = true; window.open(d.url, '_blank'); }
                    })
                    .catch(() => { busy = false; err = 'Failed'; })
                "
                class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold transition-colors disabled:opacity-50 whitespace-nowrap"
                :class="sent ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700 hover:bg-red-200'"
                x-text="busy ? '…' : (sent ? '✓ Drafted' : '{{ $flagLabel }}')">
        </button>
        <span x-show="err" x-text="err" class="text-[10px] text-red-500 ml-0.5"></span>
    </span>
@endif
