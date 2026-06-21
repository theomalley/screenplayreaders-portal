<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('budget-admin.index') }}" class="text-gray-400 hover:text-gray-600">&larr;</a>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Budget — Department Allocations</h2>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded-md text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <p class="text-sm text-gray-500 mb-4">Percentage of total budget allocated to each department at each budget class. Values are percentages (e.g. 5.0 = 5%).</p>

            <form method="POST" action="{{ route('budget-admin.allocations.update') }}">
                @csrf
                @method('PATCH')

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 bg-gray-50">
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase sticky left-0 bg-gray-50 min-w-[180px]">Department</th>
                                    @for ($c = 1; $c <= 8; $c++)
                                        <th class="px-2 py-2 text-right text-xs font-medium text-gray-500 uppercase w-20">Class {{ $c }}</th>
                                    @endfor
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($allocations as $dept => $rows)
                                    <tr class="hover:bg-blue-50/30">
                                        <td class="px-4 py-2 text-gray-700 font-medium sticky left-0 bg-white whitespace-nowrap">{{ str_replace('_', ' ', ucwords($dept, '_')) }}</td>
                                        @foreach ($rows->sortBy('budget_class') as $alloc)
                                            <td class="px-2 py-1 text-right">
                                                @if ($canEdit)
                                                    <input type="number" name="allocs[{{ $alloc->id }}]"
                                                           value="{{ rtrim(rtrim(number_format($alloc->percentage * 100, 2, '.', ''), '0'), '.') }}"
                                                           min="0" max="100" step="0.01"
                                                           class="w-16 text-right text-sm border-gray-300 rounded shadow-sm focus:ring-indigo-500 focus:border-indigo-500 py-1 px-1" />
                                                @else
                                                    <span class="font-mono text-gray-800">{{ number_format($alloc->percentage * 100, 1) }}%</span>
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
                        <x-primary-button>Save Allocations</x-primary-button>
                    </div>
                @endif
            </form>
        </div>
    </div>
</x-app-layout>
