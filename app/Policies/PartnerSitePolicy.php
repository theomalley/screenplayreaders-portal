<?php

// v1.0 — 2026-07-23 | Replaces inline abort_unless() calls in
//                     Marketing\PartnerSiteController. Covered by
//                     tests/Feature/PartnerSiteControllerTest.php.

namespace App\Policies;

use App\Models\PartnerSite;
use App\Models\User;

class PartnerSitePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, PartnerSite $partnerSite): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, PartnerSite $partnerSite): bool
    {
        return $user->isAdmin();
    }

    public function checkNow(User $user, PartnerSite $partnerSite): bool
    {
        return $user->isAdmin();
    }

    public function toggleActive(User $user, PartnerSite $partnerSite): bool
    {
        return $user->isAdmin();
    }

    public function history(User $user, PartnerSite $partnerSite): bool
    {
        return $user->isAdmin();
    }
}
