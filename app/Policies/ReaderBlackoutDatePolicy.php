<?php

// v1.0 — 2026-11-07 | Video consulting Phase 1 — a reader may only delete their own blackout rows.

namespace App\Policies;

use App\Models\ReaderBlackoutDate;
use App\Models\User;

class ReaderBlackoutDatePolicy
{
    public function delete(User $user, ReaderBlackoutDate $blackout): bool
    {
        return $blackout->reader_id === $user->id;
    }
}
