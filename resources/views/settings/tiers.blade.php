<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Settings</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">

            @include('settings._nav')

            <div class="space-y-6">

            @if(session('success'))
                <div class="bg-green-50 border border-green-200 rounded-lg px-4 py-3 text-sm text-green-700">
                    {{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm text-red-700 space-y-1">
                    @foreach($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <div class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-3 text-xs text-blue-800">
                Readers can never see what tier they (or anyone else) are in — tier assignment and all settings below are admin/editor-only.
            </div>

            {{-- ===== PER-TIER CARDS ===== --}}
            @foreach ($tiers as $tier)
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-semibold text-gray-800">
                            {{ $tier->name }}
                            @if($tier->is_onboarding)
                                <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-amber-100 text-amber-800 uppercase tracking-wide">Onboarding</span>
                            @endif
                        </h3>
                        @unless($tier->is_onboarding)
                            <form method="POST" action="{{ route('tiers.destroy', $tier) }}"
                                  onsubmit="return confirm('Delete tier &quot;{{ $tier->name }}&quot;? This only works if no readers or assignments are currently in it.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs text-red-500 hover:text-red-700 hover:underline">Delete</button>
                            </form>
                        @endunless
                    </div>

                    <form method="POST" action="{{ route('tiers.update', $tier) }}" class="space-y-4">
                        @csrf
                        @method('PATCH')

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="name_{{ $tier->id }}" value="Name" />
                                <x-text-input id="name_{{ $tier->id }}" name="name" type="text"
                                    value="{{ $tier->name }}" class="mt-1 block w-full text-sm" required />
                            </div>
                            <div>
                                <x-input-label for="position_{{ $tier->id }}" value="Display order" />
                                <input type="number" id="position_{{ $tier->id }}" name="position" min="0" step="1"
                                    value="{{ $tier->position }}"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="timeout_hours_{{ $tier->id }}" value="Timeout (hours)" />
                                <p class="text-xs text-gray-400 mb-1">If nobody in this tier accepts within this many hours, the assignment transfers to the tier below. Leave blank to disable.</p>
                                <input type="number" id="timeout_hours_{{ $tier->id }}" name="timeout_hours" min="1" max="8760"
                                    value="{{ $tier->timeout_hours }}"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                            </div>
                            <div>
                                <x-input-label for="escalates_to_tier_id_{{ $tier->id }}" value="Escalates to" />
                                <select id="escalates_to_tier_id_{{ $tier->id }}" name="escalates_to_tier_id"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                    <option value="">— None —</option>
                                    @foreach ($tiers as $target)
                                        @continue($target->id === $tier->id || $target->is_onboarding)
                                        <option value="{{ $target->id }}" @selected($tier->escalates_to_tier_id === $target->id)>{{ $target->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div>
                            <x-input-label value="Assignment types this tier can see &amp; accept" />
                            <p class="text-xs text-gray-400 mb-1">Leave all unchecked for no restriction (this tier sees every type).</p>
                            <div class="flex flex-wrap gap-x-4 gap-y-1.5 mt-1">
                                @foreach ($assignmentTypes as $value => $label)
                                    <label class="flex items-center gap-1.5 text-xs text-gray-700">
                                        <input type="checkbox" name="allowed_assignment_types[]" value="{{ $value }}"
                                            @checked(in_array($value, $tier->allowed_assignment_types ?? []))
                                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                        {{ $label }}
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <x-primary-button>Save</x-primary-button>
                    </form>
                </div>
            @endforeach

            {{-- ===== ADD TIER ===== --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-800 mb-3">Add a Tier</h3>
                <form method="POST" action="{{ route('tiers.store') }}" class="flex items-end gap-3 flex-wrap">
                    @csrf
                    <div>
                        <x-input-label for="new_tier_name" value="Name" />
                        <x-text-input id="new_tier_name" name="name" type="text" placeholder="e.g. Tier 3"
                            class="mt-1 block w-56 text-sm" required />
                    </div>
                    <x-primary-button>Create Tier</x-primary-button>
                </form>
            </div>

            {{-- ===== CROSS-TIER VISIBILITY MATRIX ===== --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-800 mb-1">Cross-Tier Visibility</h3>
                <p class="text-xs text-gray-500 mb-4">
                    For each row (a reader's own tier), check the columns whose assignment pool that reader can also see and accept from.
                    A row for the onboarding tier only grants <strong>viewing</strong> — onboarding readers can never accept outside their sandbox assignment, no matter what's checked here.
                </p>

                <form method="POST" action="{{ route('tiers.visibility.update') }}">
                    @csrf
                    @method('PATCH')
                    <div class="overflow-x-auto">
                        <table class="text-sm border-collapse">
                            <thead>
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">From \ To</th>
                                    @foreach ($tiers as $toTier)
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">{{ $toTier->name }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($tiers as $fromTier)
                                    <tr class="border-t border-gray-100">
                                        <td class="px-3 py-2 font-medium text-gray-700 whitespace-nowrap">
                                            {{ $fromTier->name }}
                                            @if($fromTier->is_onboarding)
                                                <span class="block text-[10px] font-normal text-gray-400">(view only)</span>
                                            @endif
                                        </td>
                                        @foreach ($tiers as $toTier)
                                            <td class="px-3 py-2 text-center">
                                                @if($fromTier->id === $toTier->id)
                                                    <span class="text-gray-300">—</span>
                                                @else
                                                    @php
                                                        $existing = optional($crossVisibility->get($fromTier->id))
                                                            ->firstWhere('to_tier_id', $toTier->id);
                                                    @endphp
                                                    <input type="checkbox"
                                                           name="visibility[{{ $fromTier->id }}][{{ $toTier->id }}]"
                                                           value="1"
                                                           @checked($existing?->can_view)
                                                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <x-primary-button class="mt-4">Save Visibility Matrix</x-primary-button>
                </form>
            </div>

            </div>
        </div>
    </div>
</x-app-layout>
