<?php

// v1.0 — 2026-07-23 | Replaces inline abort_unless() calls in FollowupQuestionController.
//                     Covered by tests/Feature/FollowupQuestionControllerTest.php.

namespace App\Policies;

use App\Models\FollowupQuestion;
use App\Models\User;

class FollowupQuestionPolicy
{
    public function update(User $user, FollowupQuestion $followup): bool
    {
        return $user->isAdminOrEditor();
    }

    public function complete(User $user, FollowupQuestion $followup): bool
    {
        return $user->isAdminOrEditor();
    }

    public function regenerateDraft(User $user, FollowupQuestion $followup): bool
    {
        return $user->isAdminOrEditor();
    }

    public function delete(User $user, FollowupQuestion $followup): bool
    {
        return $user->isAdminOrEditor();
    }

    /** Not tied to one instance — checked against an order number string. */
    public function viewHistory(User $user): bool
    {
        return $user->isAdminOrEditor();
    }

    /** The assigned reader responding to their own still-unanswered followup. */
    public function respond(User $user, FollowupQuestion $followup): bool
    {
        return $user->isReader() && $followup->assignment->assigned_reader_id === $user->id;
    }
}
