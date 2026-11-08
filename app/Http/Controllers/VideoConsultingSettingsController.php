<?php

// v1.0 — 2026-11-09 | Reader self-service rate + availability merged onto one "Consulting"
//                      page instead of two separate nav items. Each section's form still posts
//                      to its own existing route (VideoConsultingRateController::update() /
//                      VideoConsultingAvailabilityController::update()/storeBlackout()/
//                      destroyBlackout()) — none of that logic moved, this controller only
//                      aggregates both sections' data for the combined view.

namespace App\Http\Controllers;

use App\Models\ReaderAvailabilityRule;
use App\Models\ReaderBlackoutDate;
use App\Models\ReaderVideoConsultingRate;
use App\Models\VideoConsultingDuration;
use App\Support\VideoConsultingAccess;
use Illuminate\View\View;

class VideoConsultingSettingsController extends Controller
{
    public function edit(): View
    {
        $reader = auth()->user();
        abort_unless(VideoConsultingAccess::enabledFor($reader), 404);

        $durations = VideoConsultingDuration::active()->get();
        $myRates = ReaderVideoConsultingRate::where('reader_id', $reader->id)->pluck('rate', 'duration_id');

        $rules = ReaderAvailabilityRule::where('reader_id', $reader->id)->get()->keyBy('day_of_week');
        $blackouts = ReaderBlackoutDate::where('reader_id', $reader->id)->orderBy('start_at')->get();

        return view('video-consulting.edit', compact('durations', 'myRates', 'rules', 'blackouts'));
    }
}
