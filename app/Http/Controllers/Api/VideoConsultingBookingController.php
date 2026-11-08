<?php

// v1.1 — 2026-11-09 | Video consulting Phase 6 — add readers() (the checkout upgrade's reader
//                      picker: who offers video consulting + their weekly availability pattern)
//                      and createBooking() (converts a paid-for hold into the real Assignment,
//                      called from the theme's /upload/ slot picker once the customer confirms
//                      a time — see routes/api.php).
// v1.0 — 2026-11-07 | Video consulting Phase 1 — public-facing durations/rates/availability/
//                      hold endpoints. PORTAL INTEGRATION: called by the theme's (Phase 5)
//                      booking widget, server-side from the theme's own PHP — the theme
//                      computes the final Woo price from base_rate + reader_rate itself,
//                      never trusting client input, same as every other add-to-cart flow.

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\ReaderVideoConsultingRate;
use App\Models\Tier;
use App\Models\User;
use App\Models\VideoConsultingDuration;
use App\Models\VideoConsultingHold;
use App\Services\ReaderNotificationService;
use App\Services\VideoConsultingAvailabilityService;
use App\Services\VideoConsultingNotificationService;
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

    /**
     * Readers who actually offer video consulting today (access enabled + at least one
     * configured rate), with their weekly availability pattern — the checkout upgrade's
     * reader picker shows this, not a list of real bookable slots (see available-slots).
     */
    public function readers(VideoConsultingAvailabilityService $availability): JsonResponse
    {
        $readers = User::where('role', 'reader')
            ->whereHas('videoConsultingRates')
            ->with(['videoConsultingRates.duration'])
            ->get()
            ->filter(fn (User $reader) => VideoConsultingAccess::enabledFor($reader))
            ->map(function (User $reader) use ($availability) {
                $durations = $reader->videoConsultingRates
                    ->filter(fn (ReaderVideoConsultingRate $rate) => $rate->duration && $rate->duration->enabled)
                    ->map(fn (ReaderVideoConsultingRate $rate) => [
                        'duration_id' => $rate->duration_id,
                        'minutes'     => $rate->duration->minutes,
                        'label'       => $rate->duration->label,
                        'price'       => (float) $rate->duration->base_rate + (float) $rate->rate,
                    ])
                    ->values();

                if ($durations->isEmpty()) {
                    return null;
                }

                return [
                    'id'                   => $reader->id,
                    'name'                 => $reader->name,
                    'availability_summary' => $availability->availabilitySummaryFor($reader->id),
                    'durations'            => $durations,
                ];
            })
            ->filter()
            ->values();

        return response()->json($readers);
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

    /**
     * Converts a held slot into the real Assignment, once the theme's /upload/ slot picker
     * has already placed the hold (createHold() above) and the customer confirmed it.
     * Payment already happened before this call — this endpoint only turns a hold the
     * customer paid for into a bookable Assignment, mirroring BudgetWebhookController's
     * exists()-then-create() idempotency shape (see migration
     * 2026_11_09_000003_add_unique_index_to_assignments_video_consulting_hold_id).
     */
    public function createBooking(Request $request): JsonResponse
    {
        $data = $request->validate([
            'hold_token'     => ['required', 'string'],
            'order_number'   => ['required', 'string', 'max:64'],
            'customer_name'  => ['nullable', 'string', 'max:255'],
            'customer_email' => ['required', 'email', 'max:255'],
        ]);

        $hold = VideoConsultingHold::with('duration')->where('hold_token', $data['hold_token'])->first();
        if (! $hold) {
            return response()->json(['message' => 'Hold not found.'], 404);
        }

        // Idempotency fast path — the real guarantee is the unique index on
        // assignments.video_consulting_hold_id, caught below; this is just the fast path
        // for an obvious repeat (a retried request, a double-click).
        if ($hold->status === VideoConsultingHold::STATUS_CONVERTED) {
            return response()->json(['status' => 'already_exists', 'assignment_id' => $hold->converted_assignment_id], 200);
        }

        if (! $hold->isActive()) {
            return response()->json(['message' => 'This hold has expired. Please pick another time.'], 409);
        }

        $rate = ReaderVideoConsultingRate::where('reader_id', $hold->reader_id)
            ->where('duration_id', $hold->duration_id)
            ->first();
        if (! $rate) {
            return response()->json(['message' => 'This reader no longer offers that duration.'], 422);
        }

        try {
            $assignment = Assignment::create([
                'order_number'                    => $data['order_number'],
                'vendor'                          => 'sr',
                'assignment_type'                 => Assignment::TYPE_VIDEO_CONSULTING,
                'script_title'                     => "{$hold->duration->label} Video Consulting Call",
                'writer_name'                     => $data['customer_name'] ?? 'N/A (video consulting)',
                'pay_rate'                        => $rate->rate,
                'status'                          => Assignment::STATUS_UNASSIGNED,
                'requested_reader_id'             => $hold->reader_id,
                'video_consulting_hold_id'        => $hold->id,
                'video_consulting_customer_email' => $data['customer_email'],
                'unassigned_at'                   => now(),
            ]);
        } catch (QueryException $e) {
            // Race: a concurrent request for this same hold already converted it between
            // our status check above and this create() — the unique index is what actually
            // caught it.
            if ((int) ($e->errorInfo[1] ?? 0) === 1062 || str_contains($e->getMessage(), 'Unique')) {
                $hold->refresh();

                return response()->json(['status' => 'already_exists', 'assignment_id' => $hold->converted_assignment_id], 200);
            }

            throw $e;
        }

        $hold->update(['status' => VideoConsultingHold::STATUS_CONVERTED, 'converted_assignment_id' => $assignment->id]);

        // Every acceptable assignment needs at least one Tier attached (AssignmentPolicy::
        // accept()'s tierMatch() requires it) — mirrors TestDataController's/
        // IncomingAssignmentController's default-tier convention.
        $tierOne = Tier::where('is_onboarding', false)->orderBy('position')->first();
        if ($tierOne) {
            $assignment->tiers()->sync([$tierOne->id]);
        }

        app(ReaderNotificationService::class)->notifyNewAssignment($assignment->fresh());
        app(VideoConsultingNotificationService::class)->notifyCustomerBookingConfirmed($assignment->fresh());

        return response()->json(['status' => 'accepted', 'assignment_id' => $assignment->id], 201);
    }
}
