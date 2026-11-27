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
        .call-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 1.5rem; max-width: 1200px; margin: 1.5rem auto; display: flex; gap: 1.5rem; align-items: flex-start; text-align: left; }
        .call-video-pane { flex: 1 1 65%; min-width: 0; height: calc(100vh - 230px); min-height: 420px; border-radius: 8px; overflow: hidden; background: #111827; display: flex; }
        .call-info-pane { flex: 0 0 340px; max-width: 340px; display: flex; flex-direction: column; gap: 0.6rem; max-height: calc(100vh - 230px); overflow-y: auto; padding-right: 0.25rem; }

        .call-title { font-size: 1.1rem; font-weight: 700; color: #1f2937; margin-bottom: 0.1rem; }
        .call-with { font-size: 0.95rem; color: #374151; margin-bottom: 0.15rem; }
        .call-meta { font-size: 0.85rem; color: #4b5563; margin-bottom: 0.1rem; }
        .call-not-ready { margin: auto; font-size: 0.9rem; color: #92400e; background: #fffbeb; border: 1px solid #fde68a; border-radius: 6px; padding: 0.75rem 1rem; max-width: 80%; text-align: center; }
        .call-consent { margin-top: 0.5rem; font-size: 0.75rem; color: #9ca3af; }

        .call-timer { margin: 0.5rem 0; text-align: center; background: #f9fafb; border-radius: 8px; padding: 0.75rem; }
        .call-timer-value { font-size: 2.5rem; font-weight: 700; font-family: 'SF Mono', 'Menlo', monospace; color: #1f2937; line-height: 1; }
        .call-timer-label { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; margin-top: 0.2rem; }
        .call-timer-over .call-timer-value { color: #b45309; }
        .call-timer-upcoming .call-timer-value { color: #4b5563; }
        .call-extend-btn { display: inline-block; margin-top: 0.5rem; background: #fff; color: #374151; border: 1px solid #d1d5db; border-radius: 6px; padding: 0.35rem 0.8rem; font-size: 0.8rem; font-weight: 600; cursor: pointer; }
        .call-extend-btn:hover:not(:disabled) { background: #f3f4f6; }
        .call-extend-btn:disabled { opacity: 0.6; cursor: default; }

        .call-embed { display: block; width: 100%; height: 100%; border: 0; }

        .call-notes { display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0; }
        .call-notes-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.3rem; }
        .call-notes-label { font-size: 0.8rem; font-weight: 600; color: #374151; }
        .call-notes-saved { font-size: 0.75rem; color: #16a34a; }
        .call-notes-textarea { width: 100%; min-height: 100px; flex: 1 1 auto; resize: vertical; padding: 0.6rem; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.85rem; font-family: inherit; color: #1f2937; }
        .call-notes-hint { margin-top: 0.3rem; font-size: 0.7rem; color: #9ca3af; }

        @media (max-width: 860px) {
            .call-card { flex-direction: column; }
            .call-video-pane { width: 100%; height: 50vh; min-height: 320px; }
            .call-info-pane { width: 100%; max-width: 100%; max-height: none; overflow: visible; }
        }
    </style>

    @include('video-consulting.partials.call-details', ['showNotes' => true])
    @include('video-consulting.partials.call-scripts')
</x-app-layout>
