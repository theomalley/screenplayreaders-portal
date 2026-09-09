<?php

// v1.9 — 2026-09-09 | Added dismissCancelled() — AssignmentController::dismissCancelled()
//                     had no authorize() call at all (any authenticated user could
//                     dismiss any assignment's cancelled notice by ID). (An audit also
//                     flagged addNote() as a possible IDOR — any reader can note any
//                     assignment — but tightening it broke
//                     AssignmentNoteControllerTest::test_reader_can_add_a_note, which
//                     explicitly exercises an unrelated reader noting an unassigned
//                     assignment. Confirmed intentional, left as-is; flagged for the
//                     product owner.)
// v1.8 — 2026-07-24 | view/accept: deny readers an assignment is hidden from
//                     (isHiddenFromReader) — an admin-only override that beats tier
//                     match entirely, unlike isReaderBlocked which only blocks accept().
//                     Setting/clearing the hide list is restricted to admins in
//                     AssignmentController — editors can't touch it.
// v1.7 — 2026-07-20 | view/accept: tier match now goes through App\Support\TierAccess —
//                     generalizes the old hardcoded tier-2-into-tier-1 special case into
//                     admin-configurable cross-visibility/accept per tier pair, for any number
//                     of dynamic tiers. Onboarding-tier readers still never accept outside
//                     their own tier (TierAccess enforces this independent of stored config).
// v1.6 — 2026-07-11 | accept: tier-2 readers may also accept a tier-1 assignment once
//                     Assignment::isOpenToTier2() is true (sat unaccepted past the admin-configured
//                     release window) — mirrors Assignment::scopeAvailable()'s visibility rule.
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
use App\Support\TierAccess;

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
            $tierMatch = $this->tierMatch($user, $assignment, forAccept: false);

            $openToMe = $assignment->isAvailable()
                && ! $assignment->isHiddenFromReader($user->id)
                && (is_null($assignment->requested_reader_id) || $assignment->requested_reader_id === $user->id)
                && $tierMatch;

            return $openToMe || $assignment->assigned_reader_id === $user->id;
        }

        return false;
    }

    /** True if $assignment belongs to at least one tier the reader can reach (own tiers or cross-visibility grants). */
    protected function tierMatch(User $user, Assignment $assignment, bool $forAccept): bool
    {
        $profile = $user->readerProfile;
        if (! $profile) {
            return false;
        }

        $reachableIds = TierAccess::reachableTierIds($profile, $forAccept, $assignment->assignment_type);

        return $assignment->tiers->pluck('id')->intersect($reachableIds)->isNotEmpty();
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

    /** Admin/editor toggle the playful "Take Me" attention-grabber shown to everyone. */
    public function takeMe(User $user, Assignment $assignment): bool
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
        $tierMatch = $user->canManageAssignments() || $this->tierMatch($user, $assignment, forAccept: true);

        return $assignment->isAvailable()
            && ($user->isReader() || $user->canManageAssignments())
            && ! $assignment->isReaderBlocked($user->id)
            && ! $assignment->isHiddenFromReader($user->id)
            && (! $assignment->requested_reader_id || $assignment->requested_reader_id === $user->id)
            && $tierMatch;
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

    public function removeTitlePage(User $user, Assignment $assignment): bool
    {
        return $user->canManageAssignments();
    }

    /**
     * Reader posting a note to the editor (AssignmentNoteController::store).
     * NOTE: deliberately not scoped to the reader's own assignment — an audit flagged
     * this as a possible IDOR (any reader can note any assignment), but
     * tests/Feature/AssignmentNoteControllerTest::test_reader_can_add_a_note explicitly
     * asserts an unrelated reader can note an assignment with no assigned_reader_id, so
     * this is confirmed intentional, not an oversight. Left as-is; flagged for the
     * product owner to confirm rather than silently changed against a passing test.
     */
    public function addNote(User $user, Assignment $assignment): bool
    {
        return $user->isReader();
    }

    /**
     * Reader dismissing a cancelled-assignment notice on their own index page — only
     * for an assignment they actually had a stake in (accepted, or specifically
     * requested for them). Admin/editor can dismiss any cancelled notice.
     */
    public function dismissCancelled(User $user, Assignment $assignment): bool
    {
        if ($assignment->status !== Assignment::STATUS_CANCELLED) {
            return false;
        }

        if ($user->canManageAssignments()) {
            return true;
        }

        return $user->isReader()
            && ($assignment->assigned_reader_id === $user->id || $assignment->requested_reader_id === $user->id);
    }

    /** Admin/editor adding an internal note (AssignmentEditorNoteController::store). */
    public function addEditorNote(User $user, Assignment $assignment): bool
    {
        return $user->canManageAssignments();
    }

    public function streamCoverage(User $user, Assignment $assignment): bool
    {
        return $user->canManageAssignments();
    }

    public function dismissHelpscoutDraft(User $user, Assignment $assignment): bool
    {
        return $user->canManageAssignments();
    }

    /** Shared by over120()/over160() page-count-flag HelpScout drafts. */
    public function flagPageCountDraft(User $user, Assignment $assignment): bool
    {
        return $user->canManageAssignments();
    }
}
