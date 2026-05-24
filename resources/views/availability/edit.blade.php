<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">My Availability</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-lg mx-auto px-4 sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-4 bg-green-50 border border-green-200 rounded-lg p-4 text-sm text-green-700">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <form method="POST" action="{{ route('availability.update') }}" class="p-6 space-y-5">
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

                    <div>
                        <x-input-label value="Status" />
                        <div class="mt-3 flex gap-6">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="availability" value="available"
                                       {{ old('availability', $profile->availability ?? 'available') === 'available' ? 'checked' : '' }}
                                       class="text-green-600 focus:ring-green-500" />
                                <span class="text-sm font-medium text-green-700">Available</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="availability" value="unavailable"
                                       {{ old('availability', $profile->availability ?? 'available') === 'unavailable' ? 'checked' : '' }}
                                       class="text-red-600 focus:ring-red-500" />
                                <span class="text-sm font-medium text-red-700">Unavailable</span>
                            </label>
                        </div>
                        <x-input-error :messages="$errors->get('availability')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="availability_message" value="Note (optional)" />
                        <textarea id="availability_message" name="availability_message"
                                  rows="3"
                                  class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                  placeholder="e.g. Back Jan 15, on vacation through end of month…">{{ old('availability_message', $profile->availability_message) }}</textarea>
                        <p class="mt-1 text-xs text-gray-400">Shared with the assignments team — not visible to customers.</p>
                        <x-input-error :messages="$errors->get('availability_message')" class="mt-1" />
                    </div>

                    <div class="flex justify-end pt-2 border-t border-gray-100">
                        <x-primary-button>Save</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
