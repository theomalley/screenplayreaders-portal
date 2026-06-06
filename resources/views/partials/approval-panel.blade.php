{{--
    Reusable approval panel — no nested <form> elements, uses Alpine fetch().
    Variables: $type, $approveUrl, $rejectUrl, $preview (raw HTML), $label
--}}
<div x-data="{
        done: false,
        rejecting: false,
        note: '',
        csrf: document.querySelector('meta[name=csrf-token]')?.content ?? '',
        async approve() {
            const r = await fetch('{{ $approveUrl }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': this.csrf } });
            if (r.ok) { this.done = true; setTimeout(() => location.reload(), 400); }
        },
        async reject() {
            const fd = new FormData(); fd.append('note', this.note);
            const r = await fetch('{{ $rejectUrl }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': this.csrf }, body: fd });
            if (r.ok) { this.done = true; setTimeout(() => location.reload(), 400); }
        }
    }"
     x-show="!done"
     class="mt-2 px-3 py-2 bg-amber-50 border border-amber-200 rounded text-xs text-amber-700 space-y-2">

    <p class="font-medium">{{ $label }}</p>
    <div class="w-full">
        {!! $preview !!}
    </div>
    <div class="flex items-center gap-3 flex-wrap">
        <button type="button" @click="approve()"
                class="px-2 py-1 text-xs bg-green-600 text-white rounded hover:bg-green-700 shrink-0">Approve</button>
        <button type="button" @click="rejecting = !rejecting"
                class="px-2 py-1 text-xs bg-red-100 text-red-700 border border-red-300 rounded hover:bg-red-200 shrink-0">Reject</button>
    </div>

    <div x-show="rejecting" x-cloak class="space-y-1.5">
        <textarea x-model="note" rows="2" placeholder="Optional — reason for rejection (shown to the user)"
                  class="w-full border border-amber-300 rounded px-2 py-1 text-xs text-gray-700 bg-white focus:outline-none focus:ring-1 focus:ring-amber-400 resize-none"></textarea>
        <div class="flex items-center gap-2">
            <button type="button" @click="reject()"
                    class="px-2 py-1 text-xs bg-red-600 text-white rounded hover:bg-red-700">Confirm Reject</button>
            <button type="button" @click="rejecting = false"
                    class="px-2 py-1 text-xs text-gray-500 hover:text-gray-700">Cancel</button>
        </div>
    </div>
</div>
