{{-- Admin/editor popover to toggle the "Take Me" attention-grabber. Expects: $assignment
     (status === 'unassigned' — gated by the caller, admin-assignment-row.blade.php). --}}
@php
    $tmEnabled  = (bool) $assignment->take_me_enabled;
    $tmStyle    = $assignment->take_me_style ?: 'gold';
    $tmText     = $assignment->take_me_text ?? '';
    $tmDefaults = \App\Models\Assignment::TAKE_ME_DEFAULT_TEXT;
@endphp
<div class="relative inline-block normal-case tracking-normal"
     x-data="{
        open: false,
        saving: false,
        enabled: {{ $tmEnabled ? 'true' : 'false' }},
        style: '{{ $tmStyle }}',
        text: @js($tmText),
        defaults: @js($tmDefaults),
        async save(nextEnabled) {
            this.saving = true;
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
                if (r.ok) location.reload();
            } finally { this.saving = false; }
        }
     }"
     @click.stop
     @click.outside="open = false">
    <button type="button" @click="open = !open"
            title="Take Me attention-grabber"
            class="inline-flex items-center gap-0.5 px-1 py-px rounded text-[9px] font-bold tracking-wide border transition-colors"
            :class="enabled ? 'bg-fuchsia-100 text-fuchsia-700 border-fuchsia-300' : 'bg-white text-gray-400 border-gray-300 hover:text-fuchsia-600 hover:border-fuchsia-300'">
        🎯 Take Me
    </button>

    <div x-show="open" x-cloak x-transition
         class="absolute z-30 top-full left-0 mt-1 w-64 bg-white border border-gray-200 rounded-lg shadow-lg p-3">
        <div class="text-[11px] font-semibold text-gray-600 mb-2">Take Me attention-grabber</div>

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
               class="w-full text-xs border border-gray-300 rounded px-2 py-1 mb-2 focus:ring-indigo-400 focus:border-indigo-400" />

        <div class="flex items-center justify-between gap-2">
            <button type="button" x-show="enabled" :disabled="saving"
                    @click="save(false)"
                    class="text-[11px] text-gray-400 hover:text-red-600 disabled:opacity-50">Turn off</button>
            <button type="button" :disabled="saving"
                    @click="save(true)"
                    class="ml-auto text-[11px] px-2.5 py-1 bg-indigo-600 text-white rounded hover:bg-indigo-500 disabled:opacity-50"
                    x-text="saving ? 'Saving…' : (enabled ? 'Update' : 'Turn on')"></button>
        </div>
    </div>
</div>
