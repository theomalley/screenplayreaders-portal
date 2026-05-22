<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Assignment Archive</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

            @if($assignments->isEmpty())
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 px-6 py-12 text-center text-gray-400 text-sm">
                    No completed assignments yet.
                </div>
            @else
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs font-medium text-gray-500 uppercase tracking-wide">
                            <tr>
                                <th class="px-4 py-3 text-left">Order</th>
                                <th class="px-4 py-3 text-left">Script / Writer</th>
                                <th class="px-4 py-3 text-left">Type</th>
                                <th class="px-4 py-3 text-left">Reader</th>
                                <th class="px-4 py-3 text-left">Submitted</th>
                                <th class="px-4 py-3 text-left">Completed</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($assignments as $assignment)
                                @php
                                    $typeLabel = match($assignment->assignment_type) {
                                        'script_coverage'   => 'Script Coverage',
                                        'notes_only'        => 'Notes-Only',
                                        'deep_dive'         => 'Deep-Dive',
                                        'short'             => 'Short',
                                        'budget'            => 'Budget',
                                        'book'              => 'Book',
                                        'coverage'          => 'Coverage',
                                        'development_notes' => 'Dev Notes',
                                        default             => $assignment->assignment_type ?? '—',
                                    };
                                    if ($assignment->vendor === 'wd') {
                                        $typeLabel = 'WD ' . $typeLabel;
                                    }
                                @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 font-mono text-gray-700 whitespace-nowrap">
                                        {{ $assignment->order_number }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-gray-800">{{ $assignment->script_title }}</div>
                                        <div class="text-gray-400 text-xs">{{ $assignment->writer_name }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $typeLabel }}</td>
                                    <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                                        {{ $assignment->assignedReader?->readerProfile?->initials ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-500 whitespace-nowrap tabular-nums">
                                        {{ $assignment->submitted_at?->format('M j, Y') ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-500 whitespace-nowrap tabular-nums">
                                        {{ $assignment->completed_at?->format('M j, Y') ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('assignments.show', $assignment) }}"
                                            class="text-xs font-medium text-indigo-600 hover:text-indigo-800">
                                            View →
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($assignments->hasPages())
                    <div>{{ $assignments->links() }}</div>
                @endif
            @endif

        </div>
    </div>
</x-app-layout>
