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
            @if (session('success'))
                <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded-md text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if ($delivery)
                <div class="mb-4 bg-amber-50 border border-amber-200 rounded-lg p-5">
                    <h3 class="text-sm font-semibold text-amber-800 uppercase tracking-wider mb-3">Delivery Queued</h3>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                        <div><span class="text-amber-600 text-xs uppercase">Order ID</span><div class="font-mono font-medium text-amber-900">#{{ $delivery['order_id'] }}</div></div>
                        <div><span class="text-amber-600 text-xs uppercase">Budget</span><div class="font-mono font-medium text-amber-900">${{ number_format((float) $delivery['budget'], 0) }}</div></div>
                        <div><span class="text-amber-600 text-xs uppercase">Delivering to</span><div class="font-medium text-amber-900">{{ $delivery['email'] }}</div></div>
                        <div><span class="text-amber-600 text-xs uppercase">Format</span><div class="font-medium text-amber-900">{{ $delivery['topsheet'] ? 'Topsheet PDF only' : 'Full PDF + XLSX' }}</div></div>
                    </div>
                </div>
            @endif

            {{-- Unified Test Form --}}
            <form method="POST" action="{{ route('budget-admin.test.run') }}" class="mb-6"
                  x-data="{
                      states: @js($states),
                      randomize() {
                          const budgetRanges = [
                              [25000, 49999], [50000, 199999], [200000, 499999],
                              [500000, 1999999], [2000000, 3499999], [3500000, 10999999],
                              [11000000, 24999999], [25000000, 100000000]
                          ];
                          const range = budgetRanges[Math.floor(Math.random() * budgetRanges.length)];
                          this.$refs.budget.value = Math.floor(range[0] + Math.random() * (range[1] - range[0]));

                          const stateOpts = this.$refs.state.options;
                          this.$refs.state.selectedIndex = Math.floor(Math.random() * stateOpts.length);

                          const guildOpts = ['all', 'sag_only', 'none'];
                          this.$refs.guilds.value = guildOpts[Math.floor(Math.random() * guildOpts.length)];

                          this.$refs.cast_count.value = Math.floor(Math.random() * 16) + 1;
                          this.$refs.use_defaults.checked = Math.random() > 0.3;

                          // Randomize customization points (whole numbers, sum to exactly 10)
                          let remaining = 10;
                          const fields = ['usercast','userstunts','usertravel','userspfx','usermufx','useranimals','uservfx'];
                          fields.forEach((f, i) => {
                              if (i === fields.length - 1) {
                                  this.$refs[f].value = remaining;
                              } else {
                                  const val = Math.floor(Math.random() * (Math.min(remaining, 6) + 1));
                                  this.$refs[f].value = val;
                                  remaining -= val;
                              }
                          });
                      }
                  }">
                @csrf

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-5 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">Test Parameters</h3>
                        <button type="button" @click="randomize()"
                                class="px-3 py-1 text-xs font-semibold text-indigo-700 bg-indigo-100 rounded-full hover:bg-indigo-200 transition">
                            Randomize
                        </button>
                    </div>

                    <div class="p-5 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Budget ($)</label>
                            <input type="number" name="budget" x-ref="budget"
                                   value="{{ old('budget', $input['budget'] ?? 500000) }}"
                                   min="25000" max="250000000" step="any"
                                   class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" required />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">State</label>
                            <select name="shootingstate" x-ref="state"
                                    class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                <option value="0">Default (CA)</option>
                                @foreach ($states as $state)
                                    <option value="{{ $state }}" {{ ($input['shootingstate'] ?? '') === $state ? 'selected' : '' }}>{{ $state }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Guilds</label>
                            <select name="guilds" x-ref="guilds"
                                    class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                <option value="all" {{ ($input['_guilds'] ?? 'all') === 'all' ? 'selected' : '' }}>All (automatic)</option>
                                <option value="sag_only" {{ ($input['_guilds'] ?? '') === 'sag_only' ? 'selected' : '' }}>SAG only</option>
                                <option value="none" {{ ($input['_guilds'] ?? '') === 'none' ? 'selected' : '' }}>None</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Cast Count</label>
                            <input type="number" name="cast_count" x-ref="cast_count"
                                   value="{{ old('cast_count', $input['usercastsize'] ?? 4) }}"
                                   min="0" max="25"
                                   class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                        </div>
                        <div class="flex items-end">
                            <label class="flex items-center gap-2 text-sm text-gray-700 pb-2">
                                <input type="hidden" name="use_defaults" value="0" />
                                <input type="checkbox" name="use_defaults" value="1" x-ref="use_defaults"
                                       {{ old('use_defaults', '1') === '1' ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                Default weeks
                            </label>
                        </div>
                    </div>

                    {{-- Customization Points --}}
                    <div class="px-5 pb-4">
                        <label class="block text-xs font-medium text-gray-500 mb-2">Customization Points (max 10 total)</label>
                        <div class="grid grid-cols-4 sm:grid-cols-7 gap-2">
                            @php
                                $pointFields = [
                                    'usercast' => 'Cast', 'userstunts' => 'Stunts', 'usertravel' => 'Travel',
                                    'userspfx' => 'SpFX', 'usermufx' => 'MuFX', 'useranimals' => 'Animals', 'uservfx' => 'VFX',
                                ];
                            @endphp
                            @foreach ($pointFields as $key => $label)
                                <div>
                                    <label class="block text-xs text-gray-400 text-center">{{ $label }}</label>
                                    <input type="number" name="{{ $key }}" x-ref="{{ $key }}"
                                           value="{{ old($key, $input[$key] ?? '') }}"
                                           min="0" max="10" step="1" placeholder="—"
                                           class="w-full text-center border-gray-300 rounded shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm py-1" />
                                </div>
                            @endforeach
                        </div>
                        <p class="text-xs text-gray-400 mt-1">Leave blank to use defaults for budget level</p>
                    </div>

                    <div class="px-5 pb-4 flex items-center gap-3 border-t border-gray-100 pt-3">
                        <x-primary-button>Run Calculation</x-primary-button>
                    </div>
                </div>
            </form>

            {{-- Results --}}
            @if ($payload)
                <div class="space-y-4">

                    {{-- Budget vs Grand Total Comparison --}}
                    @php
                        $budgetAmt = (float)($payload['budget'] ?? 0);
                        $contingency = (float)($payload['contingency_total'] ?? 0);
                        $presurplus = (float)($payload['presurplus_total_FINAL'] ?? 0);
                        $engineGrandTotal = $presurplus + $contingency;

                        // Sum surplus items
                        $surplusKeys = ['_570additionalcastbudget','_572agentattyfees','_610stuntslaborbudget','_620purchases','_630rentals','_640boxrentals','_699miscexpenses','_710travel','_720lodging','_799miscexpenses','_2310makeupfxlaborbudget','_2312materials','_2314rentals','_2399miscexpenses','_2410specialfxlaborbudget','_2412purchasesrentals','_2414manufacturing','_2416riggingstriking','_2418boxrentals','_2499miscexpenses','_2510animalslaborbudget','_2512animals','_2514animalcarefeeding','_2516animaltravellodging','_2810visualfxbudget'];
                        $surplusTotal = 0;
                        foreach ($surplusKeys as $k) $surplusTotal += (float)($payload[$k] ?? 0);
                        $engineGrandTotal += $surplusTotal;

                        $gap = $budgetAmt - $engineGrandTotal;
                        $gapPct = $budgetAmt > 0 ? abs($gap / $budgetAmt * 100) : 0;
                        $isMatch = abs($gap) < 1; // within $1
                    @endphp

                    <div class="rounded-lg shadow-sm border {{ $isMatch ? 'bg-green-600 border-green-700' : 'bg-red-600 border-red-700' }} text-white p-5">
                        <div class="grid grid-cols-2 sm:grid-cols-5 gap-4 text-center">
                            <div>
                                <div class="text-xs uppercase tracking-wider opacity-75">Budget</div>
                                <div class="text-xl font-bold">${{ number_format($budgetAmt, 0) }}</div>
                            </div>
                            <div>
                                <div class="text-xs uppercase tracking-wider opacity-75">Engine Total</div>
                                <div class="text-xl font-bold">${{ number_format($engineGrandTotal, 2) }}</div>
                            </div>
                            <div>
                                <div class="text-xs uppercase tracking-wider opacity-75">Gap</div>
                                <div class="text-xl font-bold">{{ $isMatch ? 'MATCH' : '$' . number_format(abs($gap), 2) }}</div>
                            </div>
                            <div>
                                <div class="text-xs uppercase tracking-wider opacity-75">Class</div>
                                <div class="text-xl font-bold">{{ $payload['budgetclass'] ?? '?' }}</div>
                            </div>
                            <div>
                                <div class="text-xs uppercase tracking-wider opacity-75">Calc Time</div>
                                <div class="text-xl font-bold">{{ $elapsed }}ms</div>
                            </div>
                        </div>
                        @if (!$isMatch)
                            <div class="mt-2 text-center text-sm opacity-90">
                                Off by {{ number_format($gapPct, 2) }}% — presurplus: ${{ number_format($presurplus, 2) }}, contingency: ${{ number_format($contingency, 2) }}, surplus: ${{ number_format($surplusTotal, 2) }}
                            </div>
                        @endif
                    </div>

                    {{-- Deliver This Budget --}}
                    <form method="POST" action="{{ route('budget-admin.test.deliver') }}" class="bg-amber-50 border border-amber-200 rounded-lg p-4 flex items-center gap-4 flex-wrap">
                        @csrf
                        {{-- Pass through all the same parameters that produced these results --}}
                        @foreach ($input as $k => $v)
                            <input type="hidden" name="{{ $k }}" value="{{ $v }}" />
                        @endforeach
                        <input type="hidden" name="use_defaults" value="{{ $input['userusetimedefaults'] ?? '1' }}" />
                        <div class="flex items-center gap-2">
                            <label class="text-sm font-medium text-amber-800">Email:</label>
                            <input type="email" name="test_email" value="{{ auth()->user()->email }}"
                                   class="border-amber-300 rounded-md shadow-sm focus:ring-amber-500 focus:border-amber-500 text-sm w-64" required />
                        </div>
                        <label class="flex items-center gap-2 text-sm text-amber-800">
                            <input type="hidden" name="topsheet_only" value="0" />
                            <input type="checkbox" name="topsheet_only" value="1"
                                   class="rounded border-amber-300 text-amber-600 focus:ring-amber-500" />
                            Topsheet only
                        </label>
                        <button type="submit"
                                class="px-4 py-2 bg-amber-600 text-white text-sm font-semibold rounded-md hover:bg-amber-700 whitespace-nowrap"
                                onclick="return confirm('Generate files and send email for this exact budget?')">
                            Deliver This Budget
                        </button>
                    </form>

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
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                        <div class="grid grid-cols-4 gap-4 text-sm text-center">
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
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @php $positions = \App\Models\Budget\CrewPosition::orderBy('sort_order')->get(); @endphp
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
