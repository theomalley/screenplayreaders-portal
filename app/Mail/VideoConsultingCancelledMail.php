<?php

// v1.1 — 2026-11-09 | Follow the house mailersend()+template_id convention (see
//                      BudgetDeliveryMail): sends via a MailerSend dashboard template when one
//                      is configured, otherwise falls back to the plain Blade view below. No
//                      template is configured yet, so this currently sends the Blade fallback.
// v1.0 — 2026-11-09 | Video consulting Phase 3.

namespace App\Mail;

use App\Models\Assignment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use MailerSend\LaravelDriver\MailerSendTrait;

class VideoConsultingCancelledMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, MailerSendTrait;

    public function __construct(public readonly Assignment $assignment)
    {
    }

    public function build(): static
    {
        $subject = 'Your video consulting call has been cancelled';
        $this->subject($subject);

        $templateId = config('services.mailersend.video_consulting_cancelled_template_id');

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
                        ],
                    ],
                ],
            );
        } else {
            $this->view('emails.video-consulting.cancelled');
        }

        return $this;
    }
}
