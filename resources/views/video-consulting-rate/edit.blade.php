<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">My Video Consulting Rate</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-lg mx-auto px-4 sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-4 bg-green-50 border border-green-200 rounded-lg p-4 text-sm text-green-700">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <form method="POST" action="{{ route('video-consulting-rate.update') }}" class="p-6 space-y-5">
                    @csrf
                    @method('PATCH')

                    @if ($errors->any())
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-sm text-red-700">
                            <ul class="list-disc list-inside space-y-0.5">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <p class="text-sm text-gray-500">
                        Set what you want to be paid per video consulting call. Leave a field blank if you
                        don't want to offer that call length.
                    </p>

                    @forelse ($durations as $duration)
                        <div>
                            <x-input-label for="rate_{{ $duration->id }}" value="{{ $duration->label }}" />
                            <div class="mt-1 flex items-center gap-2">
                                <span class="text-sm text-gray-500">$</span>
                                <input type="text" id="rate_{{ $duration->id }}" name="rates[{{ $duration->id }}]"
                                       value="{{ old('rates.' . $duration->id, $myRates[$duration->id] ?? '') }}"
                                       class="block w-32 border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                <span class="text-xs text-gray-400">
                                    (${{ number_format($duration->min_reader_rate, 2) }} – ${{ number_format($duration->max_reader_rate, 2) }})
                                </span>
                            </div>
                            <x-input-error :messages="$errors->get('rates.' . $duration->id)" class="mt-1" />
                        </div>
                    @empty
                        <p class="text-sm text-gray-400">No video consulting durations have been set up yet.</p>
                    @endforelse

                    <div class="flex justify-end pt-2 border-t border-gray-100">
                        <x-primary-button>Save</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
