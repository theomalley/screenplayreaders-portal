<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Settings</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">

            @include('settings._nav')
            @include('settings.services._nav')

            <div class="space-y-6">

            @if(session('success'))
                <div class="bg-green-50 border border-green-200 rounded-lg px-4 py-3 text-sm text-green-700">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('warning'))
                <div class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 text-sm text-amber-800">
                    {{ session('warning') }}
                </div>
            @endif

            {{-- Video Consulting --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-800 mb-1">Video Consulting</h3>
                <p class="text-xs text-gray-500 mb-4">
                    Sitewide default for the video consulting feature. Turning this off hides the Consulting
                    tab for every reader <strong>except</strong> ones an admin has explicitly set to "Always enabled"
                    on their profile (Readers &gt; edit profile) — that per-reader override always wins over this setting.
                </p>
                <form method="POST" action="{{ route('settings.video-consulting') }}">
                    @csrf
                    @method('PATCH')
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="hidden" name="video_consulting_enabled" value="0" />
                        <input type="checkbox" name="video_consulting_enabled" value="1"
                               {{ $videoConsultingEnabled ? 'checked' : '' }}
                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                        <span class="text-sm text-gray-700">Video consulting enabled sitewide</span>
                    </label>
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <x-input-label value="Reader's \"minimum notice before a call\" — allowed range (hours)" />
                        <p class="text-xs text-gray-500 mb-2">
                            Bounds the per-reader setting (Readers &gt; edit profile, or the reader's own Profile
                            page) for how much notice they can require between a customer ordering a call and
                            their first offered slot.
                        </p>
                        <div class="flex items-center gap-2">
                            <input type="number" name="lead_hours_min"
                                   value="{{ old('lead_hours_min', $videoConsultingLeadHoursBounds['min']) }}"
                                   min="0" max="720" step="1"
                                   class="w-24 border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500" />
                            <span class="text-sm text-gray-500">to</span>
                            <input type="number" name="lead_hours_max"
                                   value="{{ old('lead_hours_max', $videoConsultingLeadHoursBounds['max']) }}"
                                   min="0" max="720" step="1"
                                   class="w-24 border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500" />
                        </div>
                        <x-input-error :messages="$errors->get('lead_hours_min')" class="mt-1" />
                        <x-input-error :messages="$errors->get('lead_hours_max')" class="mt-1" />
                    </div>
                    <div class="mt-3">
                        <x-primary-button>Save</x-primary-button>
                    </div>
                </form>
            </div>

            @if($isAdmin)

            {{-- Video Consulting Durations --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6"
                 x-data="{
                     durations: {{ Js::from($videoConsultingDurations->map(fn($d) => [
                         'id'                => $d->id,
                         'minutes'           => $d->minutes,
                         'label'             => $d->label,
                         'base_rate'         => (string) $d->base_rate,
                         'base_rate_percent' => (string) $d->base_rate_percent,
                         'min_reader_rate'   => (string) $d->min_reader_rate,
                         'max_reader_rate'   => (string) $d->max_reader_rate,
                         'enabled'           => $d->enabled,
                     ])->values()) }},
                     addDuration() {
                         this.durations.push({ id: null, minutes: 30, label: '', base_rate: '0.00', base_rate_percent: '0.00', min_reader_rate: '0.00', max_reader_rate: '0.00', enabled: true });
                         this.$nextTick(() => {
                             const inputs = this.$el.querySelectorAll('input[name*=\'[label]\']');
                             inputs[inputs.length - 1]?.focus();
                         });
                     },
                     removeDuration(idx) {
                         if (!confirm('Remove this duration? If it already has reader rates or bookings it will be kept and disabled instead.')) return;
                         this.durations.splice(idx, 1);
                     },
                     moveDuration(idx, dir) {
                         const target = idx + dir;
                         if (target < 0 || target >= this.durations.length) return;
                         const row = this.durations[idx];
                         this.durations.splice(idx, 1);
                         this.durations.splice(target, 0, row);
                     }
                 }">
                <h3 class="text-sm font-semibold text-gray-800 mb-1">Video Consulting Durations</h3>
                <p class="text-xs text-gray-500 mb-4">
                    Call-length options offered for paid video consulting calls. Readers set their own rate on their
                    Video Consulting Rates page, bounded by the <strong>Min/Max Reader Rate</strong> set here.
                    <strong>SR's Fee ($)</strong> and <strong>SR's Fee %</strong> both apply together — SR's fee is
                    the flat dollar amount plus that percentage of the reader's own rate (rounded to the nearest
                    dollar), so SR's cut keeps scaling up on higher-dollar calls instead of staying capped at the
                    flat amount. The customer's price is SR's fee + the reader's own rate.
                    A duration with existing reader rates or bookings can't be deleted — it will be disabled instead.
                </p>

                <form method="POST" action="{{ route('settings.video-consulting-durations') }}">
                    @csrf
                    @method('PATCH')

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-xs text-gray-500 uppercase tracking-wide border-b border-gray-100">
                                    <th class="py-2 pr-2 font-medium w-10"></th>
                                    <th class="text-left py-2 px-2 font-medium">Label</th>
                                    <th class="text-center py-2 px-2 font-medium w-24">Minutes</th>
                                    <th class="text-center py-2 px-2 font-medium w-28">SR's Fee ($)</th>
                                    <th class="text-center py-2 px-2 font-medium w-24">SR's Fee %</th>
                                    <th class="text-center py-2 px-2 font-medium w-28">Min Reader Rate ($)</th>
                                    <th class="text-center py-2 px-2 font-medium w-28">Max Reader Rate ($)</th>
                                    <th class="text-center py-2 px-2 font-medium w-20">Enabled</th>
                                    <th class="w-10"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(duration, idx) in durations" :key="idx">
                                    <tr class="border-b border-gray-50">
                                        <td class="py-2 pr-2 text-gray-400">
                                            <div class="flex flex-col leading-none">
                                                <button type="button" @click="moveDuration(idx, -1)" class="hover:text-gray-700" title="Move up">&#9650;</button>
                                                <button type="button" @click="moveDuration(idx, 1)" class="hover:text-gray-700" title="Move down">&#9660;</button>
                                            </div>
                                        </td>
                                        <td class="py-2 px-2">
                                            <input type="hidden" :name="'durations[' + idx + '][id]'" :value="duration.id">
                                            <input type="text" :name="'durations[' + idx + '][label]'" x-model="duration.label"
                                                   placeholder="e.g. 30 Minutes" required
                                                   class="w-full text-sm border border-gray-300 rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-indigo-400">
                                        </td>
                                        <td class="py-2 px-2">
                                            <input type="number" :name="'durations[' + idx + '][minutes]'" x-model="duration.minutes"
                                                   min="1" max="600" step="1" required
                                                   class="w-full text-center text-sm border border-gray-300 rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-indigo-400">
                                        </td>
                                        <td class="py-2 px-2">
                                            <input type="number" :name="'durations[' + idx + '][base_rate]'" x-model="duration.base_rate"
                                                   min="0" max="9999.99" step="0.01" required
                                                   title="Minimum SR keeps, in dollars."
                                                   class="w-full text-center text-sm border border-gray-300 rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-indigo-400">
                                        </td>
                                        <td class="py-2 px-2">
                                            <input type="number" :name="'durations[' + idx + '][base_rate_percent]'" x-model="duration.base_rate_percent"
                                                   min="0" max="100" step="0.1"
                                                   title="Percentage of the reader's own rate — added on top of SR's Fee ($), not instead of it."
                                                   class="w-full text-center text-sm border border-gray-300 rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-indigo-400">
                                        </td>
                                        <td class="py-2 px-2">
                                            <input type="number" :name="'durations[' + idx + '][min_reader_rate]'" x-model="duration.min_reader_rate"
                                                   min="0" max="9999.99" step="0.01" required
                                                   class="w-full text-center text-sm border border-gray-300 rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-indigo-400">
                                        </td>
                                        <td class="py-2 px-2">
                                            <input type="number" :name="'durations[' + idx + '][max_reader_rate]'" x-model="duration.max_reader_rate"
                                                   min="0" max="9999.99" step="0.01" required
                                                   class="w-full text-center text-sm border border-gray-300 rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-indigo-400">
                                        </td>
                                        <td class="py-2 px-2 text-center">
                                            <input type="checkbox" :name="'durations[' + idx + '][enabled]'" value="1" x-model="duration.enabled"
                                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                        </td>
                                        <td class="py-2 px-2 text-center">
                                            <button type="button" @click="removeDuration(idx)"
                                                    class="text-gray-400 hover:text-red-500 transition-colors" title="Remove">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex items-center justify-between mt-4">
                        <button type="button" @click="addDuration()"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm text-indigo-600 border border-indigo-300 rounded-md hover:bg-indigo-50 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Add duration
                        </button>
                        <x-primary-button>Save Durations</x-primary-button>
                    </div>
                </form>
            </div>

            @endif
            </div>
        </div>
    </div>
</x-app-layout>
