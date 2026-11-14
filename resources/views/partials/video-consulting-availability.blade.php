{{--
    Shared weekly-availability + blackout-dates UI for video consulting. Used by both the
    reader's own Profile page (self-edit, posting to the reader-only video-consulting-*
    routes) and the admin's reader-edit page (admin override, posting to the readers.*
    equivalents) — see profile/edit.blade.php. Moved out of the old standalone "Consulting"
    page (video-consulting/edit.blade.php keeps only the Rate section now).

    Required props:
      $rules               — ReaderAvailabilityRule collection keyed by day_of_week
      $blackouts           — ReaderBlackoutDate collection for this reader
      $minLeadHours        — int
      $updateAction        — route() for the weekly-schedule form
      $blackoutStoreAction — route() for adding a blackout
    Blackout removal always posts to the same video-consulting-availability.blackouts.destroy
    route regardless of caller — ReaderBlackoutDatePolicy::delete() allows both the owning
    reader and any admin/editor.
--}}
@php
    $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    $hourOptions = [];
    for ($h = 0; $h < 24; $h++) {
        $hourOptions[$h] = \Carbon\Carbon::createFromTime($h, 0)->format('g A');
    }
    $minuteOptions = ['00' => ':00', '15' => ':15', '30' => ':30', '45' => ':45'];
@endphp

<form method="POST" action="{{ $updateAction }}" class="space-y-4">
    @csrf
    @method('PATCH')

    @if ($errors->any() && $errors->hasAny(collect(range(0, 6))->flatMap(fn ($d) => ["days.{$d}.start_time", "days.{$d}.end_time"])->all()))
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-sm text-red-700">
            <ul class="list-disc list-inside space-y-0.5">
                @foreach (range(0, 6) as $d)
                    @foreach (array_merge($errors->get("days.{$d}.start_time"), $errors->get("days.{$d}.end_time")) as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                @endforeach
            </ul>
        </div>
    @endif

    <p class="text-sm text-gray-500">Weekly recurring hours available for video consulting calls.</p>

    @foreach ($dayNames as $dayOfWeek => $dayName)
        @php
            $rule = $rules->get($dayOfWeek);

            // A day with no saved rule yet (and no old() input from a failed submit) defaults
            // to 9 AM–5 PM instead of a blank "--" placeholder, so a reader/admin doesn't have
            // to manually set the hour AND minute on both ends of every single day — just
            // check the days wanted and adjust from there. The hidden start_time/end_time
            // field is pre-filled from this same default too (not just the dropdown's
            // `selected` option), so it submits correctly even if never touched.
            $startVal = old("days.$dayOfWeek.start_time", $rule?->start_time);
            $startVal = $startVal ? substr($startVal, 0, 5) : '09:00';
            [$startHourRaw, $startMinute] = explode(':', $startVal);
            $startHour = (int) $startHourRaw;

            $endVal = old("days.$dayOfWeek.end_time", $rule?->end_time);
            $endVal = $endVal ? substr($endVal, 0, 5) : '17:00';
            [$endHourRaw, $endMinute] = explode(':', $endVal);
            $endHour = (int) $endHourRaw;
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

    <div class="pt-3 border-t border-gray-100">
        <x-input-label value="Minimum notice before a call (hours)" />
        <input type="number" name="min_lead_hours" min="0" max="336" step="1"
               value="{{ old('min_lead_hours', $minLeadHours) }}"
               class="mt-1 block w-32 border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
        <p class="mt-1 text-sm text-gray-500">
            Calls won't be offered to customers sooner than this, so there's time to read the
            script and compile notes first. 0 = no minimum beyond being in the future.
        </p>
        <x-input-error :messages="$errors->get('min_lead_hours')" class="mt-1" />
    </div>

    <div class="flex justify-end pt-2 border-t border-gray-100">
        <x-primary-button>Save Availability</x-primary-button>
    </div>
</form>

<div class="space-y-4 pt-6 mt-6 border-t border-gray-200">
    <h3 class="font-semibold text-gray-800">Blackout Dates</h3>
    <p class="text-sm text-gray-500">One-off times unavailable, even during the normal hours above.</p>

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

    <form method="POST" action="{{ $blackoutStoreAction }}" class="flex flex-wrap items-end gap-3 pt-3 border-t border-gray-100">
        @csrf
        <div>
            <x-input-label value="Starts" />
            <input type="datetime-local" name="start_at" required
                   class="mt-1 border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
        </div>
        <div>
            <x-input-label value="Ends" />
            <input type="datetime-local" name="end_at" required
                   class="mt-1 border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
        </div>
        <div class="flex-1 min-w-[10rem]">
            <x-input-label value="Reason (optional)" />
            <input type="text" name="reason" maxlength="255"
                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
        </div>
        <x-primary-button>Add</x-primary-button>
    </form>
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
