<?php

// v1.0 — 2026-07-23 | Replaces inline abort_unless() calls in ReadCreditController.
//                     Covered by tests/Feature/ReadCreditControllerTest.php.

namespace App\Policies;

use App\Models\ReadCreditPackage;
use App\Models\User;

class ReadCreditPackagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdminOrEditor();
    }

    public function create(User $user): bool
    {
        return $user->isAdminOrEditor();
    }

    public function update(User $user, ReadCreditPackage $package): bool
    {
        return $user->isAdminOrEditor();
    }
}
