<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Payroll</h2>
            <form method="GET" action="{{ route('payroll.index') }}">
                <select name="period" onchange="this.form.submit()"
                    class="text-sm border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                    @foreach(\App\Http\Controllers\PayrollController::$PERIODS as $key => $label)
                        <option value="{{ $key }}" @selected($period === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </form>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Payout Schedule (admin only) --}}
            @php
                $days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
            @endphp
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Payout Schedule</h3>
                    <span class="text-sm text-gray-500">
                        Next payout:
                        @if ($schedule['override'])
                            <span class="font-semibold text-amber-600">{{ $nextPayout->format('D M j') }} at {{ $nextPayout->format('g:i A') }} PT</span>
                            <span class="ml-1 text-xs text-amber-500">(override active)</span>
                        @else
                            <span class="font-semibold text-gray-700">{{ $nextPayout->format('D M j') }} at {{ $nextPayout->format('g:i A') }} PT</span>
                        @endif
                    </span>
                </div>

                <div class="px-5 py-4 space-y-4">

                    {{-- Schedule form --}}
                    @if (session('success'))
                        <div class="text-sm text-green-700 bg-green-50 border border-green-200 rounded px-3 py-2">
                            {{ session('success') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('payroll.schedule.update') }}" class="flex flex-wrap items-end gap-4">
                        @csrf @method('PATCH')

                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Frequency</label>
                            <div class="flex gap-4 items-center h-9">
                                <label class="flex items-center gap-1.5 text-sm text-gray-700 cursor-pointer">
                                    <input type="radio" name="frequency" value="weekly"
                                        {{ $schedule['frequency'] === 'weekly' ? 'checked' : '' }}
                                        class="text-indigo-600 focus:ring-indigo-500">
                                    Weekly
                                </label>
                                <label class="flex items-center gap-1.5 text-sm text-gray-700 cursor-pointer">
                                    <input type="radio" name="frequency" value="biweekly"
                                        {{ $schedule['frequency'] === 'biweekly' ? 'checked' : '' }}
                                        class="text-indigo-600 focus:ring-indigo-500">
                                    Biweekly
                                </label>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Payout Day</label>
                            <select name="day" class="text-sm border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 h-9">
                                @foreach ($days as $i => $dayName)
                                    <option value="{{ $i }}" {{ $schedule['day'] === $i ? 'selected' : '' }}>{{ $dayName }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Payout Time (PT)</label>
                            <input type="time" name="time" value="{{ $schedule['time'] }}"
                                class="text-sm border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 h-9 px-2">
                        </div>

                        <button type="submit"
                            class="h-9 px-4 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-md shadow-sm">
                            Save Schedule
                        </button>
                    </form>

                    {{-- Override next payout date --}}
                    <div class="border-t border-gray-100 pt-4">
                        <div class="text-xs font-medium text-gray-500 mb-2 uppercase tracking-wide">Override Next Payout Date</div>
                        <form method="POST" action="{{ route('payroll.schedule.override') }}" class="flex flex-wrap items-end gap-3">
                            @csrf @method('PATCH')
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Date</label>
                                <input type="date" name="override_date"
                                    value="{{ $schedule['override'] ?? '' }}"
                                    class="text-sm border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 h-9 px-2">
                            </div>
                            <button type="submit"
                                class="h-9 px-4 text-sm font-medium text-white bg-amber-500 hover:bg-amber-600 rounded-md shadow-sm">
                                Set Override
                            </button>
                            @if ($schedule['override'])
                            <button type="submit" name="override_date" value=""
                                class="h-9 px-4 text-sm font-medium text-gray-600 bg-white border border-gray-300 hover:bg-gray-50 rounded-md shadow-sm">
                                Clear Override
                            </button>
                            @endif
                        </form>
                    </div>

                </div>
            </div>

            {{-- Summary totals --}}
            <div class="grid grid-cols-3 gap-4">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 px-5 py-4">
                    <div class="text-xs font-medium text-indigo-500 uppercase tracking-wide">1099 Pay</div>
                    <div class="mt-1 text-2xl font-bold text-indigo-700">${{ number_format($totals['pay_1099'], 2) }}</div>
                </div>
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 px-5 py-4">
                    <div class="text-xs font-medium text-gray-500 uppercase tracking-wide">Non-1099 Pay</div>
                    <div class="mt-1 text-2xl font-bold text-gray-700">${{ number_format($totals['pay_non_1099'], 2) }}</div>
                </div>
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 px-5 py-4">
                    <div class="text-xs font-medium text-green-600 uppercase tracking-wide">Total Pay Out</div>
                    <div class="mt-1 text-2xl font-bold text-green-700">${{ number_format($totals['total'], 2) }}</div>
                </div>
            </div>

            {{-- Weekly payout history --}}
            @if ($periods)
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Weekly Payout History</h3>
                </div>
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-xs font-medium text-gray-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3 text-left">Pay Period</th>
                            <th class="px-5 py-3 text-right">1099 Pay</th>
                            <th class="px-5 py-3 text-right">Non-1099 Pay</th>
                            <th class="px-5 py-3 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($periods as $row)
                        @php $rowTotal = $row['pay_1099'] + $row['pay_non_1099']; @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3 whitespace-nowrap">
                                <span class="font-medium text-gray-800">
                                    {{ $row['period_start']->format('M j') }} – {{ $row['period_end']->format('M j, Y') }}
                                </span>
                                <div class="text-[10px] text-gray-400 mt-0.5">
                                    @if ($row['count_1099'] > 0)
                                        {{ $row['count_1099'] }} 1099 assignment{{ $row['count_1099'] !== 1 ? 's' : '' }}
                                    @endif
                                    @if ($row['count_1099'] > 0 && $row['count_non'] > 0)
                                        &middot;
                                    @endif
                                    @if ($row['count_non'] > 0)
                                        {{ $row['count_non'] }} non-1099 assignment{{ $row['count_non'] !== 1 ? 's' : '' }}
                                    @endif
                                </div>
                            </td>
                            <td class="px-5 py-3 text-right tabular-nums {{ $row['pay_1099'] > 0 ? 'text-indigo-700 font-medium' : 'text-gray-300' }}">
                                ${{ number_format($row['pay_1099'], 2) }}
                            </td>
                            <td class="px-5 py-3 text-right tabular-nums {{ $row['pay_non_1099'] > 0 ? 'text-gray-700' : 'text-gray-300' }}">
                                ${{ number_format($row['pay_non_1099'], 2) }}
                            </td>
                            <td class="px-5 py-3 text-right tabular-nums font-semibold text-green-700">
                                ${{ number_format($rowTotal, 2) }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50 border-t border-gray-200">
                        <tr>
                            <td class="px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Total</td>
                            <td class="px-5 py-3 text-right tabular-nums font-bold text-indigo-700">${{ number_format($totals['pay_1099'], 2) }}</td>
                            <td class="px-5 py-3 text-right tabular-nums font-bold text-gray-700">${{ number_format($totals['pay_non_1099'], 2) }}</td>
                            <td class="px-5 py-3 text-right tabular-nums font-bold text-green-700">${{ number_format($totals['total'], 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            @else
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 px-6 py-12 text-center text-gray-400 text-sm">
                No paid assignments in this period.
            </div>
            @endif

        </div>
    </div>
</x-app-layout>
