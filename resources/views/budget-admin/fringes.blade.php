<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('budget-admin.index') }}" class="text-gray-400 hover:text-gray-600">&larr;</a>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Budget — Fringe Rates</h2>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded-md text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <form method="POST" action="{{ route('budget-admin.fringes.update') }}">
                @csrf
                @method('PATCH')

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-5 py-3 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">Payroll Taxes & Union Fringes</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 bg-gray-50/50">
                                    <th class="px-5 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fringe</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase w-28">Rate (%)</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase w-32">Ceiling ($)</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase w-28">Hourly Add-on ($)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($fringes as $fringe)
                                    <tr class="hover:bg-blue-50/30">
                                        <td class="px-5 py-3 text-gray-700 font-medium">{{ $fringe->name }}</td>
                                        <td class="px-3 py-2 text-right">
                                            @if ($canEdit)
                                                <input type="number" name="fringes[{{ $fringe->id }}][rate]"
                                                       value="{{ rtrim(rtrim(number_format($fringe->rate * 100, 4, '.', ''), '0'), '.') }}"
                                                       min="0" max="100" step="0.0001"
                                                       class="w-24 text-right text-sm border-gray-300 rounded shadow-sm focus:ring-indigo-500 focus:border-indigo-500 py-1 px-2" />
                                                {{-- Display as percentage, store as decimal --}}
                                            @else
                                                <span class="font-mono text-gray-800">{{ number_format($fringe->rate * 100, 2) }}%</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            @if ($canEdit)
                                                <input type="number" name="fringes[{{ $fringe->id }}][ceiling]"
                                                       value="{{ $fringe->ceiling !== null ? number_format($fringe->ceiling, 2, '.', '') : '' }}"
                                                       min="0" step="0.01" placeholder="—"
                                                       class="w-28 text-right text-sm border-gray-300 rounded shadow-sm focus:ring-indigo-500 focus:border-indigo-500 py-1 px-2" />
                                            @else
                                                <span class="font-mono text-gray-800">{{ $fringe->ceiling !== null ? '$' . number_format($fringe->ceiling, 2) : '—' }}</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            @if ($canEdit)
                                                <input type="number" name="fringes[{{ $fringe->id }}][hourly_addon]"
                                                       value="{{ $fringe->hourly_addon !== null ? number_format($fringe->hourly_addon, 2, '.', '') : '' }}"
                                                       min="0" step="0.01" placeholder="—"
                                                       class="w-24 text-right text-sm border-gray-300 rounded shadow-sm focus:ring-indigo-500 focus:border-indigo-500 py-1 px-2" />
                                            @else
                                                <span class="font-mono text-gray-800">{{ $fringe->hourly_addon !== null ? '$' . number_format($fringe->hourly_addon, 2) : '—' }}</span>
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
                        <x-primary-button>Save Fringe Rates</x-primary-button>
                    </div>
                @endif
            </form>
        </div>
    </div>
</x-app-layout>
