<?php

// v1.3 — 2026-11-22 | update() now also saves a fee override (video_consulting_fee_override_
//                      enabled/_amount/_percent) when the caller is an admin — the only
//                      self-serve path to set it on their own rate; everyone else still has it
//                      set for them from the admin's reader/editor-edit page instead. See
//                      VideoConsultingDuration::effectiveBaseRate().
// v1.2 — 2026-11-14 | The "Consulting" page/nav tab is retired — Rate now lives on the
//                      reader's main Profile page (see profile/edit.blade.php). edit() and
//                      update()'s redirect both now point at profile.edit instead.
// v1.1 — 2026-11-09 | Rate + availability merged onto one "Consulting" page
//                      (VideoConsultingSettingsController) — edit() now just redirects there
//                      so any existing bookmark/link to this standalone page still lands
//                      somewhere valid instead of 404ing.
// v1.0 — 2026-11-07 | Video consulting Phase 1 — reader self-service pay rate per duration.
//                      First self-serve rate page in the app; every other reader rate
//                      (ReaderProfile::RATE_KEYS) is admin-only via ReaderProfileController.
//                      No route parameter, always operates on auth()->user() — same pattern
//                      as AvailabilityController, so there's no IDOR surface to authorize.

namespace App\Http\Controllers;

use App\Models\ReaderVideoConsultingRate;
use App\Models\VideoConsultingDuration;
use App\Support\VideoConsultingAccess;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class VideoConsultingRateController extends Controller
{
    public function edit(): RedirectResponse
    {
        return redirect()->route('profile.edit');
    }

    public function update(Request $request): RedirectResponse
    {
        $reader = auth()->user();
        abort_unless(VideoConsultingAccess::enabledFor($reader), 404);

        $durations = VideoConsultingDuration::active()->get()->keyBy('id');
        $submitted = $request->input('rates', []);

        $errors = [];
        $toSave = [];

        foreach ($submitted as $durationId => $rawRate) {
            $duration = $durations->get((int) $durationId);
            if (! $duration) {
                continue;
            }

            $rawRate = trim((string) $rawRate);
            if ($rawRate === '') {
                $toSave[$duration->id] = null; // opt out of this duration
                continue;
            }

            if (! is_numeric($rawRate)) {
                $errors["rates.{$durationId}"] = "Enter a valid number for {$duration->label}.";
                continue;
            }

            $rate = round((float) $rawRate, 2);
            if ($rate < (float) $duration->min_reader_rate || $rate > (float) $duration->max_reader_rate) {
                $errors["rates.{$durationId}"] = "{$duration->label} rate must be between \${$duration->min_reader_rate} and \${$duration->max_reader_rate}.";
                continue;
            }

            $toSave[$duration->id] = $rate;
        }

        $isAdmin = $reader->isAdmin();
        $feeOverrideEnabled = $isAdmin && $request->boolean('fee_override_enabled');
        $feeOverrideAmount = is_numeric($request->input('fee_override_amount')) ? round((float) $request->input('fee_override_amount'), 2) : null;
        $feeOverridePercent = is_numeric($request->input('fee_override_percent')) ? round((float) $request->input('fee_override_percent'), 2) : null;

        if ($feeOverrideEnabled) {
            if ($feeOverrideAmount === null || $feeOverrideAmount < 0) {
                $errors['fee_override_amount'] = 'Enter a valid SR fee amount (0 or more).';
            }
            if ($feeOverridePercent === null || $feeOverridePercent < 0 || $feeOverridePercent > 100) {
                $errors['fee_override_percent'] = 'Enter a valid SR fee percent (0-100).';
            }
        }

        if (! empty($errors)) {
            return back()->withErrors($errors)->withInput();
        }

        foreach ($toSave as $durationId => $rate) {
            if ($rate === null) {
                ReaderVideoConsultingRate::where('reader_id', $reader->id)->where('duration_id', $durationId)->delete();
            } else {
                ReaderVideoConsultingRate::updateOrCreate(
                    ['reader_id' => $reader->id, 'duration_id' => $durationId],
                    ['rate' => $rate]
                );
            }
        }

        if ($isAdmin) {
            VideoConsultingAccess::updateReaderProfileShell($reader, [
                'video_consulting_fee_override_enabled' => $feeOverrideEnabled,
                'video_consulting_fee_override_amount'  => $feeOverrideAmount,
                'video_consulting_fee_override_percent' => $feeOverridePercent,
            ]);
        }

        return redirect()->route('profile.edit')->with('success', 'Video consulting rates updated.');
    }
}
