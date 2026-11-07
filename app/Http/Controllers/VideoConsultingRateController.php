<?php

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
use Illuminate\View\View;

class VideoConsultingRateController extends Controller
{
    public function edit(): View
    {
        $reader = auth()->user();
        abort_unless(VideoConsultingAccess::enabledFor($reader), 404);

        $durations = VideoConsultingDuration::active()->get();
        $myRates = ReaderVideoConsultingRate::where('reader_id', $reader->id)->pluck('rate', 'duration_id');

        return view('video-consulting-rate.edit', compact('durations', 'myRates'));
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

        return redirect()->route('video-consulting-rate.edit')->with('success', 'Video consulting rates updated.');
    }
}
