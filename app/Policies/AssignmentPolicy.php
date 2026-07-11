<?php

// v1.5 — 2026-07-11 | accept: enforce reader tier match (assignment.tier must be in the
//                     reader's ReaderProfile::tiers()) — previously tier was only enforced
//                     by which assignments were queried into view, not at authorization time.
//                     Admin/editor keep unrestricted accept.
// v1.4 — 2026-06-15 | Add duplicate() — admin/editor can clone an assignment as a new draft.
// v1.3 — 2026-06-13 | accept: deny readers blocked from the assignment's order
//                     (isReaderBlocked) even though it now shows in their Available pool.
// v1.2 — 2026-05-28 | delete: allow editors (canManageAssignments) not just admins.
// v1.1 — 2026-05-24 | submitCoverage allows admin/editor when they are the assigned user.
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
            $openToMe = $assignment->isAvailable()
                && (is_null($assignment->requested_reader_id) || $assignment->requested_reader_id === $user->id);

            return $openToMe || $assignment->assigned_reader_id === $user->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->canManageAssignments();
    }

    /** Admin/editor can clone an existing assignment as a new draft */
    public function duplicate(User $user, Assignment $assignment): bool
    {
        return $user->canManageAssignments();
    }

    public function update(User $user, Assignment $assignment): bool
    {
        return $user->canManageAssignments();
    }

    public function delete(User $user, Assignment $assignment): bool
    {
        return $user->canManageAssignments();
    }

    /** Reader, editor, or admin self-assigning an unassigned assignment */
    public function accept(User $user, Assignment $assignment): bool
    {
        $readerTiers = $user->readerProfile?->tiers() ?? [1];

        return $assignment->isAvailable()
            && ($user->isReader() || $user->canManageAssignments())
            && ! $assignment->isReaderBlocked($user->id)
            && (! $assignment->requested_reader_id || $assignment->requested_reader_id === $user->id)
            && ($user->canManageAssignments() || in_array($assignment->tier, $readerTiers, true));
    }

    /** Reader cancelling their own accepted assignment */
    public function cancel(User $user, Assignment $assignment): bool
    {
        return $user->isReader()
            && $assignment->status === Assignment::STATUS_ASSIGNED
            && $assignment->assigned_reader_id === $user->id;
    }

    /** Reader or assigned admin/editor submitting coverage */
    public function submitCoverage(User $user, Assignment $assignment): bool
    {
        return in_array($assignment->status, [Assignment::STATUS_ASSIGNED, Assignment::STATUS_NEEDS_ATTENTION], true)
            && $assignment->assigned_reader_id === $user->id
            && $user->hasAnyRole(['reader', 'admin', 'editor']);
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
