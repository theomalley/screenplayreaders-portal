<?php

// v1.7 — 2026-11-23 | update() now validates every submitted day BEFORE writing any
//                      ReaderAvailabilityRule — a start>=end failure on (say) day 4 used to
//                      still leave days 0-3 permanently persisted since the old code validated
//                      and wrote inside the same loop. Mirrors VideoConsultingRateController::
//                      update()'s collect-errors-then-write-all pattern.
// v1.6 — 2026-11-22 | Add updateHolidayBlackouts() — the reader's own US federal holiday
//                      blackout accordion (ReaderProfile.video_consulting_holiday_blackouts,
//                      App\Support\FederalHolidays). Separate action/form from update() since
//                      it lives visually in the Blackout Dates card, not the weekly-schedule one.
// v1.5 — 2026-11-22 | update() now also saves video_consulting_available — the reader's own
//                      "Available for video calls" pause toggle (ReaderProfile.
//                      video_consulting_available). Doesn't touch the day loop below at all,
//                      so pausing/unpausing never affects the saved weekly schedule.
// v1.4 — 2026-11-15 | min_lead_hours is validated against admin-configurable bounds
//                      (Setting::getVideoConsultingLeadHoursBounds(), Tools > Settings >
//                      Assignments) instead of a hardcoded 0-336.
// v1.3 — 2026-11-14 | The "Consulting" page/nav tab is retired — Availability now lives on the
//                      reader's main Profile page (see profile/edit.blade.php). Every redirect
//                      here now points at profile.edit instead of the retired video-consulting.edit.
// v1.2 — 2026-11-09 | update() now also saves min_lead_hours (ReaderProfile.
//                      video_consulting_min_lead_hours) — the minimum notice a reader requires
//                      before a call, enforced in VideoConsultingAvailabilityService::
//                      generateSlots() so customers are never offered a slot sooner than this.
// v1.1 — 2026-11-09 | Rate + availability merged onto one "Consulting" page
//                      (VideoConsultingSettingsController) — edit() now just redirects there
//                      so any existing bookmark/link to this standalone page still lands
//                      somewhere valid instead of 404ing.
// v1.0 — 2026-11-07 | Video consulting Phase 1 — reader self-service weekly availability +
//                      blackout dates. v1 UI supports exactly one window per day-of-week; the
//                      underlying ReaderAvailabilityRule table isn't limited to that, so a
//                      richer multi-window-per-day UI can be added later without a migration.

namespace App\Http\Controllers;

use App\Models\ReaderAvailabilityRule;
use App\Models\ReaderBlackoutDate;
use App\Support\VideoConsultingAccess;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class VideoConsultingAvailabilityController extends Controller
{
    public function edit(): RedirectResponse
    {
        return redirect()->route('profile.edit');
    }

    public function update(Request $request): RedirectResponse
    {
        $reader = auth()->user();
        abort_unless(VideoConsultingAccess::enabledFor($reader), 404);

        $leadHoursBounds = \App\Models\Setting::getVideoConsultingLeadHoursBounds();

        $data = $request->validate([
            'days'              => ['array'],
            'days.*.enabled'    => ['nullable', 'boolean'],
            'days.*.start_time' => ['nullable', 'date_format:H:i'],
            'days.*.end_time'   => ['nullable', 'date_format:H:i'],
            'min_lead_hours'    => ['nullable', 'integer', "min:{$leadHoursBounds['min']}", "max:{$leadHoursBounds['max']}"],
        ]);

        // Pass 1: validate every day first — decide what each day's end state should be
        // without writing anything yet, so a bad day later in the loop can't leave earlier
        // days' writes permanently committed.
        $errors = [];
        $dayActions = [];

        foreach (range(0, 6) as $dayOfWeek) {
            $day = $data['days'][$dayOfWeek] ?? null;
            $enabled = (bool) ($day['enabled'] ?? false);
            $start = $day['start_time'] ?? null;
            $end = $day['end_time'] ?? null;

            if (! $enabled || ! $start || ! $end) {
                $dayActions[$dayOfWeek] = null; // delete
                continue;
            }

            if ($start >= $end) {
                $errors["days.{$dayOfWeek}.end_time"] = 'End time must be after start time.';
                continue;
            }

            $dayActions[$dayOfWeek] = ['start_time' => $start, 'end_time' => $end, 'enabled' => true];
        }

        if (! empty($errors)) {
            return back()->withErrors($errors)->withInput();
        }

        // Pass 2: everything validated — now safe to write.
        if ($reader->readerProfile) {
            $reader->readerProfile->update([
                'video_consulting_min_lead_hours' => (int) ($data['min_lead_hours'] ?? $leadHoursBounds['min']),
                'video_consulting_available'      => $request->boolean('video_consulting_available'),
            ]);
        }

        foreach ($dayActions as $dayOfWeek => $action) {
            if ($action === null) {
                ReaderAvailabilityRule::where('reader_id', $reader->id)->where('day_of_week', $dayOfWeek)->delete();
                continue;
            }

            ReaderAvailabilityRule::updateOrCreate(
                ['reader_id' => $reader->id, 'day_of_week' => $dayOfWeek],
                $action
            );
        }

        return redirect()->route('profile.edit')->with('success', 'Availability updated.');
    }

    public function updateHolidayBlackouts(Request $request): RedirectResponse
    {
        $reader = auth()->user();
        abort_unless(VideoConsultingAccess::enabledFor($reader), 404);

        $validKeys = array_keys(\App\Support\FederalHolidays::HOLIDAYS);
        $submitted = $request->input('holidays', []);
        $blackedOut = array_values(array_intersect($validKeys, is_array($submitted) ? $submitted : []));

        if ($reader->readerProfile) {
            $reader->readerProfile->update([
                'video_consulting_holiday_blackouts' => $blackedOut,
            ]);
        }

        return redirect()->route('profile.edit')->with('success', 'Holiday blackouts updated.');
    }

    public function storeBlackout(Request $request): RedirectResponse
    {
        $reader = auth()->user();
        abort_unless(VideoConsultingAccess::enabledFor($reader), 404);

        $data = $request->validate([
            'start_at' => ['required', 'date'],
            'end_at'   => ['required', 'date', 'after:start_at'],
            'reason'   => ['nullable', 'string', 'max:255'],
        ]);

        ReaderBlackoutDate::create([
            'reader_id' => $reader->id,
            'start_at'  => $data['start_at'],
            'end_at'    => $data['end_at'],
            'reason'    => $data['reason'] ?? null,
        ]);

        return redirect()->route('profile.edit')->with('success', 'Blackout added.');
    }

    public function destroyBlackout(ReaderBlackoutDate $blackout): RedirectResponse
    {
        $this->authorize('delete', $blackout);

        $blackout->delete();

        return redirect()->route('profile.edit')->with('success', 'Blackout removed.');
    }
}
