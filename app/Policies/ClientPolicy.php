<?php

// v1.0 — 2026-07-23 | Replaces inline abort_unless() calls in ClientController.
//                     Covered by tests/Feature/ClientControllerTest.php.

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdminOrEditor();
    }

    public function view(User $user, Client $client): bool
    {
        return $user->isAdminOrEditor();
    }

    public function create(User $user): bool
    {
        return $user->isAdminOrEditor();
    }

    public function update(User $user, Client $client): bool
    {
        return $user->isAdminOrEditor();
    }

    public function delete(User $user, Client $client): bool
    {
        return $user->isAdmin();
    }
}
