<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('budget-admin.index') }}" class="text-gray-400 hover:text-gray-600">&larr;</a>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Budget — Test Calculator</h2>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            @if ($errors->any())
                <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-800 rounded-md text-sm">
                    <strong>Calculation error:</strong> {{ $errors->first() }}
                </div>
            @endif

            {{-- Input Form --}}
            <form method="POST" action="{{ route('budget-admin.test.run') }}" class="mb-6">
                @csrf

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-5 py-3 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">Test Parameters</h3>
                    </div>
                    <div class="p-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Budget Amount ($)</label>
                            <input type="number" name="budget"
                                   value="{{ old('budget', $input['budget'] ?? 500000) }}"
                                   min="25000" max="250000000" step="any"
                                   class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                                   required />
                            <p class="text-xs text-gray-400 mt-1">$25K – $250M</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">State</label>
                            <select name="shootingstate"
                                    class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                <option value="0">Default (California rates)</option>
                                @foreach ($states as $state)
                                    <option value="{{ $state }}" {{ ($input['shootingstate'] ?? '') === $state ? 'selected' : '' }}>{{ $state }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Guilds</label>
                            <select name="guilds"
                                    class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                <option value="all" selected>All guilds (automatic)</option>
                                <option value="sag_only">SAG-AFTRA only</option>
                                <option value="none">No guilds</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Cast Members</label>
                            <input type="number" name="cast_count"
                                   value="{{ old('cast_count', $input['usercastsize'] ?? 4) }}"
                                   min="0" max="25"
                                   class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                        </div>
                    </div>

                    <div class="px-5 pb-4 flex items-center gap-4">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="hidden" name="use_defaults" value="0" />
                            <input type="checkbox" name="use_defaults" value="1"
                                   {{ old('use_defaults', '1') === '1' ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                            Use default weeks for budget level
                        </label>
                        <x-primary-button>Run Calculation</x-primary-button>
                    </div>
                </div>
            </form>

            {{-- Delivery Test --}}
            <form method="POST" action="{{ route('budget-admin.test.deliver') }}" class="mb-6"
                  x-data="{ showDeliver: false }">
                @csrf

                <div class="bg-white rounded-lg shadow-sm border border-amber-200 overflow-hidden">
                    <button @click.prevent="showDeliver = !showDeliver" type="button"
                            class="w-full px-5 py-3 bg-amber-50 border-b border-amber-200 flex items-center justify-between text-left">
                        <h3 class="text-sm font-semibold text-amber-800 uppercase tracking-wider">Generate Files & Send Email</h3>
                        <svg class="w-4 h-4 text-amber-400 transition-transform" :class="showDeliver && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="showDeliver" x-collapse>
                        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Budget Amount ($)</label>
                                <input type="number" name="budget"
                                       value="{{ old('budget', $input['budget'] ?? 500000) }}"
                                       min="25000" max="250000000" step="any"
                                       class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                                       required />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">State</label>
                                <select name="shootingstate"
                                        class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                    <option value="0">Default (California rates)</option>
                                    @foreach ($states as $state)
                                        <option value="{{ $state }}" {{ ($input['shootingstate'] ?? '') === $state ? 'selected' : '' }}>{{ $state }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Guilds</label>
                                <select name="guilds"
                                        class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                    <option value="all" selected>All guilds (automatic)</option>
                                    <option value="sag_only">SAG-AFTRA only</option>
                                    <option value="none">No guilds</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Cast Members</label>
                                <input type="number" name="cast_count"
                                       value="{{ old('cast_count', $input['usercastsize'] ?? 4) }}"
                                       min="0" max="25"
                                       class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                            </div>
                        </div>
                        <div class="px-5 pb-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Send to Email</label>
                                <input type="email" name="test_email"
                                       value="{{ old('test_email', auth()->user()->email) }}"
                                       placeholder="you@example.com"
                                       class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                                       required />
                            </div>
                            <div class="flex items-end gap-4">
                                <label class="flex items-center gap-2 text-sm text-gray-700 pb-2">
                                    <input type="hidden" name="topsheet_only" value="0" />
                                    <input type="checkbox" name="topsheet_only" value="1"
                                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                    Topsheet only (1-page PDF, no XLSX)
                                </label>
                                <input type="hidden" name="use_defaults" value="1" />
                                <button type="submit"
                                        class="px-4 py-2 bg-amber-600 text-white text-sm font-semibold rounded-md hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 whitespace-nowrap"
                                        onclick="return confirm('This will create a real budget order, generate files in Google Drive, and send an email. Continue?')">
                                    Generate &amp; Deliver
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            {{-- Delivery Status --}}
            @if (session('success'))
                <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded-md text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if ($delivery)
                <div class="mb-4 bg-amber-50 border border-amber-200 rounded-lg overflow-hidden">
                    <div class="px-5 py-3 bg-amber-100 border-b border-amber-200">
                        <h3 class="text-sm font-semibold text-amber-800 uppercase tracking-wider">Delivery Queued</h3>
                    </div>
                    <div class="p-5 grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                        <div>
                            <span class="text-amber-600 text-xs uppercase">Order ID</span>
                            <div class="font-mono font-medium text-amber-900">#{{ $delivery['order_id'] }}</div>
                        </div>
                        <div>
                            <span class="text-amber-600 text-xs uppercase">Budget</span>
                            <div class="font-mono font-medium text-amber-900">${{ number_format((float) $delivery['budget'], 0) }}</div>
                        </div>
                        <div>
                            <span class="text-amber-600 text-xs uppercase">Delivering to</span>
                            <div class="font-medium text-amber-900">{{ $delivery['email'] }}</div>
                        </div>
                        <div>
                            <span class="text-amber-600 text-xs uppercase">Format</span>
                            <div class="font-medium text-amber-900">{{ $delivery['topsheet'] ? 'Topsheet PDF only' : 'Full PDF + XLSX' }}</div>
                        </div>
                    </div>
                    <div class="px-5 pb-3 text-xs text-amber-600">
                        The queue worker will copy the Google Sheets template, fill tokens, export files, and send the email. Check the order status below or your inbox.
                    </div>
                </div>
            @endif

            {{-- Results --}}
            @if ($payload)
                <div class="space-y-4">
                    {{-- Summary Card --}}
                    <div class="bg-indigo-600 rounded-lg shadow-sm text-white p-5">
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-center">
                            <div>
                                <div class="text-indigo-200 text-xs uppercase tracking-wider">Budget</div>
                                <div class="text-2xl font-bold">${{ number_format((float)($payload['budget'] ?? 0), 0) }}</div>
                            </div>
                            <div>
                                <div class="text-indigo-200 text-xs uppercase tracking-wider">Class</div>
                                <div class="text-2xl font-bold">{{ $payload['budgetclass'] ?? '?' }}</div>
                            </div>
                            <div>
                                <div class="text-indigo-200 text-xs uppercase tracking-wider">Payload Keys</div>
                                <div class="text-2xl font-bold">{{ count($payload) }}</div>
                            </div>
                            <div>
                                <div class="text-indigo-200 text-xs uppercase tracking-wider">Calc Time</div>
                                <div class="text-2xl font-bold">{{ $elapsed }}ms</div>
                            </div>
                        </div>
                    </div>

                    {{-- Guild Codes --}}
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                        <div class="px-5 py-3 bg-gray-50 border-b border-gray-200">
                            <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">Guild Codes & Rates</h3>
                        </div>
                        <div class="p-5 grid grid-cols-2 sm:grid-cols-3 gap-3 text-sm">
                            @foreach (['SAG', 'WGA', 'DGADIR', 'DGAUPM', 'IATSE', 'TEAMSTERS'] as $guild)
                                <div class="flex justify-between border-b border-gray-100 pb-1">
                                    <span class="text-gray-500">{{ $guild }}</span>
                                    <span class="font-mono">
                                        {{ $payload['guildcode' . $guild] ?? '—' }}
                                        <span class="text-gray-400 text-xs ml-1">{{ $payload['guildcode' . $guild . 'text'] ?? '' }}</span>
                                    </span>
                                </div>
                            @endforeach
                            <div class="flex justify-between border-b border-gray-100 pb-1">
                                <span class="text-gray-500">Non-union key</span>
                                <span class="font-mono">${{ is_numeric($payload['rate_nonunionkey'] ?? '') ? number_format((float)$payload['rate_nonunionkey'], 2) : '—' }}/wk</span>
                            </div>
                            <div class="flex justify-between border-b border-gray-100 pb-1">
                                <span class="text-gray-500">SAG rate</span>
                                <span class="font-mono">${{ is_numeric($payload['rate_SAG'] ?? '') ? number_format((float)$payload['rate_SAG'], 2) : '—' }}/wk</span>
                            </div>
                            <div class="flex justify-between border-b border-gray-100 pb-1">
                                <span class="text-gray-500">Min wage</span>
                                <span class="font-mono">${{ is_numeric($payload['rate_minimumwage'] ?? '') ? number_format((float)$payload['rate_minimumwage'], 2) : '—' }}/hr</span>
                            </div>
                        </div>
                    </div>

                    {{-- Schedule --}}
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                        <div class="px-5 py-3 bg-gray-50 border-b border-gray-200">
                            <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">Schedule</h3>
                        </div>
                        <div class="p-5 grid grid-cols-4 gap-4 text-sm text-center">
                            @foreach (['PREP', 'SHOOT', 'WRAP', 'POST'] as $phase)
                                <div>
                                    <div class="text-gray-500 text-xs uppercase">{{ $phase }}</div>
                                    <div class="font-mono text-lg">{{ $payload['weeks' . $phase] ?? 0 }}</div>
                                    <div class="text-gray-400 text-xs">weeks</div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Crew Positions with non-empty labor --}}
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                        <div class="px-5 py-3 bg-gray-50 border-b border-gray-200">
                            <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">Crew Labor (non-zero positions)</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="border-b border-gray-100 bg-gray-50/50">
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Position</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Rate (shoot)</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Weeks (shoot)</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Labor Total</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">FICA</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @php
                                        $positions = \App\Models\Budget\CrewPosition::orderBy('sort_order')->get();
                                    @endphp
                                    @foreach ($positions as $pos)
                                        @php
                                            $prefix = '_' . $pos->line_item_id . $pos->slug;
                                            $labor = $payload[$prefix . 'labortotal'] ?? '';
                                        @endphp
                                        @if (is_numeric($labor) && $labor > 0)
                                            <tr class="hover:bg-blue-50/30">
                                                <td class="px-4 py-1.5 text-gray-700">{{ $pos->name }} <span class="text-gray-400 text-xs">#{{ $pos->line_item_id }}</span></td>
                                                <td class="px-3 py-1.5 text-right font-mono">${{ number_format((float)($payload[$prefix . 'rateshoot'] ?? 0), 2) }}</td>
                                                <td class="px-3 py-1.5 text-right font-mono">{{ $payload[$prefix . 'weeksshoot'] ?? 0 }}</td>
                                                <td class="px-3 py-1.5 text-right font-mono font-medium">${{ number_format((float)$labor, 2) }}</td>
                                                <td class="px-3 py-1.5 text-right font-mono text-gray-500">${{ number_format((float)($payload[$prefix . 'FICA'] ?? 0), 2) }}</td>
                                            </tr>
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Full Payload (collapsible) --}}
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden" x-data="{ open: false }">
                        <button @click="open = !open" type="button"
                                class="w-full px-5 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between text-left">
                            <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">Full Payload ({{ count($payload) }} keys)</h3>
                            <svg class="w-4 h-4 text-gray-400 transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div x-show="open" x-collapse class="p-4 max-h-[600px] overflow-auto">
                            <table class="w-full text-xs font-mono">
                                <tbody>
                                    @foreach ($payload as $key => $value)
                                        @if ($value !== '' && $value !== null)
                                            <tr class="border-b border-gray-50 hover:bg-yellow-50/50">
                                                <td class="py-0.5 pr-3 text-gray-500 whitespace-nowrap align-top">@{{ {{ $key }} }}</td>
                                                <td class="py-0.5 text-gray-800 break-all">{{ is_numeric($value) ? number_format((float) $value, 2) : $value }}</td>
                                            </tr>
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
