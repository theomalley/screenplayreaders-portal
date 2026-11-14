<?php

// v1.3 — 2026-11-14 | The "Consulting" page/nav tab is retired entirely — Rate and
//                      Availability both now live on the reader's main Profile page (see
//                      profile/edit.blade.php). edit() is kept only as a redirect so any old
//                      bookmark/link to /video-consulting still lands somewhere valid.
// v1.2 — 2026-11-14 | Availability (weekly schedule + blackout dates) moved off this page onto
//                      the reader's main Profile instead — this now only aggregates data for
//                      the Rate section. See profile/edit.blade.php + ReaderProfileController.
// v1.1 — 2026-11-09 | Pass minLeadHours (ReaderProfile.video_consulting_min_lead_hours) to the
//                      view — the minimum notice a reader requires before a call, saved via
//                      VideoConsultingAvailabilityController::update() alongside the weekly hours.
// v1.0 — 2026-11-09 | Reader self-service rate + availability merged onto one "Consulting"
//                      page instead of two separate nav items. Each section's form still posts
//                      to its own existing route (VideoConsultingRateController::update() /
//                      VideoConsultingAvailabilityController::update()/storeBlackout()/
//                      destroyBlackout()) — none of that logic moved, this controller only
//                      aggregates both sections' data for the combined view.

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class VideoConsultingSettingsController extends Controller
{
    public function edit(): RedirectResponse
    {
        return redirect()->route('profile.edit');
    }
}
