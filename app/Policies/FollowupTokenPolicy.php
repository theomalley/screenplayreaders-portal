<?php

// v1.0 — 2026-07-23 | Replaces inline abort_unless(isAdmin()) in
//                     FollowupQuestionController::destroyToken(). Covered by
//                     tests/Feature/FollowupQuestionControllerTest.php.

namespace App\Policies;

use App\Models\FollowupToken;
use App\Models\User;

class FollowupTokenPolicy
{
    /** Admin-only: delete an entire followup round (token + all its questions). */
    public function delete(User $user, FollowupToken $token): bool
    {
        return $user->isAdmin();
    }
}
