<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ $assignment->script_title }}
                </h2>
                <p class="text-sm text-gray-500 mt-0.5">
                    {{ $assignment->writer_name }} &middot; {{ $assignment->page_count }}pp
                    @if ($assignment->rush)
                        &middot; <span class="text-amber-600 font-medium">Rush</span>
                    @endif
                </p>
            </div>
            <a href="{{ route('assignments.index') }}"
               class="text-sm text-gray-500 hover:text-gray-700">&larr; Assignments</a>
        </div>
    </x-slot>

    <div class="py-6 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">

        @if (session('success'))
            <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded-md text-sm">
                {{ session('success') }}
            </div>
        @endif

        {{-- Script viewer --}}
        <div class="bg-white rounded-lg shadow mb-6 overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                <span class="text-sm font-medium text-gray-700">Script</span>
                @if ($dlUrl)
                    <a href="{{ $dlUrl }}" target="_blank"
                       class="text-xs text-indigo-600 hover:text-indigo-800">Download (admin)</a>
                @endif
            </div>

            @if ($viewLink && $assignment->assigned_reader_id === auth()->id() || auth()->user()->isAdminOrEditor())
                @if ($viewLink)
                    <iframe src="{{ $viewLink }}"
                            class="w-full"
                            style="height: 80vh;"
                            frameborder="0"
                            allowfullscreen></iframe>
                @else
                    <div class="px-5 py-10 text-center text-sm text-gray-400">
                        Script not yet uploaded.
                    </div>
                @endif
            @else
                <div class="px-5 py-10 text-center text-sm text-gray-400">
                    Script will be available once you accept this assignment.
                </div>
            @endif
        </div>

        {{-- Assignment details --}}
        <div class="bg-white rounded-lg shadow px-5 py-4 text-sm text-gray-700 space-y-2">
            <div class="grid grid-cols-2 gap-x-6 gap-y-2">
                <div><span class="font-medium">Order #</span> {{ $assignment->order_number }}</div>
                <div><span class="font-medium">Status</span> {{ ucfirst(str_replace('_', ' ', $assignment->status)) }}</div>
                <div><span class="font-medium">Type</span> {{ ucfirst(str_replace('_', ' ', $assignment->assignment_type)) }}</div>
                <div><span class="font-medium">Vendor</span> {{ strtoupper($assignment->vendor) }}</div>
                @if (auth()->user()->isAdminOrEditor())
                    <div><span class="font-medium">Pay rate</span> ${{ number_format($assignment->pay_rate, 2) }}</div>
                @endif
            </div>
            @if ($assignment->notes)
                <div class="pt-2 border-t border-gray-100">
                    <span class="font-medium">Notes</span>
                    <p class="mt-1 text-gray-600">{{ $assignment->notes }}</p>
                </div>
            @endif
        </div>

        {{-- Reader actions --}}
        @if (auth()->user()->isReader())
            <div class="mt-4 flex gap-3">
                @can('accept', $assignment)
                    <form method="POST" action="{{ route('assignments.accept', $assignment) }}">
                        @csrf
                        <button type="submit"
                                class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded hover:bg-indigo-700">
                            Accept Assignment
                        </button>
                    </form>
                @endcan

                @can('cancel', $assignment)
                    <form method="POST" action="{{ route('assignments.cancel', $assignment) }}">
                        @csrf
                        <button type="submit"
                                class="px-4 py-2 bg-gray-200 text-gray-700 text-sm font-medium rounded hover:bg-gray-300">
                            Return to Pool
                        </button>
                    </form>
                @endcan

                @can('submitCoverage', $assignment)
                    <a href="{{ route('coverage.show', $assignment) }}"
                       class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded hover:bg-green-700">
                        Submit Coverage
                    </a>
                @endcan
            </div>
        @endif

        {{-- Admin actions --}}
        @if (auth()->user()->isAdminOrEditor())
            <div class="mt-4 flex gap-3">
                <a href="{{ route('assignments.edit', $assignment) }}"
                   class="px-4 py-2 bg-gray-800 text-white text-sm font-medium rounded hover:bg-gray-700">
                    Edit Assignment
                </a>
            </div>
        @endif

    </div>
</x-app-layout>
