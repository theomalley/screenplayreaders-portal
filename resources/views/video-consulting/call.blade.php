<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Join Call</h2>
            <a href="{{ route('assignments.show', $assignment) }}" class="text-sm text-gray-500 hover:text-gray-700">
                &larr; Back to Assignment
            </a>
        </div>
    </x-slot>

    <style>
        .call-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 2rem; text-align: center; max-width: 560px; margin: 2rem auto; }
        .call-title { font-size: 1.25rem; font-weight: 700; color: #1f2937; margin-bottom: 0.5rem; }
        .call-meta { font-size: 0.95rem; color: #4b5563; margin-bottom: 0.25rem; }
        .call-join-btn { display: inline-block; margin-top: 1.25rem; background: #16a34a; color: #fff; border: none; border-radius: 6px; padding: 0.7rem 1.75rem; font-size: 0.95rem; font-weight: 600; text-decoration: none; }
        .call-join-btn:hover { background: #15803d; }
        .call-not-ready { margin-top: 1.25rem; font-size: 0.9rem; color: #92400e; background: #fffbeb; border: 1px solid #fde68a; border-radius: 6px; padding: 0.75rem 1rem; }
        .call-consent { margin-top: 1.5rem; font-size: 0.8rem; color: #9ca3af; }
    </style>

    @include('video-consulting.partials.call-details')
</x-app-layout>
