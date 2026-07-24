<?php

// v1.0 — 2026-07-23 | Replaces inline abort_unless() calls in AnnouncementController.
//                     markRead/dismiss/history are deliberately unauthorized (any
//                     authenticated user) and stay that way. Covered by
//                     tests/Feature/AnnouncementControllerTest.php.

namespace App\Policies;

use App\Models\Announcement;
use App\Models\User;

class AnnouncementPolicy
{
    public function create(User $user): bool
    {
        return $user->canManageAssignments();
    }

    /** Admin can edit any announcement; an editor only their own. */
    public function update(User $user, Announcement $announcement): bool
    {
        return $announcement->canBeEditedBy($user);
    }

    public function delete(User $user, Announcement $announcement): bool
    {
        return $user->canManageAssignments();
    }
}
