<?php

// v1.1 — 2026-07-23 | Authorization moved to the manage-payout-schedule Gate ability (AppServiceProvider)
// v1.0 — 2026-06-02 | Admin payout schedule settings — frequency, day, time, next-date override

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\PayPeriod;
use Illuminate\Http\Request;

class PayoutScheduleController extends Controller
{
    /**
     * Save payout frequency, day of week, and time.
     * When switching to biweekly, stores the current next payout date as the
     * cycle anchor so PayPeriod::start() aligns correctly.
     */
    public function update(Request $request)
    {
        $this->authorize('manage-payout-schedule');

        $data = $request->validate([
            'frequency' => ['required', 'in:weekly,biweekly'],
            'day'       => ['required', 'integer', 'min:0', 'max:6'],
            'time'      => ['required', 'regex:/^\d{2}:\d{2}$/'],
        ]);

        $previous = Setting::getPayoutSchedule();

        Setting::setValue('payout_frequency', $data['frequency']);
        Setting::setValue('payout_day',       (string) $data['day']);
        Setting::setValue('payout_time',      $data['time']);

        // When switching to biweekly (or changing the schedule while biweekly),
        // anchor the cycle to the next payout date calculated with the NEW settings.
        if ($data['frequency'] === 'biweekly') {
            // Re-read config now that new values are stored, then compute anchor
            $nextPayout = PayPeriod::nextPayoutDate();
            Setting::setValue('payout_biweekly_anchor', $nextPayout->toDateString());
        }

        return back()->with('success', 'Payout schedule saved.');
    }

    /**
     * Set or clear the next payout date override.
     * Sending an empty override_date clears any existing override.
     */
    public function setOverride(Request $request)
    {
        $this->authorize('manage-payout-schedule');

        $data = $request->validate([
            'override_date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $date = $data['override_date'] ?? null;

        if ($date) {
            Setting::setValue('payout_next_override', $date);
            return back()->with('success', 'Next payout date overridden to ' . $date . '.');
        }

        Setting::where('key', 'payout_next_override')->delete();
        return back()->with('success', 'Payout date override cleared.');
    }
}
