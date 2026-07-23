<?php

// v1.0 — 2026-07-23 | Replaces inline abort_unless() calls in ScriptRegistrationController.
//                     Covered by tests/Feature/ScriptRegistrationControllerTest.php.

namespace App\Policies;

use App\Models\ScriptRegistration;
use App\Models\User;

class ScriptRegistrationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdminOrEditor();
    }

    public function view(User $user, ScriptRegistration $registration): bool
    {
        return $user->isAdminOrEditor();
    }

    public function regenerateCertificate(User $user, ScriptRegistration $registration): bool
    {
        return $user->isAdminOrEditor();
    }

    public function download(User $user, ScriptRegistration $registration): bool
    {
        return $user->isAdminOrEditor();
    }

    public function regenerateToken(User $user, ScriptRegistration $registration): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, ScriptRegistration $registration): bool
    {
        return $user->isAdmin();
    }

    /** Not tied to one instance. */
    public function bulkDelete(User $user): bool
    {
        return $user->isAdmin();
    }

    /** End-to-end pipeline test tools — not tied to one instance. */
    public function useTestTools(User $user): bool
    {
        return $user->isAdmin();
    }
}
