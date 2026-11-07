<?php

// v1.0 — 2026-11-07 | Video consulting Phase 1 — public-facing durations/rates/availability/
//                      hold endpoints. PORTAL INTEGRATION: called by the theme's (Phase 5)
//                      booking widget, server-side from the theme's own PHP — the theme
//                      computes the final Woo price from base_rate + reader_rate itself,
//                      never trusting client input, same as every other add-to-cart flow.

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReaderVideoConsultingRate;
use App\Models\User;
use App\Models\VideoConsultingDuration;
use App\Models\VideoConsultingHold;
use App\Services\VideoConsultingAvailabilityService;
use App\Support\VideoConsultingAccess;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VideoConsultingBookingController extends Controller
{
    private const HOLD_TTL_MINUTES = 20;

    public function durations(): JsonResponse
    {
        return response()->json(
            VideoConsultingDuration::active()->get(['id', 'minutes', 'label', 'base_rate'])
        );
    }

    public function readerRates(User $reader): JsonResponse
    {
        abort_unless($reader->isReader(), 404);

        if (! VideoConsultingAccess::enabledFor($reader)) {
            return response()->json([]);
        }

        $rates = ReaderVideoConsultingRate::where('reader_id', $reader->id)
            ->with('duration')
            ->get()
            ->filter(fn (ReaderVideoConsultingRate $rate) => $rate->duration && $rate->duration->enabled)
            ->map(fn (ReaderVideoConsultingRate $rate) => [
                'duration_id' => $rate->duration_id,
                'minutes'     => $rate->duration->minutes,
                'label'       => $rate->duration->label,
                'base_rate'   => (float) $rate->duration->base_rate,
                'reader_rate' => (float) $rate->rate,
                'price'       => (float) $rate->duration->base_rate + (float) $rate->rate,
            ])
            ->values();

        return response()->json($rates);
    }

    public function availableSlots(Request $request, VideoConsultingAvailabilityService $availability): JsonResponse
    {
        $data = $request->validate([
            'reader_id'   => ['required', 'integer', 'exists:users,id'],
            'duration_id' => ['required', 'integer', 'exists:video_consulting_durations,id'],
            'from'        => ['required', 'date'],
            'to'          => ['required', 'date', 'after:from'],
        ]);

        $reader = User::find($data['reader_id']);
        if (! VideoConsultingAccess::enabledFor($reader)) {
            return response()->json([]);
        }

        $slots = $availability->generateSlots(
            (int) $data['reader_id'],
            (int) $data['duration_id'],
            Carbon::parse($data['from']),
            Carbon::parse($data['to'])
        );

        return response()->json(array_map(
            fn (array $slot) => ['start' => $slot['start']->toIso8601String(), 'end' => $slot['end']->toIso8601String()],
            $slots
        ));
    }

    public function createHold(Request $request, VideoConsultingAvailabilityService $availability): JsonResponse
    {
        $data = $request->validate([
            'reader_id'   => ['required', 'integer', 'exists:users,id'],
            'duration_id' => ['required', 'integer', 'exists:video_consulting_durations,id'],
            'slot_start'  => ['required', 'date'],
        ]);

        $duration = VideoConsultingDuration::active()->find($data['duration_id']);
        if (! $duration) {
            return response()->json(['message' => 'Duration is not available.'], 422);
        }

        if (! VideoConsultingAccess::enabledFor(User::find($data['reader_id']))) {
            return response()->json(['message' => 'This reader is not currently accepting video consulting bookings.'], 422);
        }

        $hasRate = ReaderVideoConsultingRate::where('reader_id', $data['reader_id'])
            ->where('duration_id', $data['duration_id'])
            ->exists();
        if (! $hasRate) {
            return response()->json(['message' => 'This reader does not offer that duration.'], 422);
        }

        $slotStart = Carbon::parse($data['slot_start']);

        // Never trust that the client called availableSlots() first — re-validate here.
        if (! $availability->isSlotAvailable((int) $data['reader_id'], (int) $data['duration_id'], $slotStart)) {
            return response()->json(['message' => 'That slot is no longer available.'], 409);
        }

        try {
            $hold = VideoConsultingHold::create([
                'reader_id'   => $data['reader_id'],
                'duration_id' => $data['duration_id'],
                'slot_start'  => $slotStart,
                'slot_end'    => $slotStart->copy()->addMinutes($duration->minutes),
                'hold_token'  => Str::random(64),
                'status'      => VideoConsultingHold::STATUS_HELD,
                'expires_at'  => now()->addMinutes(self::HOLD_TTL_MINUTES),
            ]);
        } catch (QueryException $e) {
            // Race: a concurrent request already holds this reader/slot_start — the unique
            // index (migration 2026_11_07_000005) is what actually caught it.
            if ((int) ($e->errorInfo[1] ?? 0) === 1062 || str_contains($e->getMessage(), 'Unique')) {
                return response()->json(['message' => 'That slot is no longer available.'], 409);
            }

            throw $e;
        }

        return response()->json([
            'hold_token' => $hold->hold_token,
            'expires_at' => $hold->expires_at->toIso8601String(),
        ], 201);
    }

    public function releaseHold(string $holdToken): JsonResponse
    {
        VideoConsultingHold::where('hold_token', $holdToken)
            ->where('status', VideoConsultingHold::STATUS_HELD)
            ->delete();

        return response()->json(['status' => 'released']);
    }
}
