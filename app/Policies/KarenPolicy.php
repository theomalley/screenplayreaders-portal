<?php

// v1.0 — 2026-07-27 | View/create/update gated by the karens / karens.manage toggles in
//                     Tools → Permissions; delete is admin-only and not permission-gated
//                     (editors must never be able to delete a Karen entry).

namespace App\Policies;

use App\Models\Karen;
use App\Models\User;
use App\Support\Permission;

class KarenPolicy
{
    public function viewAny(User $user): bool
    {
        return Permission::check('karens', $user);
    }

    public function view(User $user, Karen $karen): bool
    {
        return Permission::check('karens', $user);
    }

    public function create(User $user): bool
    {
        return Permission::check('karens.manage', $user);
    }

    public function update(User $user, Karen $karen): bool
    {
        return Permission::check('karens.manage', $user);
    }

    public function delete(User $user, Karen $karen): bool
    {
        return $user->isAdmin();
    }
}
