<?php

// v1.0 — 2026-07-23 | Replaces inline abort_unless() calls in OrderLogController.
//                     Covered by tests/Feature/OrderLogControllerTest.php.

namespace App\Policies;

use App\Models\OrderRevenue;
use App\Models\User;

class OrderRevenuePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdminOrEditor();
    }

    public function download(User $user, OrderRevenue $order): bool
    {
        return $user->isAdminOrEditor();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, OrderRevenue $order): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, OrderRevenue $order): bool
    {
        return $user->isAdmin();
    }

    /** Not tied to one instance. */
    public function bulkDelete(User $user): bool
    {
        return $user->isAdmin();
    }
}
