<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Test Data</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="bg-green-50 border border-green-200 rounded-lg px-4 py-3 text-sm text-green-700">
                    {{ session('success') }}
                </div>
            @endif

            <p class="text-sm text-gray-500">
                Test assignments are excluded from payroll, reader pay, and HelpScout drafts.
                They are tagged <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-200 text-amber-800">TEST</span>
                everywhere in the portal so testers know what they're working with.
            </p>

            {{-- Test Script --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5 space-y-3">
                <div>
                    <h3 class="text-sm font-semibold text-gray-700">Test Script</h3>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Upload any PDF (a short screenplay works great). All seeded test assignments will use it so readers can view the script and submit coverage end-to-end. The file is stored on the server — no Google Drive sharing required.
                    </p>
                </div>

                @if($testScriptId === '__LOCAL_TEST__')
                    <div class="flex items-center gap-3 p-3 bg-green-50 border border-green-200 rounded-md">
                        <svg class="w-4 h-4 text-green-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div class="flex-1 min-w-0">
                            <span class="text-xs font-medium text-green-700">Script uploaded: </span>
                            <span class="text-xs text-green-600 font-mono">{{ $testScriptName ?: 'test-script.pdf' }}</span>
                        </div>
                        <form method="POST" action="{{ route('test-data.script') }}">
                            @csrf
                            <button type="submit" class="text-xs text-red-500 hover:text-red-700 whitespace-nowrap">Remove</button>
                        </form>
                    </div>
                @endif

                <form method="POST" action="{{ route('test-data.script') }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-3">
                    @csrf
                    <input type="file" name="pdf" accept="application/pdf" required
                           class="text-sm text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-xs file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                    <button type="submit"
                            class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 transition-colors whitespace-nowrap">
                        {{ $testScriptId ? 'Replace' : 'Upload Script' }}
                    </button>
                    @if(!$testScriptId)
                        <span class="text-xs text-amber-600">Not set — test assignments will have no viewable script</span>
                    @endif
                </form>
            </div>

            {{-- Status bar --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 px-5 py-4 flex items-center justify-between gap-4">
                <div>
                    <div class="text-xs font-medium text-gray-500 uppercase tracking-wide">Test Assignments</div>
                    <div class="text-2xl font-bold text-gray-800 mt-0.5">{{ $testCount }}</div>
                </div>
                @if($pendingReset > 0)
                <div class="text-right">
                    <div class="text-xs font-medium text-amber-600 uppercase tracking-wide">In Progress / Completed</div>
                    <div class="text-2xl font-bold text-amber-700 mt-0.5">{{ $pendingReset }}</div>
                    <div class="text-[10px] text-gray-400 mt-0.5">will reset in ≤4 h if auto-reset is on</div>
                </div>
                @endif
            </div>

            {{-- Seed --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5 space-y-4">
                <div>
                    <h3 class="text-sm font-semibold text-gray-700">Seed Test Assignments</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Creates fresh unassigned test assignments with varied types, page counts, and titles.</p>
                </div>
                <form method="POST" action="{{ route('test-data.seed') }}" class="flex items-end gap-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Count</label>
                        <select name="count" class="text-sm border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                            @foreach ([5, 10, 20, 50] as $n)
                                <option value="{{ $n }}" @selected($n === 10)>{{ $n }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit"
                            class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 transition-colors">
                        Seed
                    </button>
                </form>
            </div>

            {{-- Reset --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5 space-y-4">
                <div>
                    <h3 class="text-sm font-semibold text-gray-700">Reset All Test Assignments</h3>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Sets every test assignment back to <strong>Available</strong>, clears all coverage data and assignment timestamps.
                        Testers can start again immediately.
                    </p>
                </div>
                <form method="POST" action="{{ route('test-data.reset') }}"
                      onsubmit="return confirm('Reset all {{ $testCount }} test assignment(s) to Available?')">
                    @csrf
                    <button type="submit"
                            @class(['px-4 py-2 text-sm font-medium rounded-md transition-colors text-white',
                                    'bg-amber-500 hover:bg-amber-600' => $testCount > 0,
                                    'bg-gray-300 cursor-not-allowed' => $testCount === 0])
                            @disabled($testCount === 0)>
                        Reset All ({{ $testCount }})
                    </button>
                </form>
            </div>

            {{-- Auto-reset --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5 space-y-4">
                <div>
                    <h3 class="text-sm font-semibold text-gray-700">Auto-Reset on Completion</h3>
                    <p class="text-xs text-gray-500 mt-0.5">
                        When enabled, any test assignment that reaches <strong>Completed</strong> status is automatically reset
                        back to Available after <strong>4 hours</strong>. Lets testers loop through the full workflow without manual resets.
                    </p>
                </div>
                <form method="POST" action="{{ route('test-data.auto-reset') }}" class="flex items-center gap-4">
                    @csrf
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="enabled" value="1"
                               @checked($autoReset)
                               onchange="this.form.submit()"
                               class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 cursor-pointer">
                        <span class="text-sm font-medium {{ $autoReset ? 'text-green-700' : 'text-gray-600' }}">
                            {{ $autoReset ? 'Enabled' : 'Disabled' }}
                        </span>
                    </label>
                </form>
            </div>

            {{-- Delete all --}}
            <div class="bg-white rounded-lg shadow-sm border border-red-100 p-5 space-y-4">
                <div>
                    <h3 class="text-sm font-semibold text-red-700">Delete All Test Assignments</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Permanently deletes every test assignment and its coverage data. Cannot be undone.</p>
                </div>
                <form method="POST" action="{{ route('test-data.destroy') }}"
                      onsubmit="return confirm('Permanently delete all {{ $testCount }} test assignment(s)? This cannot be undone.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            @class(['px-4 py-2 text-sm font-medium rounded-md transition-colors text-white',
                                    'bg-red-600 hover:bg-red-700' => $testCount > 0,
                                    'bg-gray-300 cursor-not-allowed' => $testCount === 0])
                            @disabled($testCount === 0)>
                        Delete All ({{ $testCount }})
                    </button>
                </form>
            </div>

        </div>
    </div>
</x-app-layout>
