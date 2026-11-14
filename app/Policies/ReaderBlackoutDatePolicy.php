<?php

// v1.1 — 2026-11-14 | Admin/editor can now also delete any reader's blackout rows — the admin
//                      override of video consulting availability on the reader's edit page
//                      reuses this same destroy route/policy rather than a separate one.
// v1.0 — 2026-11-07 | Video consulting Phase 1 — a reader may only delete their own blackout rows.

namespace App\Policies;

use App\Models\ReaderBlackoutDate;
use App\Models\User;

class ReaderBlackoutDatePolicy
{
    public function delete(User $user, ReaderBlackoutDate $blackout): bool
    {
        return $blackout->reader_id === $user->id || $user->isAdminOrEditor();
    }
}
