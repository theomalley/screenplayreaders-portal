<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('budget-admin.index') }}" class="text-gray-400 hover:text-gray-600">&larr;</a>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Budget — State Rates</h2>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded-md text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <form method="POST" action="{{ route('budget-admin.states.update') }}">
                @csrf
                @method('PATCH')

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-5 py-3 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">SUI Rates, Ceilings & Minimum Wages by State</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 bg-gray-50/50">
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase min-w-[160px]">State</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase w-28">SUI Rate (%)</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase w-32">SUI Ceiling ($)</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase w-28">Min Wage ($/hr)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($states as $state)
                                    <tr class="hover:bg-blue-50/30">
                                        <td class="px-4 py-2 text-gray-700 font-medium">{{ $state->state_name }}</td>
                                        <td class="px-3 py-1 text-right">
                                            @if ($canEdit)
                                                <input type="number" name="states[{{ $state->id }}][sui_rate]"
                                                       value="{{ rtrim(rtrim(number_format($state->sui_rate * 100, 4, '.', ''), '0'), '.') }}"
                                                       min="0" max="20" step="0.0001"
                                                       class="w-24 text-right text-sm border-gray-300 rounded shadow-sm focus:ring-indigo-500 focus:border-indigo-500 py-1 px-2" />
                                            @else
                                                <span class="font-mono text-gray-800">{{ number_format($state->sui_rate * 100, 2) }}%</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-1 text-right">
                                            @if ($canEdit)
                                                <input type="number" name="states[{{ $state->id }}][sui_ceiling]"
                                                       value="{{ number_format($state->sui_ceiling, 2, '.', '') }}"
                                                       min="0" step="0.01"
                                                       class="w-28 text-right text-sm border-gray-300 rounded shadow-sm focus:ring-indigo-500 focus:border-indigo-500 py-1 px-2" />
                                            @else
                                                <span class="font-mono text-gray-800">${{ number_format($state->sui_ceiling, 2) }}</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-1 text-right">
                                            @if ($canEdit)
                                                <input type="number" name="states[{{ $state->id }}][minimum_wage]"
                                                       value="{{ number_format($state->minimum_wage, 2, '.', '') }}"
                                                       min="0" step="0.01"
                                                       class="w-24 text-right text-sm border-gray-300 rounded shadow-sm focus:ring-indigo-500 focus:border-indigo-500 py-1 px-2" />
                                            @else
                                                <span class="font-mono text-gray-800">${{ number_format($state->minimum_wage, 2) }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                @if ($canEdit)
                    <div class="mt-4 flex justify-end">
                        <x-primary-button>Save State Rates</x-primary-button>
                    </div>
                @endif
            </form>
        </div>
    </div>
</x-app-layout>
