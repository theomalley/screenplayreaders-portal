<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">My Video Consulting Availability</h2>
    </x-slot>

    @php
        $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        $hourOptions = [];
        for ($h = 0; $h < 24; $h++) {
            $hourOptions[$h] = \Carbon\Carbon::createFromTime($h, 0)->format('g A');
        }
        $minuteOptions = ['00' => ':00', '15' => ':15', '30' => ':30', '45' => ':45'];
    @endphp

    <div class="py-6">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 text-sm text-green-700">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-sm text-red-700">
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <form method="POST" action="{{ route('video-consulting-availability.update') }}" class="p-6 space-y-4">
                    @csrf
                    @method('PATCH')

                    <p class="text-sm text-gray-500">Weekly recurring hours you're available for video consulting calls.</p>

                    @foreach ($dayNames as $dayOfWeek => $dayName)
                        @php
                            $rule = $rules->get($dayOfWeek);

                            $startVal = old("days.$dayOfWeek.start_time", $rule?->start_time);
                            $startVal = $startVal ? substr($startVal, 0, 5) : '';
                            [$startHourRaw, $startMinute] = $startVal ? explode(':', $startVal) : ['', ''];
                            $startHour = $startHourRaw !== '' ? (int) $startHourRaw : '';
                            // Default the minute dropdown to :00 rather than the "--" placeholder —
                            // otherwise picking only an hour and leaving minute untouched left the
                            // hidden start_time/end_time field blank and the whole row silently
                            // failed to save (syncField() below requires both hour AND minute set).
                            if ($startMinute === '') $startMinute = '00';

                            $endVal = old("days.$dayOfWeek.end_time", $rule?->end_time);
                            $endVal = $endVal ? substr($endVal, 0, 5) : '';
                            [$endHourRaw, $endMinute] = $endVal ? explode(':', $endVal) : ['', ''];
                            $endHour = $endHourRaw !== '' ? (int) $endHourRaw : '';
                            if ($endMinute === '') $endMinute = '00';
                        @endphp
                        <div class="flex items-center gap-3 flex-wrap">
                            <label class="flex items-center gap-2 w-32 cursor-pointer">
                                <input type="checkbox" name="days[{{ $dayOfWeek }}][enabled]" value="1"
                                       {{ old("days.$dayOfWeek.enabled", $rule?->enabled ? '1' : '') ? 'checked' : '' }}
                                       class="rounded text-indigo-600 focus:ring-indigo-500" />
                                <span class="text-sm font-medium text-gray-700">{{ $dayName }}</span>
                            </label>

                            <input type="hidden" name="days[{{ $dayOfWeek }}][start_time]" value="{{ $startVal }}"
                                   class="svc-avail-hidden" data-day="{{ $dayOfWeek }}" data-field="start" />
                            <div class="flex items-center gap-1">
                                <select class="svc-avail-hour border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        data-day="{{ $dayOfWeek }}" data-field="start">
                                    <option value="">--</option>
                                    @foreach ($hourOptions as $val => $label)
                                        <option value="{{ $val }}" {{ $startHour === $val ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <select class="svc-avail-minute border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        data-day="{{ $dayOfWeek }}" data-field="start">
                                    <option value="">--</option>
                                    @foreach ($minuteOptions as $val => $label)
                                        <option value="{{ $val }}" {{ $startMinute === $val ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <span class="text-gray-400 text-sm">to</span>

                            <input type="hidden" name="days[{{ $dayOfWeek }}][end_time]" value="{{ $endVal }}"
                                   class="svc-avail-hidden" data-day="{{ $dayOfWeek }}" data-field="end" />
                            <div class="flex items-center gap-1">
                                <select class="svc-avail-hour border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        data-day="{{ $dayOfWeek }}" data-field="end">
                                    <option value="">--</option>
                                    @foreach ($hourOptions as $val => $label)
                                        <option value="{{ $val }}" {{ $endHour === $val ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <select class="svc-avail-minute border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        data-day="{{ $dayOfWeek }}" data-field="end">
                                    <option value="">--</option>
                                    @foreach ($minuteOptions as $val => $label)
                                        <option value="{{ $val }}" {{ $endMinute === $val ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    @endforeach

                    <div class="flex justify-end pt-2 border-t border-gray-100">
                        <x-primary-button>Save</x-primary-button>
                    </div>
                </form>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="p-6 space-y-4">
                    <p class="text-sm text-gray-500">Blackout dates — one-off times you're unavailable, even during your normal hours above.</p>

                    @forelse ($blackouts as $blackout)
                        <div class="flex items-center justify-between border border-gray-100 rounded-md px-3 py-2 text-sm">
                            <div>
                                <span class="font-medium text-gray-700">{{ $blackout->start_at->format('M j, Y g:ia') }}</span>
                                <span class="text-gray-400">&rarr;</span>
                                <span class="font-medium text-gray-700">{{ $blackout->end_at->format('M j, Y g:ia') }}</span>
                                @if ($blackout->reason)
                                    <span class="text-gray-400">— {{ $blackout->reason }}</span>
                                @endif
                            </div>
                            <form method="POST" action="{{ route('video-consulting-availability.blackouts.destroy', $blackout) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-800 text-xs font-medium">Remove</button>
                            </form>
                        </div>
                    @empty
                        <p class="text-sm text-gray-400">No blackout dates set.</p>
                    @endforelse

                    <form method="POST" action="{{ route('video-consulting-availability.blackouts.store') }}" class="flex flex-wrap items-end gap-3 pt-3 border-t border-gray-100">
                        @csrf
                        <div>
                            <x-input-label for="start_at" value="Starts" />
                            <input type="datetime-local" id="start_at" name="start_at" required
                                   class="mt-1 border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <x-input-label for="end_at" value="Ends" />
                            <input type="datetime-local" id="end_at" name="end_at" required
                                   class="mt-1 border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                        <div class="flex-1 min-w-[10rem]">
                            <x-input-label for="reason" value="Reason (optional)" />
                            <input type="text" id="reason" name="reason" maxlength="255"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                        <x-primary-button>Add</x-primary-button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function(){
        function syncField(day, field) {
            var hourSel = document.querySelector('.svc-avail-hour[data-day="' + day + '"][data-field="' + field + '"]');
            var minSel  = document.querySelector('.svc-avail-minute[data-day="' + day + '"][data-field="' + field + '"]');
            var hidden  = document.querySelector('.svc-avail-hidden[data-day="' + day + '"][data-field="' + field + '"]');
            if (!hourSel || !minSel || !hidden) return;

            if (hourSel.value === '' || minSel.value === '') {
                hidden.value = '';
                return;
            }
            hidden.value = ('0' + hourSel.value).slice(-2) + ':' + minSel.value;
        }

        document.querySelectorAll('.svc-avail-hour, .svc-avail-minute').forEach(function(sel){
            sel.addEventListener('change', function(){
                syncField(sel.dataset.day, sel.dataset.field);
            });
        });
    })();
    </script>
</x-app-layout>
