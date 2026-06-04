<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Rates</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded-md text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @php
                $canEdit   = auth()->user()->isAdmin();
                $isEditor  = auth()->user()->isEditor();
                $isReader  = auth()->user()->isReader();
            @endphp

            <form method="POST" action="{{ route('ratebook.update') }}">
                @csrf
                @method('PATCH')

                {{-- SR Base Rates --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden mb-6">
                    <div class="px-5 py-3 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">SR — Base Rates</h3>
                    </div>
                    <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-100 text-sm">
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
                    </table></div>
                </div>

                {{-- SR Modifiers --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden mb-6">
                    <div class="px-5 py-3 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">SR — Modifiers</h3>
                    </div>
                    <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-100 text-sm">
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
                    </table></div>
                </div>

                {{-- WD Base Rates --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden mb-6">
                    <div class="px-5 py-3 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">WD — Base Rates</h3>
                    </div>
                    <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-100 text-sm">
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
                    </table></div>
                </div>

                {{-- WD Modifiers --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden mb-6">
                    <div class="px-5 py-3 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">WD — Modifiers</h3>
                    </div>
                    <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-100 text-sm">
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
                    </table></div>
                </div>

                {{-- Editor Rates — hidden from readers --}}
                @if (!$isReader)
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden mb-6">
                    <div class="px-5 py-3 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">Editor Rates</h3>
                    </div>

                    {{-- How commission is calculated --}}
                    <div class="px-5 py-4 border-b border-gray-100 text-xs text-gray-500 space-y-1 leading-relaxed">
                        <p><span class="font-semibold text-gray-700">How editor commission is calculated:</span>
                        Commission is a percentage of the <em>pre-commission revenue</em> for each order — that is, the order total minus all reader costs and payment processing fees, before commission is deducted.
                        For orders that mix commission-eligible and ineligible services, only the eligible share of pre-commission revenue is used as the base.</p>
                        <p>Example: order total $200, reader cost $80, processing $6.10 → pre-commission = $113.90. At 6.5%, commission = $7.40.</p>
                    </div>

                    @if ($canEdit)
                        {{-- Admin: per-editor breakdown --}}
                        @if ($editorRates && $editorRates->isNotEmpty())
                            <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-100 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-5 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Editor</th>
                                        <th class="px-5 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Commission</th>
                                        <th class="px-5 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Weekly Flat</th>
                                        <th class="px-5 py-2"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($editorRates as $ed)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-5 py-2 text-gray-700 font-medium">{{ $ed['name'] }}</td>
                                            <td class="px-5 py-2 text-right font-mono text-gray-800">
                                                {{ $ed['commission'] !== null ? number_format($ed['commission'], 2) . '%' : '—' }}
                                            </td>
                                            <td class="px-5 py-2 text-right font-mono text-gray-800">
                                                {{ $ed['weekly_flat'] !== null ? '$' . number_format($ed['weekly_flat'], 2) : '—' }}
                                            </td>
                                            <td class="px-5 py-2 text-right">
                                                <a href="{{ route('admin.editors.edit', $ed['id']) }}"
                                                   class="text-xs text-indigo-500 hover:text-indigo-700 hover:underline">Edit</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table></div>
                        @else
                            <p class="px-5 py-4 text-sm text-gray-400 italic">No editors found.</p>
                        @endif

                    @elseif ($isEditor && $myEditorRates)
                        {{-- Editor: own rates, read-only --}}
                        <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-100 text-sm">
                            <tbody class="divide-y divide-gray-100">
                                <tr>
                                    <td class="px-5 py-3 text-gray-700 w-2/3">Your Commission Rate</td>
                                    <td class="px-5 py-3 text-right font-mono text-gray-800">
                                        {{ $myEditorRates['commission'] !== null ? number_format($myEditorRates['commission'], 2) . '%' : '—' }}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="px-5 py-3 text-gray-700 w-2/3">Your Weekly Flat Pay</td>
                                    <td class="px-5 py-3 text-right font-mono text-gray-800">
                                        {{ $myEditorRates['weekly_flat'] !== null ? '$' . number_format($myEditorRates['weekly_flat'], 2) : '—' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table></div>
                    @endif
                </div>
                @endif

                @if ($canEdit)
                    <div class="flex justify-end">
                        <x-primary-button>Save Rates</x-primary-button>
                    </div>
                @endif

            </form>

        </div>
    </div>
</x-app-layout>
