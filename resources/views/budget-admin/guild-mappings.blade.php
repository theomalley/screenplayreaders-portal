<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('budget-admin.index') }}" class="text-gray-400 hover:text-gray-600">&larr;</a>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Budget — Guild Tier Mappings</h2>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded-md text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <p class="text-sm text-gray-500 mb-4">Default rate tier code used for each guild at each budget class when "all guilds automatic" is selected. 999 = non-union.</p>

            <form method="POST" action="{{ route('budget-admin.guild-mappings.update') }}">
                @csrf
                @method('PATCH')

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 bg-gray-50">
                                    <th class="px-5 py-2 text-left text-xs font-medium text-gray-500 uppercase min-w-[140px]">Guild</th>
                                    @for ($c = 1; $c <= 8; $c++)
                                        <th class="px-2 py-2 text-center text-xs font-medium text-gray-500 uppercase w-20">Class {{ $c }}</th>
                                    @endfor
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($mappings as $guild => $rows)
                                    <tr class="hover:bg-blue-50/30">
                                        <td class="px-5 py-3 text-gray-700 font-medium">{{ $guild }}</td>
                                        @foreach ($rows->sortBy('budget_class') as $mapping)
                                            <td class="px-2 py-1 text-center">
                                                @if ($canEdit)
                                                    <input type="number" name="mappings[{{ $mapping->id }}]"
                                                           value="{{ $mapping->tier_code }}"
                                                           min="0" step="1"
                                                           class="w-16 text-center text-sm border-gray-300 rounded shadow-sm focus:ring-indigo-500 focus:border-indigo-500 py-1 px-1
                                                                  {{ $mapping->tier_code === 999 ? 'text-gray-400' : 'text-indigo-700 font-medium' }}" />
                                                @else
                                                    <span class="font-mono {{ $mapping->tier_code === 999 ? 'text-gray-400' : 'text-indigo-700 font-medium' }}">
                                                        {{ $mapping->tier_code }}
                                                    </span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                @if ($canEdit)
                    <div class="mt-4 flex justify-end">
                        <x-primary-button>Save Mappings</x-primary-button>
                    </div>
                @endif
            </form>
        </div>
    </div>
</x-app-layout>
