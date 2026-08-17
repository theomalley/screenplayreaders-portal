<?php

// v1.5 — 2026-08-17 | Dedup: a reader can now only be emailed once per order_number, via the
//                      new assignment_notified_readers ledger (checked/claimed with insertOrIgnore
//                      to stay race-safe). Fixes readers getting indistinguishable duplicate
//                      emails when a multi-reader order has same-type sibling slots (e.g. the
//                      two notes_only slots on a 3-reader order both going STATUS_UNASSIGNED
//                      independently), and readers getting re-emailed for an assignment that was
//                      already STATUS_UNASSIGNED when EscalateTierTimeouts transfers its tier.
// v1.4 — 2026-07-31 | Add notifyOptedInAdmins() — admins who opt in (ProfileController::
//                      updateNotifications(), admin-only) get a copy of the same
//                      NewAssignmentMail a reader would get, for previewing/testing the
//                      template. Not gated by the accept() policy or capacity — this isn't
//                      a real eligibility check, just "send me what a reader would see."
// v1.3 — 2026-07-31 | Fix: neither notification path checked tier reachability,
//                      allowed_assignment_types, hidden_from_reader_ids, or blocked_reader_ids
//                      before emailing — an opted-in reader outside the assignment's tier (or
//                      explicitly hidden/blocked from it) still got the email even though
//                      AssignmentPolicy::accept() would deny them. Both paths now gate through
//                      Gate::forUser($reader)->allows('accept', $assignment) — the same check
//                      the Accept button itself uses — instead of duplicating the tier logic.
// v1.2 — 2026-07-10 | Fix: general pool notification no longer fires at all when the
//                      assignment has a requested_reader_id — it was previously only
//                      excluding the requested reader themselves, so every other opted-in
//                      reader still got emailed about a request meant for someone else.
// v1.1 — 2026-06-13 | Skip readers who opted into notify_only_if_under_capacity and are
//                      currently at their assignment capacity.
// v1.0 — 2026-05-30 | Initial: email readers of new unassigned assignments per their profile prefs

namespace App\Services;

use App\Mail\NewAssignmentMail;
use App\Models\Assignment;
use App\Models\ReaderProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;

class ReaderNotificationService
{
    /**
     * Notify eligible readers that a new assignment is available.
     * Call this after any assignment is created or transitions to STATUS_UNASSIGNED.
     */
    public function notifyNewAssignment(Assignment $assignment): void
    {
        if ($assignment->status !== Assignment::STATUS_UNASSIGNED) {
            return;
        }

        // Notify the specifically requested reader first (different context string for template)
        if ($assignment->requested_reader_id) {
            $requested = User::with('readerProfile')->find($assignment->requested_reader_id);

            if (
                $requested &&
                Gate::forUser($requested)->allows('accept', $assignment) &&
                $requested->readerProfile?->email_notifications &&
                $requested->readerProfile?->email_notify_requests &&
                ! $this->skipForCapacity($requested->readerProfile, true) &&
                $this->claimNotification($assignment->order_number, $requested->id)
            ) {
                Mail::to($requested->email)
                    ->send(new NewAssignmentMail($assignment, $requested, 'request'));
            }

            $this->notifyOptedInAdmins($assignment, 'request');
        }

        // General pool: only for assignments open to anyone. Assignments with a
        // requested_reader_id are targeted at that one reader (handled above) — they
        // must not also blast the whole opted-in pool, even excluding that reader,
        // since this is a "request", not a general "available" notification.
        if ($assignment->requested_reader_id) {
            return;
        }

        $this->notifyOptedInAdmins($assignment, $assignment->rush ? 'rush' : 'any');

        $readers = User::with('readerProfile')
            ->whereHas('readerProfile', function ($q) use ($assignment) {
                $q->where('email_notifications', true);

                if ($assignment->rush) {
                    $q->where(function ($q2) {
                        $q2->where('email_notify_any', true)
                           ->orWhere('email_notify_rush', true);
                    });
                } else {
                    $q->where('email_notify_any', true);
                }
            })
            ->get();

        $context = $assignment->rush ? 'rush' : 'any';

        foreach ($readers as $reader) {
            if (! Gate::forUser($reader)->allows('accept', $assignment)) {
                continue;
            }

            if ($this->skipForCapacity($reader->readerProfile, false)) {
                continue;
            }

            if (! $this->claimNotification($assignment->order_number, $reader->id)) {
                continue;
            }

            Mail::to($reader->email)
                ->send(new NewAssignmentMail($assignment, $reader, $context));
        }
    }

    /**
     * True if the reader opted into "only notify if under capacity" and is currently
     * at (or over) their assignment capacity.
     */
    private function skipForCapacity(?ReaderProfile $profile, bool $isRequestedAssignment): bool
    {
        if (! $profile?->notify_only_if_under_capacity) {
            return false;
        }

        return $profile->isAtCapacity($isRequestedAssignment);
    }

    /**
     * Atomically claims the (order_number, reader) pair via the unique index on
     * assignment_notified_readers — returns true (and records the claim) only the
     * first time this reader is notified about this order, across every sibling
     * Assignment row and every trigger (creation, escalation, scheduled release).
     * insertOrIgnore() makes this race-safe against two triggers firing concurrently.
     */
    private function claimNotification(string $orderNumber, int $readerId): bool
    {
        return DB::table('assignment_notified_readers')->insertOrIgnore([
            'order_number' => $orderNumber,
            'reader_id'    => $readerId,
            'created_at'   => now(),
        ]) > 0;
    }

    /**
     * Admins who opted into "preview the new-assignment email" (editor_profiles.email_notify_*,
     * admin-only — editors never have these set) get a copy of the exact email a reader would
     * receive for this context, addressed to them. This is for previewing/testing the MailerSend
     * template, not a real eligibility check, so it skips accept() and capacity entirely.
     */
    private function notifyOptedInAdmins(Assignment $assignment, string $context): void
    {
        $admins = User::where('role', 'admin')
            ->with('editorProfile')
            ->whereHas('editorProfile', function ($q) use ($context) {
                $q->where('email_notifications', true);

                if ($context === 'request') {
                    $q->where('email_notify_requests', true);
                } elseif ($context === 'rush') {
                    $q->where(function ($q2) {
                        $q2->where('email_notify_any', true)
                           ->orWhere('email_notify_rush', true);
                    });
                } else {
                    $q->where('email_notify_any', true);
                }
            })
            ->get();

        foreach ($admins as $admin) {
            Mail::to($admin->email)->send(new NewAssignmentMail($assignment, $admin, $context));
        }
    }
}
