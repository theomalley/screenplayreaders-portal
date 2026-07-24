<?php

// v1.0 — 2026-07-23 | Replaces inline abort_unless() calls in InvoiceController — all
//                     10 actions used the same isAdminOrEditor() rule. Secondary
//                     preconditions (invoice_type, status, google_doc_id) stay inline
//                     in the controller since they're not authorization. Covered by
//                     tests/Feature/InvoiceControllerTest.php.

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdminOrEditor();
    }

    public function create(User $user): bool
    {
        return $user->isAdminOrEditor();
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->isAdminOrEditor();
    }

    public function download(User $user, Invoice $invoice): bool
    {
        return $user->isAdminOrEditor();
    }

    public function resend(User $user, Invoice $invoice): bool
    {
        return $user->isAdminOrEditor();
    }

    public function send(User $user, Invoice $invoice): bool
    {
        return $user->isAdminOrEditor();
    }

    public function markPaid(User $user, Invoice $invoice): bool
    {
        return $user->isAdminOrEditor();
    }

    public function markOutstanding(User $user, Invoice $invoice): bool
    {
        return $user->isAdminOrEditor();
    }

    public function void(User $user, Invoice $invoice): bool
    {
        return $user->isAdminOrEditor();
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->isAdminOrEditor();
    }
}
