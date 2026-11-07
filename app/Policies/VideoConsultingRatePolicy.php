<?php

// v1.0 — 2026-11-07 | Video consulting Phase 1. The reader-facing self-service controller
//                      never needs this — like AvailabilityController, it takes no route
//                      parameter and always operates on auth()->user()'s own row, so there's
//                      no IDOR surface to authorize against. This exists for any future
//                      admin-side view/override of a specific reader's rate.

namespace App\Policies;

use App\Models\ReaderVideoConsultingRate;
use App\Models\User;

class VideoConsultingRatePolicy
{
    public function view(User $user, ReaderVideoConsultingRate $rate): bool
    {
        return $user->isAdmin() || $rate->reader_id === $user->id;
    }

    public function update(User $user, ReaderVideoConsultingRate $rate): bool
    {
        return $user->isReader() && $rate->reader_id === $user->id;
    }
}
