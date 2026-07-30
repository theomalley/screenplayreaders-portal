{{-- Admin/editor "Take Me" attention-grabber card — assignments/edit.blade.php.
     Expects: $assignment. Only meaningful while unassigned (gated by the caller);
     saves independently via AJAX, not part of the surrounding page form. --}}
@php
    $tmEnabled  = (bool) $assignment->take_me_enabled;
    $tmStyle    = $assignment->take_me_style ?: 'gold';
    $tmText     = $assignment->take_me_text ?? '';
    $tmDefaults = \App\Models\Assignment::TAKE_ME_DEFAULT_TEXT;
@endphp
<div class="bg-fuchsia-50 border border-fuchsia-200 rounded-lg px-4 py-3 mt-5"
     x-data="{
        saving: false,
        saved: false,
        enabled: {{ $tmEnabled ? 'true' : 'false' }},
        style: '{{ $tmStyle }}',
        text: @js($tmText),
        defaults: @js($tmDefaults),
        async save(nextEnabled) {
            this.saving = true; this.saved = false;
            try {
                const r = await fetch(@js(route('assignments.take-me', $assignment)), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ enabled: nextEnabled, style: this.style, text: this.text }),
                });
                if (r.ok) {
                    this.enabled = nextEnabled;
                    this.saved = true;
                    setTimeout(() => { this.saved = false; }, 3000);
                }
            } finally { this.saving = false; }
        }
     }">
    <h3 class="text-xs font-semibold text-fuchsia-700 uppercase tracking-wider mb-2">
        🎯 Take Me
        <span class="ml-1 text-[10px] font-normal text-fuchsia-400 normal-case tracking-normal">(playful outline + badge shown to everyone, to draw attention to a stale assignment)</span>
    </h3>

    <div class="flex items-center gap-1.5 mb-2">
        <button type="button" @click="style = 'gold'"
                :class="style === 'gold' ? 'ring-2 ring-offset-1 ring-gray-400' : ''"
                class="flex-1 take-me-badge take-me-badge-gold justify-center">Gold</button>
        <button type="button" @click="style = 'rainbow'"
                :class="style === 'rainbow' ? 'ring-2 ring-offset-1 ring-gray-400' : ''"
                class="flex-1 take-me-badge take-me-badge-rainbow justify-center">Rainbow</button>
        <button type="button" @click="style = 'neon'"
                :class="style === 'neon' ? 'ring-2 ring-offset-1 ring-gray-400' : ''"
                class="flex-1 take-me-badge take-me-badge-neon justify-center">Neon</button>
    </div>

    <input type="text" x-model="text" maxlength="80"
           :placeholder="defaults[style]"
           class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm mb-2" />

    <div class="flex items-center justify-end gap-2">
        <span x-show="saved" x-cloak class="text-[10px] text-green-600 mr-auto" x-text="enabled ? 'Saved — live now' : 'Turned off'"></span>
        <button type="button" x-show="enabled" :disabled="saving"
                @click="save(false)"
                class="px-3 py-1.5 text-xs font-medium text-fuchsia-400 hover:text-red-600 disabled:opacity-50">Turn off</button>
        <button type="button" :disabled="saving"
                @click="save(true)"
                class="px-3 py-1.5 text-xs font-medium text-white bg-fuchsia-500 hover:bg-fuchsia-600 rounded-md shadow-sm disabled:opacity-50"
                x-text="saving ? 'Saving…' : (enabled ? 'Update' : 'Turn on')"></button>
    </div>
</div>
