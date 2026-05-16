<?php

// v1.0 — 2026-05-16 | Role-based access for assignments. All authz flows through here — no inline checks in controllers.

namespace App\Policies;

use App\Models\Assignment;
use App\Models\User;

class AssignmentPolicy
{
    /** Admin and editor can see all assignments including incoming */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'editor', 'reader']);
    }

    public function view(User $user, Assignment $assignment): bool
    {
        if ($user->canManageAssignments()) {
            return true;
        }

        if ($user->isReader()) {
            // Readers see unassigned or their own accepted assignments
            return $assignment->isAvailable()
                || $assignment->assigned_reader_id === $user->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->canManageAssignments();
    }

    public function update(User $user, Assignment $assignment): bool
    {
        return $user->canManageAssignments();
    }

    public function delete(User $user, Assignment $assignment): bool
    {
        return $user->isAdmin();
    }

    /** Reader accepting an unassigned assignment */
    public function accept(User $user, Assignment $assignment): bool
    {
        return $user->isReader() && $assignment->isAvailable();
    }

    /** Reader cancelling their own accepted assignment */
    public function cancel(User $user, Assignment $assignment): bool
    {
        return $user->isReader()
            && $assignment->status === Assignment::STATUS_ASSIGNED
            && $assignment->assigned_reader_id === $user->id;
    }

    /** Reader submitting coverage */
    public function submitCoverage(User $user, Assignment $assignment): bool
    {
        return $user->isReader()
            && $assignment->status === Assignment::STATUS_ASSIGNED
            && $assignment->assigned_reader_id === $user->id;
    }

    /** Admin/editor QC and delivery actions */
    public function manageQc(User $user, Assignment $assignment): bool
    {
        return $user->canManageAssignments()
            && in_array($assignment->status, [Assignment::STATUS_QC, Assignment::STATUS_COMPLETED], true);
    }

    public function removeTitlePage(User $user, Assignment $assignment): bool
    {
        return $user->canManageAssignments();
    }
}
