<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Coverage Attestations</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <p class="text-sm text-gray-500">
                These checkboxes appear on the SR coverage submission form's Final Assessment step.
                A reader must check every one below before they can submit coverage.
            </p>

            @if (session('success'))
                <div class="bg-green-50 border border-green-200 rounded-lg p-3 text-sm text-green-700">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-sm text-red-700">
                    <strong>Please correct the following:</strong>
                    <ul class="mt-1 list-disc list-inside space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden mb-6">
                <div class="px-5 py-3 bg-gray-50 border-b border-gray-200">
                    <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">Attestation Checkboxes</h3>
                </div>
                <div class="divide-y divide-gray-100">
                    @forelse ($attestations as $i => $attestation)
                        <div class="px-5 py-3 flex items-start gap-3">
                            <div class="flex flex-col items-center gap-1 pt-1">
                                <form method="POST" action="{{ route('coverage-attestations.move-up', $attestation) }}">
                                    @csrf
                                    <button type="submit" @if ($i === 0) disabled @endif
                                        class="text-gray-400 hover:text-gray-700 disabled:opacity-25 disabled:cursor-not-allowed">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('coverage-attestations.move-down', $attestation) }}">
                                    @csrf
                                    <button type="submit" @if ($i === $attestations->count() - 1) disabled @endif
                                        class="text-gray-400 hover:text-gray-700 disabled:opacity-25 disabled:cursor-not-allowed">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                </form>
                            </div>

                            <form method="POST" action="{{ route('coverage-attestations.update', $attestation) }}" class="flex-1 flex items-start gap-2">
                                @csrf
                                @method('PATCH')
                                <textarea name="text" rows="2" required maxlength="500"
                                    class="flex-1 border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm resize-y">{{ $attestation->text }}</textarea>
                                <button type="submit" class="mt-1 text-xs text-indigo-500 hover:text-indigo-700 hover:underline whitespace-nowrap">Save</button>
                            </form>

                            <form method="POST" action="{{ route('coverage-attestations.destroy', $attestation) }}"
                                onsubmit="return confirm('Delete this attestation checkbox?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="mt-1 text-xs text-red-500 hover:text-red-700 hover:underline whitespace-nowrap">Delete</button>
                            </form>
                        </div>
                    @empty
                        <div class="px-5 py-3 text-gray-400 italic text-sm">
                            No attestation checkboxes configured — readers can submit coverage with nothing to confirm.
                        </div>
                    @endforelse
                </div>

                <div class="px-5 py-3 border-t border-gray-100 bg-gray-50">
                    <form method="POST" action="{{ route('coverage-attestations.store') }}" class="flex items-start gap-2">
                        @csrf
                        <textarea name="text" rows="2" required maxlength="500" placeholder="New attestation checkbox text…"
                            class="flex-1 border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm resize-y"></textarea>
                        <x-primary-button type="submit">Add</x-primary-button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
