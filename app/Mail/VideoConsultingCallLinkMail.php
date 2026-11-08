<?php

// v1.1 — 2026-11-09 | Send from support@screenplayreaders.com (not the portal-wide default
//                      portal@screenplayreaders.com) — this is a customer-facing email, matching
//                      the existing support@ address used for Help Scout customer conversations.
// v1.0 — 2026-11-09 | Video consulting Phase 6 — sent once the reader accepts and
//                      VideoConsultingCallService creates the Whereby room. Stopgap until
//                      Phase 7's polished magic-link call page exists — for now the customer
//                      just gets the raw Whereby URL by email. Follows the house
//                      mailersend()+template_id convention (see VideoConsultingCancelledMail).

namespace App\Mail;

use App\Models\Assignment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use MailerSend\LaravelDriver\MailerSendTrait;

class VideoConsultingCallLinkMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, MailerSendTrait;

    public function __construct(public readonly Assignment $assignment)
    {
    }

    public function build(): static
    {
        $subject = 'Your video consulting call link';
        $this->subject($subject);
        $this->from('support@screenplayreaders.com', 'Screenplay Readers');

        $templateId = config('services.mailersend.video_consulting_call_link_template_id');

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
                            'call_url' => $this->assignment->whereby_room_url,
                        ],
                    ],
                ],
            );
        } else {
            $this->view('emails.video-consulting.call-link');
        }

        return $this;
    }
}
