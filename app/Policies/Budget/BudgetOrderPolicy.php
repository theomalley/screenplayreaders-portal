<?php

// v1.0 — 2026-07-23 | Replaces inline abort_unless() calls in BudgetOrderController.
//                     Covered by tests/Feature/BudgetOrderControllerTest.php.

namespace App\Policies\Budget;

use App\Models\Budget\BudgetOrder;
use App\Models\User;

class BudgetOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdminOrEditor();
    }

    public function view(User $user, BudgetOrder $budgetOrder): bool
    {
        return $user->isAdminOrEditor();
    }

    public function download(User $user, BudgetOrder $budgetOrder): bool
    {
        return $user->isAdminOrEditor();
    }

    public function regenerate(User $user, BudgetOrder $budgetOrder): bool
    {
        return $user->isAdminOrEditor();
    }

    /** Bulk delete — not tied to one instance. */
    public function bulkDelete(User $user): bool
    {
        return $user->isAdmin();
    }
}
