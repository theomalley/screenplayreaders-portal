<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('budget-admin.index') }}" class="text-gray-400 hover:text-gray-600">&larr;</a>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Budget — Crew Rates</h2>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded-md text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <form method="POST" action="{{ route('budget-admin.crew-rates.update') }}" x-data="{ dirty: false }" @change="dirty = true">
                @csrf
                @method('PATCH')

                @foreach ($positions as $department => $deptPositions)
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden mb-6">
                        <div class="px-5 py-3 bg-gray-50 border-b border-gray-200">
                            <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">{{ str_replace('_', ' ', $department) }}</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="border-b border-gray-100 bg-gray-50/50">
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase sticky left-0 bg-gray-50/50 min-w-[180px]">Position</th>
                                        <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase w-16">Guild</th>
                                        @php
                                            $allTierCodes = $deptPositions->flatMap(fn($p) => $p->rateTiers->pluck('tier_code'))->unique()->sort()->values();
                                        @endphp
                                        @foreach ($allTierCodes as $code)
                                            <th class="px-2 py-2 text-right text-xs font-medium text-gray-500 uppercase min-w-[90px]">Tier {{ $code }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($deptPositions as $position)
                                        @php
                                            $tiersByCode = $position->rateTiers->keyBy('tier_code');
                                        @endphp
                                        <tr class="hover:bg-blue-50/30">
                                            <td class="px-3 py-2 text-gray-700 font-medium sticky left-0 bg-white whitespace-nowrap">
                                                {{ $position->name }}
                                                <span class="text-gray-400 text-xs ml-1">#{{ $position->line_item_id }}</span>
                                            </td>
                                            <td class="px-2 py-2 text-gray-500 text-xs">{{ $position->guild }}</td>
                                            @foreach ($allTierCodes as $code)
                                                <td class="px-2 py-1 text-right">
                                                    @if ($tiersByCode->has($code))
                                                        @php $tier = $tiersByCode[$code]; @endphp
                                                        @if ($tier->rate_type === 'min_wage')
                                                            <span class="text-gray-400 text-xs italic">min wage</span>
                                                        @elseif ($canEdit)
                                                            <input type="number"
                                                                   name="tiers[{{ $tier->id }}]"
                                                                   value="{{ number_format($tier->rate_value, 2, '.', '') }}"
                                                                   min="0" step="0.01"
                                                                   class="w-24 text-right text-sm border-gray-300 rounded shadow-sm focus:ring-indigo-500 focus:border-indigo-500 py-1 px-2" />
                                                        @else
                                                            <span class="font-mono text-gray-800">
                                                                @if ($tier->rate_type === 'flat') F @endif
                                                                ${{ number_format($tier->rate_value, 2) }}
                                                                @if ($tier->rate_type === 'hourly')/hr @elseif ($tier->rate_type === 'weekly')/wk @endif
                                                            </span>
                                                        @endif
                                                    @else
                                                        <span class="text-gray-200">&mdash;</span>
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach

                @if ($canEdit)
                    <div class="sticky bottom-0 bg-white/95 backdrop-blur border-t border-gray-200 py-3 px-4 flex items-center justify-between -mx-4 sm:-mx-6 lg:-mx-8"
                         x-show="dirty" x-transition>
                        <span class="text-sm text-amber-600">You have unsaved changes</span>
                        <x-primary-button>Save All Rates</x-primary-button>
                    </div>
                @endif
            </form>
        </div>
    </div>
</x-app-layout>
