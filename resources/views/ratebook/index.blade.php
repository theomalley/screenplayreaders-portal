<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Ratebook</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded-md text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @php $canEdit = auth()->user()->isAdmin(); @endphp

            <form method="POST" action="{{ route('ratebook.update') }}">
                @csrf
                @method('PATCH')

                {{-- SR Base Rates --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden mb-6">
                    <div class="px-5 py-3 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">SR — Base Rates</h3>
                    </div>
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <tbody class="divide-y divide-gray-100">
                            @php
                            $srBaseRows = [
                                'rate_sr_script_coverage' => 'Script Coverage',
                                'rate_sr_notes_only'      => 'Notes-Only Coverage',
                                'rate_sr_short'           => 'Short Coverage',
                                'rate_sr_deep_dive'       => 'Deep-Dive Development Notes',
                                'rate_sr_budget'          => 'Budget Script Coverage',
                            ];
                            @endphp
                            @foreach ($srBaseRows as $key => $label)
                                <tr>
                                    <td class="px-5 py-3 text-gray-700 w-2/3">{{ $label }}</td>
                                    <td class="px-5 py-3 text-right">
                                        @if ($canEdit)
                                            <div class="flex items-center justify-end gap-1">
                                                <span class="text-gray-400 text-sm">$</span>
                                                <input type="number" name="{{ $key }}" value="{{ number_format($rates[$key], 2) }}"
                                                    min="0" max="9999.99" step="0.01"
                                                    class="w-24 text-right border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                                            </div>
                                            <x-input-error :messages="$errors->get($key)" class="mt-1 text-right" />
                                        @else
                                            <span class="font-mono text-gray-800">${{ number_format($rates[$key], 2) }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            <tr class="bg-gray-50">
                                <td class="px-5 py-3 text-gray-500 italic">Book Coverage</td>
                                <td class="px-5 py-3 text-right text-gray-400 text-xs">Custom per assignment</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                {{-- SR Modifiers --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden mb-6">
                    <div class="px-5 py-3 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">SR — Modifiers</h3>
                    </div>
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <tbody class="divide-y divide-gray-100">
                            @php
                            $srModRows = [
                                'rate_sr_rush'              => 'Rush (24h turnaround)',
                                'rate_sr_request'           => 'Reader Request',
                                'rate_sr_proofreading'      => 'Proofreading',
                                'rate_sr_oversized_121_160' => 'Oversized (121–160 pages)',
                            ];
                            @endphp
                            @foreach ($srModRows as $key => $label)
                                <tr>
                                    <td class="px-5 py-3 text-gray-700 w-2/3">{{ $label }}</td>
                                    <td class="px-5 py-3 text-right">
                                        @if ($canEdit)
                                            <div class="flex items-center justify-end gap-1">
                                                <span class="text-gray-400 text-sm">+$</span>
                                                <input type="number" name="{{ $key }}" value="{{ number_format($rates[$key], 2) }}"
                                                    min="0" max="9999.99" step="0.01"
                                                    class="w-24 text-right border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                                            </div>
                                            <x-input-error :messages="$errors->get($key)" class="mt-1 text-right" />
                                        @else
                                            <span class="font-mono text-gray-800">+${{ number_format($rates[$key], 2) }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            <tr class="bg-gray-50">
                                <td class="px-5 py-3 text-gray-500 italic">Oversized (161+ pages)</td>
                                <td class="px-5 py-3 text-right text-gray-400 text-xs">Custom per assignment</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                {{-- WD Base Rates --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden mb-6">
                    <div class="px-5 py-3 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">WD — Base Rates</h3>
                    </div>
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <tbody class="divide-y divide-gray-100">
                            @php
                            $wdBaseRows = [
                                'rate_wd_coverage'          => 'Coverage',
                                'rate_wd_development_notes' => 'Development Notes',
                            ];
                            @endphp
                            @foreach ($wdBaseRows as $key => $label)
                                <tr>
                                    <td class="px-5 py-3 text-gray-700 w-2/3">{{ $label }}</td>
                                    <td class="px-5 py-3 text-right">
                                        @if ($canEdit)
                                            <div class="flex items-center justify-end gap-1">
                                                <span class="text-gray-400 text-sm">$</span>
                                                <input type="number" name="{{ $key }}" value="{{ number_format($rates[$key], 2) }}"
                                                    min="0" max="9999.99" step="0.01"
                                                    class="w-24 text-right border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                                            </div>
                                            <x-input-error :messages="$errors->get($key)" class="mt-1 text-right" />
                                        @else
                                            <span class="font-mono text-gray-800">${{ number_format($rates[$key], 2) }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- WD Modifiers --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden mb-6">
                    <div class="px-5 py-3 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">WD — Modifiers</h3>
                    </div>
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <tbody class="divide-y divide-gray-100">
                            @php
                            $wdModRows = [
                                'rate_wd_rush'              => 'Rush (24h turnaround)',
                                'rate_wd_request'           => 'Reader Request',
                                'rate_wd_oversized_121_160' => 'Oversized (121–160 pages)',
                            ];
                            @endphp
                            @foreach ($wdModRows as $key => $label)
                                <tr>
                                    <td class="px-5 py-3 text-gray-700 w-2/3">{{ $label }}</td>
                                    <td class="px-5 py-3 text-right">
                                        @if ($canEdit)
                                            <div class="flex items-center justify-end gap-1">
                                                <span class="text-gray-400 text-sm">+$</span>
                                                <input type="number" name="{{ $key }}" value="{{ number_format($rates[$key], 2) }}"
                                                    min="0" max="9999.99" step="0.01"
                                                    class="w-24 text-right border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                                            </div>
                                            <x-input-error :messages="$errors->get($key)" class="mt-1 text-right" />
                                        @else
                                            <span class="font-mono text-gray-800">+${{ number_format($rates[$key], 2) }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            <tr class="bg-gray-50">
                                <td class="px-5 py-3 text-gray-500 italic">Oversized (161+ pages)</td>
                                <td class="px-5 py-3 text-right text-gray-400 text-xs">Custom per assignment</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                @if ($canEdit)
                    <div class="flex justify-end">
                        <x-primary-button>Save Rates</x-primary-button>
                    </div>
                @endif

            </form>

        </div>
    </div>
</x-app-layout>
