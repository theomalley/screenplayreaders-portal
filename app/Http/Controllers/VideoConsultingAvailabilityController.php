<?php

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

        if ($reader->readerProfile) {
            $reader->readerProfile->update([
                'video_consulting_min_lead_hours' => (int) ($data['min_lead_hours'] ?? $leadHoursBounds['min']),
            ]);
        }

        foreach (range(0, 6) as $dayOfWeek) {
            $day = $data['days'][$dayOfWeek] ?? null;
            $enabled = (bool) ($day['enabled'] ?? false);
            $start = $day['start_time'] ?? null;
            $end = $day['end_time'] ?? null;

            if (! $enabled || ! $start || ! $end) {
                ReaderAvailabilityRule::where('reader_id', $reader->id)->where('day_of_week', $dayOfWeek)->delete();
                continue;
            }

            if ($start >= $end) {
                return back()->withErrors(["days.{$dayOfWeek}.end_time" => 'End time must be after start time.'])->withInput();
            }

            ReaderAvailabilityRule::updateOrCreate(
                ['reader_id' => $reader->id, 'day_of_week' => $dayOfWeek],
                ['start_time' => $start, 'end_time' => $end, 'enabled' => true]
            );
        }

        return redirect()->route('profile.edit')->with('success', 'Availability updated.');
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
