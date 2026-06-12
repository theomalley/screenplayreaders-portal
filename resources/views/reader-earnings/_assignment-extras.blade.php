{{--
    Per-assignment detail line: overall turnaround, assignment turnaround,
    page count, and "View Coverage" link — mirrors the Completed This Week
    tab on the Assignments page (resources/views/assignments/index.blade.php).
--}}
@php
    $overallDiff  = ($assignment->created_at && $assignment->completed_at)
        ? $assignment->created_at->diff($assignment->completed_at) : null;
    $overallStr   = $overallDiff
        ? ($overallDiff->days >= 1
            ? ($overallDiff->days . 'd ' . $overallDiff->h . 'h')
            : ($overallDiff->h >= 1 ? ($overallDiff->h . 'h ' . $overallDiff->i . 'm') : (max(0, $overallDiff->i) . 'm')))
        : '—';
    $overallTitle = ($assignment->created_at && $assignment->completed_at)
        ? ($assignment->created_at->format('M j g:ia') . ' → ' . $assignment->completed_at->format('M j g:ia'))
        : '—';

    $assignStart  = $assignment->accepted_at ?? $assignment->created_at;
    $assignDiff   = ($assignStart && $assignment->completed_at)
        ? $assignStart->diff($assignment->completed_at) : null;
    $assignStr    = $assignDiff
        ? ($assignDiff->days >= 1
            ? ($assignDiff->days . 'd ' . $assignDiff->h . 'h')
            : ($assignDiff->h >= 1 ? ($assignDiff->h . 'h ' . $assignDiff->i . 'm') : (max(0, $assignDiff->i) . 'm')))
        : '—';
    $assignTitle  = ($assignStart && $assignment->completed_at)
        ? ($assignStart->format('M j g:ia') . ' → ' . $assignment->completed_at->format('M j g:ia'))
        : '—';
@endphp
<div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1" x-data="{ textOpen: false }">
    <div>
        <div class="text-[9px] text-gray-400 uppercase tracking-wide leading-none mb-0.5">Overall Turnaround</div>
        <div class="text-xs tabular-nums text-gray-600" title="{{ $overallTitle }}">{{ $overallStr }}</div>
    </div>
    <div>
        <div class="text-[9px] text-gray-400 uppercase tracking-wide leading-none mb-0.5">Assignment Turnaround</div>
        <div class="text-xs tabular-nums text-gray-600" title="{{ $assignTitle }}">{{ $assignStr }}</div>
    </div>
    <div class="text-[10px] text-gray-400 tabular-nums">{{ $assignment->page_count }}p</div>
    @if($assignment->coverageSubmission)
        <div>
            <button @click="textOpen = true" type="button"
                    class="text-[10px] text-indigo-500 hover:text-indigo-700 hover:underline">View Coverage</button>
        </div>
        <div x-show="textOpen" x-cloak
             @keydown.escape.window="textOpen = false"
             class="fixed inset-0 z-50 flex flex-col bg-black/80">
            <div class="flex items-center justify-between px-4 py-2 bg-gray-900 shrink-0 gap-2 flex-wrap">
                <span class="text-sm text-gray-200 font-medium truncate min-w-0">
                    {{ $assignment->script_title }} — Coverage
                </span>
                <button @click="textOpen = false" type="button"
                        class="text-gray-400 hover:text-white text-2xl leading-none px-1">×</button>
            </div>
            <iframe :src="textOpen ? @js(route('coverage.preview', $assignment)) : ''"
                    class="flex-1 w-full border-0 bg-white"></iframe>
        </div>
    @endif
</div>
