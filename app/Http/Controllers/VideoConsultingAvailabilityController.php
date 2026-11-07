<?php

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
use Illuminate\View\View;

class VideoConsultingAvailabilityController extends Controller
{
    public function edit(): View
    {
        $reader = auth()->user();
        abort_unless(VideoConsultingAccess::enabledFor($reader), 404);

        $rules = ReaderAvailabilityRule::where('reader_id', $reader->id)->get()->keyBy('day_of_week');
        $blackouts = ReaderBlackoutDate::where('reader_id', $reader->id)->orderBy('start_at')->get();

        return view('video-consulting-availability.edit', compact('rules', 'blackouts'));
    }

    public function update(Request $request): RedirectResponse
    {
        $reader = auth()->user();
        abort_unless(VideoConsultingAccess::enabledFor($reader), 404);

        $data = $request->validate([
            'days'              => ['array'],
            'days.*.enabled'    => ['nullable', 'boolean'],
            'days.*.start_time' => ['nullable', 'date_format:H:i'],
            'days.*.end_time'   => ['nullable', 'date_format:H:i'],
        ]);

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

        return redirect()->route('video-consulting-availability.edit')->with('success', 'Availability updated.');
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

        return redirect()->route('video-consulting-availability.edit')->with('success', 'Blackout added.');
    }

    public function destroyBlackout(ReaderBlackoutDate $blackout): RedirectResponse
    {
        $this->authorize('delete', $blackout);

        $blackout->delete();

        return redirect()->route('video-consulting-availability.edit')->with('success', 'Blackout removed.');
    }
}
