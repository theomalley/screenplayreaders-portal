<?php

// v1.0 — 2026-07-23 | Admin-only CRUD for dynamic reader tiers. Replaces inline
//                     abort_unless(isAdmin()) in TierController. Covered by
//                     tests/Feature/TierControllerTest.php.

namespace App\Policies;

use App\Models\Tier;
use App\Models\User;

class TierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Tier $tier): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Tier $tier): bool
    {
        return $user->isAdmin();
    }

    /** Bulk cross-tier visibility matrix — not tied to one Tier instance. */
    public function manageCrossVisibility(User $user): bool
    {
        return $user->isAdmin();
    }
}
