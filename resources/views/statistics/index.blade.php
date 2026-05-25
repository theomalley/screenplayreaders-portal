<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Statistics</h2>
            <form method="GET" action="{{ route('statistics.index') }}">
                <select name="period" onchange="this.form.submit()"
                    class="text-sm border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                    @foreach(\App\Http\Controllers\StatisticsController::$PERIODS as $key => $label)
                        <option value="{{ $key }}" @selected($period === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </form>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Volume overview --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-3">Coverage Volume (All Vendors)</h3>
                <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3">
                    @foreach($volumeStats as $label => $count)
                        <div class="rounded-lg border border-indigo-100 bg-indigo-50 px-4 py-3">
                            <div class="text-xs font-medium text-indigo-500 uppercase tracking-wide">{{ $label }}</div>
                            <div class="mt-1 text-2xl font-bold text-indigo-700">{{ $count }}</div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Per-reader stats --}}
            @if($readerStats->isNotEmpty())
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Per-Reader Stats (SR coverage, selected period)</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs font-medium text-gray-500 uppercase tracking-wide">
                            <tr>
                                <th class="px-4 py-3 text-left">Reader</th>
                                <th class="px-4 py-3 text-center">Completed</th>
                                <th class="px-4 py-3 text-center">Avg Turnaround</th>
                                <th class="px-4 py-3 text-center">Avg Score</th>
                                <th class="px-4 py-3 text-center">Pass</th>
                                <th class="px-4 py-3 text-center">Consider</th>
                                <th class="px-4 py-3 text-center">Recommend</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($readerStats as $stats)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-medium text-gray-800">{{ $stats['reader_name'] }}</td>
                                <td class="px-4 py-3 text-center text-gray-700">{{ $stats['count'] }}</td>
                                <td class="px-4 py-3 text-center text-gray-600">
                                    {{ $stats['avg_turnaround_days'] !== null ? $stats['avg_turnaround_days'] . ' days' : '—' }}
                                </td>
                                <td class="px-4 py-3 text-center text-gray-600">
                                    {{ $stats['avg_score'] !== null ? $stats['avg_score'] : '—' }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="text-red-600 font-medium">{{ $stats['pass'] }}</span>
                                    <span class="text-xs text-gray-400 ml-1">({{ $stats['pass_pct'] }}%)</span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="text-yellow-600 font-medium">{{ $stats['consider'] }}</span>
                                    <span class="text-xs text-gray-400 ml-1">({{ $stats['consider_pct'] }}%)</span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="text-green-600 font-medium">{{ $stats['recommend'] }}</span>
                                    <span class="text-xs text-gray-400 ml-1">({{ $stats['recommend_pct'] }}%)</span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        {{-- Combined row --}}
                        <tfoot class="bg-indigo-50 border-t-2 border-indigo-200 text-sm font-semibold">
                            <tr>
                                <td class="px-4 py-3 text-indigo-700">All Readers Combined</td>
                                <td class="px-4 py-3 text-center text-indigo-700">{{ $combined['count'] }}</td>
                                <td class="px-4 py-3 text-center text-indigo-600">
                                    {{ $combined['avg_turnaround_days'] !== null ? $combined['avg_turnaround_days'] . ' days' : '—' }}
                                </td>
                                <td class="px-4 py-3 text-center text-indigo-600">
                                    {{ $combined['avg_score'] !== null ? $combined['avg_score'] : '—' }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="text-red-600">{{ $combined['pass'] }}</span>
                                    <span class="text-xs text-gray-400 ml-1">({{ $combined['pass_pct'] }}%)</span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="text-yellow-600">{{ $combined['consider'] }}</span>
                                    <span class="text-xs text-gray-400 ml-1">({{ $combined['consider_pct'] }}%)</span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="text-green-600">{{ $combined['recommend'] }}</span>
                                    <span class="text-xs text-gray-400 ml-1">({{ $combined['recommend_pct'] }}%)</span>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            @else
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 px-6 py-12 text-center text-gray-400 text-sm">
                    No completed SR coverages in this period.
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
