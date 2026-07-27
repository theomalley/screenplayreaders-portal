{{-- Karen List match badge — admin/editor views only. Expects: $assignment --}}
@if ($assignment->karen_alert)
    <div class="flex items-center gap-1 mt-1" title="{{ $assignment->karen_alert_note ?? 'Karen alert. Check Karen list.' }}">
        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-semibold bg-red-100 text-red-700">
            ⚠️ Karen alert. Check Karen list.
        </span>
    </div>
@endif
