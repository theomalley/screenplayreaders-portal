<?php

// v1.1 — 2026-11-09 | Video consulting Phase 6 — add notifyCustomerBookingConfirmed() (checkout
//                      upgrade + slot pick) and notifyCustomerCallLinkReady() (reader accepted,
//                      Whereby room exists) — stopgap customer notifications until Phase 7's
//                      polished call page exists.
// v1.0 — 2026-11-09 | Video consulting Phase 3 — kept separate from ReaderNotificationService
//                      (scoped to ordinary coverage assignments) since these are a different
//                      audience (customer-facing) and a different trigger shape entirely.
//                      Email-only this phase — no Help Scout ticket creation or Pushover (the
//                      portal has neither today; see the Phase 3 plan for why).

namespace App\Services;

use App\Mail\VideoConsultingAdminAlertMail;
use App\Mail\VideoConsultingBookingConfirmedMail;
use App\Mail\VideoConsultingCallLinkMail;
use App\Mail\VideoConsultingCancelledMail;
use App\Mail\VideoConsultingRescheduleConfirmedMail;
use App\Mail\VideoConsultingRescheduleOfferMail;
use App\Models\Assignment;
use App\Models\User;
use App\Models\VideoConsultingRescheduleOffer;
use Illuminate\Support\Facades\Mail;

class VideoConsultingNotificationService
{
    public function notifyCustomerBookingConfirmed(Assignment $assignment): void
    {
        if ($assignment->video_consulting_customer_email) {
            Mail::to($assignment->video_consulting_customer_email)
                ->send(new VideoConsultingBookingConfirmedMail($assignment));
        }
    }

    public function notifyCustomerCallLinkReady(Assignment $assignment): void
    {
        if ($assignment->video_consulting_customer_email) {
            Mail::to($assignment->video_consulting_customer_email)
                ->send(new VideoConsultingCallLinkMail($assignment));
        }
    }

    public function notifyCancellation(Assignment $assignment): void
    {
        if ($assignment->video_consulting_customer_email) {
            Mail::to($assignment->video_consulting_customer_email)
                ->send(new VideoConsultingCancelledMail($assignment));
        }

        $this->notifyAdmins($assignment, 'Call Cancelled', "The reader cancelled order #{$assignment->order_number}.");
    }

    public function notifyNoShowReported(Assignment $assignment): void
    {
        $this->notifyAdmins(
            $assignment,
            'No-Show Reported',
            "The reader reported that the customer did not join the call for order #{$assignment->order_number}. Confirm or dismiss from the assignment page."
        );
    }

    public function notifyRescheduleOffer(Assignment $assignment, VideoConsultingRescheduleOffer $offer): void
    {
        if ($assignment->video_consulting_customer_email) {
            Mail::to($assignment->video_consulting_customer_email)
                ->send(new VideoConsultingRescheduleOfferMail($assignment, $offer));
        }
    }

    public function notifyRescheduleConfirmed(Assignment $assignment): void
    {
        if ($assignment->video_consulting_customer_email) {
            Mail::to($assignment->video_consulting_customer_email)
                ->send(new VideoConsultingRescheduleConfirmedMail($assignment));
        }
    }

    public function notifyRescheduleDeadlineExpired(Assignment $assignment): void
    {
        $this->notifyAdmins(
            $assignment,
            'Reschedule Offer Expired',
            "The customer never responded to the reschedule offer for order #{$assignment->order_number}. Confirm or dismiss from the assignment page."
        );
    }

    private function notifyAdmins(Assignment $assignment, string $heading, string $message): void
    {
        foreach (User::where('role', 'admin')->get() as $admin) {
            Mail::to($admin->email)->send(new VideoConsultingAdminAlertMail($assignment, $heading, $message, $admin));
        }
    }
}
