<?php

// v1.0 — 2026-07-23 | Replaces inline abort_unless() call in
//                     AssignmentNoteController::reply(). dismiss()/dismissReply() are
//                     deliberately unauthorized (any authenticated user) and stay that
//                     way. Covered by tests/Feature/AssignmentNoteControllerTest.php.

namespace App\Policies;

use App\Models\AssignmentNote;
use App\Models\User;

class AssignmentNotePolicy
{
    public function reply(User $user, AssignmentNote $note): bool
    {
        return $user->isAdminOrEditor();
    }
}
