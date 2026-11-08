<?php

// v1.1 — 2026-11-09 | Send from support@screenplayreaders.com (not the portal-wide default
//                      portal@screenplayreaders.com) — this is a customer-facing email, matching
//                      the existing support@ address used for Help Scout customer conversations.
// v1.0 — 2026-11-09 | Video consulting Phase 6 — sent right after checkout upgrade + slot pick,
//                      before the reader has accepted. Follows the house mailersend()+
//                      template_id convention (see VideoConsultingCancelledMail) — falls back
//                      to the plain Blade view below until a dashboard template is configured.

namespace App\Mail;

use App\Models\Assignment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use MailerSend\LaravelDriver\MailerSendTrait;

class VideoConsultingBookingConfirmedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, MailerSendTrait;

    public function __construct(public readonly Assignment $assignment)
    {
    }

    public function build(): static
    {
        $subject = 'Your video consulting call is booked';
        $this->subject($subject);
        $this->from('support@screenplayreaders.com', 'Screenplay Readers');

        $templateId = config('services.mailersend.video_consulting_booking_confirmed_template_id');

        if ($templateId) {
            $this->mailersend(
                template_id: $templateId,
                personalization: [
                    [
                        'email' => $this->assignment->video_consulting_customer_email,
                        'data' => [
                            'subject' => $subject,
                            'script_title' => $this->assignment->script_title,
                            'order_number' => $this->assignment->order_number,
                            'slot_time' => $this->assignment->videoConsultingHold?->slot_start?->format('l, F j, Y \a\t g:i A T'),
                        ],
                    ],
                ],
            );
        } else {
            $this->view('emails.video-consulting.booking-confirmed');
        }

        return $this;
    }
}
