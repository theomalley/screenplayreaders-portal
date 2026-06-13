<?php

// v1.1 — 2026-06-13 | Skip readers who opted into notify_only_if_under_capacity and are
//                      currently at their assignment capacity.
// v1.0 — 2026-05-30 | Initial: email readers of new unassigned assignments per their profile prefs

namespace App\Services;

use App\Mail\NewAssignmentMail;
use App\Models\Assignment;
use App\Models\ReaderProfile;
use App\Models\User;
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
                $requested->readerProfile?->email_notifications &&
                $requested->readerProfile?->email_notify_requests &&
                ! $this->skipForCapacity($requested->readerProfile, true)
            ) {
                Mail::to($requested->email)
                    ->send(new NewAssignmentMail($assignment, $requested, 'request'));
            }
        }

        // General pool: all readers who opted in, excluding the requested reader (already handled above)
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
            ->when($assignment->requested_reader_id, fn ($q) =>
                $q->where('id', '!=', $assignment->requested_reader_id)
            )
            ->get();

        $context = $assignment->rush ? 'rush' : 'any';

        foreach ($readers as $reader) {
            if ($this->skipForCapacity($reader->readerProfile, false)) {
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
}
