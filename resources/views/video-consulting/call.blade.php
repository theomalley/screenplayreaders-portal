<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Join Call</h2>
            @if ($previewMode ?? false)
                <a href="{{ route('test-data.index') }}" class="text-sm text-gray-500 hover:text-gray-700">
                    &larr; Back to Test Data
                </a>
            @else
                <a href="{{ route('assignments.show', $assignment) }}" class="text-sm text-gray-500 hover:text-gray-700">
                    &larr; Back to Assignment
                </a>
            @endif
        </div>
    </x-slot>

    <style>
        .call-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 2rem; text-align: center; max-width: 720px; margin: 2rem auto; }
        .call-title { font-size: 1.25rem; font-weight: 700; color: #1f2937; margin-bottom: 0.25rem; }
        .call-with { font-size: 1rem; color: #374151; margin-bottom: 0.5rem; }
        .call-meta { font-size: 0.95rem; color: #4b5563; margin-bottom: 0.25rem; }
        .call-not-ready { margin-top: 1.25rem; font-size: 0.9rem; color: #92400e; background: #fffbeb; border: 1px solid #fde68a; border-radius: 6px; padding: 0.75rem 1rem; }
        .call-consent { margin-top: 1.5rem; font-size: 0.8rem; color: #9ca3af; }

        .call-timer { margin: 1.25rem 0; }
        .call-timer-value { font-size: 3rem; font-weight: 700; font-family: 'SF Mono', 'Menlo', monospace; color: #1f2937; line-height: 1; }
        .call-timer-label { font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; margin-top: 0.25rem; }
        .call-timer-over .call-timer-value { color: #b45309; }
        .call-timer-upcoming .call-timer-value { color: #4b5563; }
        .call-extend-btn { display: inline-block; margin-top: 0.6rem; background: #fff; color: #374151; border: 1px solid #d1d5db; border-radius: 6px; padding: 0.4rem 0.9rem; font-size: 0.85rem; font-weight: 600; cursor: pointer; }
        .call-extend-btn:hover:not(:disabled) { background: #f3f4f6; }
        .call-extend-btn:disabled { opacity: 0.6; cursor: default; }

        .call-embed { display: block; width: 100%; height: 70vh; border: 0; border-radius: 8px; margin-top: 1rem; }

        .call-notes { margin-top: 1.5rem; text-align: left; }
        .call-notes-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.4rem; }
        .call-notes-label { font-size: 0.85rem; font-weight: 600; color: #374151; }
        .call-notes-saved { font-size: 0.8rem; color: #16a34a; }
        .call-notes-textarea { width: 100%; min-height: 140px; resize: vertical; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.9rem; font-family: inherit; color: #1f2937; }
        .call-notes-hint { margin-top: 0.4rem; font-size: 0.75rem; color: #9ca3af; }
    </style>

    @include('video-consulting.partials.call-details')
    @include('video-consulting.partials.call-notes')
    @include('video-consulting.partials.call-scripts')
</x-app-layout>
