<?php

// v1.0 — 2026-11-22 | Replaces an inline abort_unless() ownership check in
//                     ScriptHighlightController::destroy() — correct in effect, but
//                     inconsistent with this codebase's "Policies for all authorization,
//                     no inline role checks" convention.

namespace App\Policies;

use App\Models\ScriptHighlight;
use App\Models\User;

class ScriptHighlightPolicy
{
    public function delete(User $user, ScriptHighlight $highlight): bool
    {
        return $highlight->user_id === $user->id;
    }
}
