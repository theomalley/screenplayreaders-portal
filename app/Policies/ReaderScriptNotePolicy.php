<?php

// v1.0 — 2026-11-22 | Replaces an inline abort_unless() ownership check in
//                     ReaderScriptNoteController::destroy() — correct in effect, but
//                     inconsistent with this codebase's "Policies for all authorization,
//                     no inline role checks" convention.

namespace App\Policies;

use App\Models\ReaderScriptNote;
use App\Models\User;

class ReaderScriptNotePolicy
{
    public function delete(User $user, ReaderScriptNote $note): bool
    {
        return $note->user_id === $user->id;
    }
}
