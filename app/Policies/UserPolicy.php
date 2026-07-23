<?php

// v1.1 — 2026-07-23 | Add editor/reader account-management abilities (viewAnyEditors,
//                     createEditor, editEditor, updateEditorRates, manageEditorCommissions,
//                     deleteEditor and their reader equivalents), replacing inline
//                     abort_unless(isAdmin())/Permission::check() calls in
//                     EditorProfileController/ReaderProfileController. These are pure
//                     role/permission checks with no dependency on which specific User is
//                     being managed — the "is $target actually an editor/reader" check
//                     stays inline in the controller as a 404 (wrong resource shape), not
//                     a 403 (authorization), so it isn't folded in here.
// v1.0 — 2026-07-23 | impersonate() — replaces inline abort checks in
//                     ImpersonateController::start(). Covered by
//                     tests/Feature/ImpersonateControllerTest.php.

namespace App\Policies;

use App\Models\User;
use App\Support\Permission;

class UserPolicy
{
    /** Only an admin may impersonate, and never another admin. */
    public function impersonate(User $actor, User $target): bool
    {
        return $actor->isAdmin() && ! $target->isAdmin();
    }

    public function viewAnyEditors(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function createEditor(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function editEditor(User $actor): bool
    {
        return Permission::check('editors.edit', $actor);
    }

    public function updateEditorRates(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function manageEditorCommissions(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function deleteEditor(User $actor): bool
    {
        return Permission::check('editors.delete', $actor);
    }

    public function viewAnyReaders(User $actor): bool
    {
        return $actor->canManageAssignments();
    }

    public function createReader(User $actor): bool
    {
        return $actor->canManageAssignments();
    }

    public function editReader(User $actor): bool
    {
        return Permission::check('readers.edit', $actor);
    }

    public function deleteReader(User $actor): bool
    {
        return Permission::check('readers.delete', $actor);
    }

    public function viewAnyTeam(User $actor): bool
    {
        return Permission::check('team', $actor);
    }

    /** Admin-only toggle of another user's hidden_from_staff flag. */
    public function toggleVisibility(User $actor, User $target): bool
    {
        return $actor->isAdmin();
    }

    // --- Editor pay (EditorPayController) — $editor is the User being paid ---

    public function editorPayMarkPaid(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function editorPayClearUnpaidBatch(User $actor): bool
    {
        return $actor->isAdmin();
    }

    /** Admin can revert any editor's payment; an editor may only revert their own. */
    public function editorPayMarkUnpaid(User $actor, User $editor): bool
    {
        return $actor->isAdminOrEditor() && ($actor->isAdmin() || $actor->id === $editor->id);
    }

    public function editorPayAddAdjustment(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function editorPayDeleteHistoryBatch(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function editorPayDeleteAllHistory(User $actor): bool
    {
        return $actor->isAdmin();
    }

    // --- Reader pay (ReaderPayController) ---

    public function readerPayMarkPaid(User $actor): bool
    {
        return $actor->isAdminOrEditor();
    }

    public function readerPayMarkUnpaid(User $actor): bool
    {
        return $actor->isAdminOrEditor();
    }

    public function readerPayAddAdjustment(User $actor): bool
    {
        return $actor->isAdminOrEditor();
    }

    public function readerPayDeleteAdjustment(User $actor): bool
    {
        return $actor->isAdminOrEditor();
    }

    public function readerPayDeleteAssignmentPay(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function readerPayClearUnpaidBatch(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function readerPayRemoveHistoryBatch(User $actor): bool
    {
        return $actor->isAdmin();
    }

    // --- StaffCardController ---

    public function viewStaffCard(User $actor): bool
    {
        return $actor->canManageAssignments();
    }

    public function draftStaffEmail(User $actor, User $target): bool
    {
        return $actor->canManageAssignments() && ! $target->isAdmin();
    }
}
