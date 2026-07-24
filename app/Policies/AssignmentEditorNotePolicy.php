<?php

// v1.0 — 2026-07-23 | Replaces inline abort_unless() call in
//                     AssignmentEditorNoteController::destroy(). Covered by
//                     tests/Feature/AssignmentEditorNoteControllerTest.php.

namespace App\Policies;

use App\Models\AssignmentEditorNote;
use App\Models\User;

class AssignmentEditorNotePolicy
{
    public function delete(User $user, AssignmentEditorNote $note): bool
    {
        return $user->canManageAssignments();
    }
}
