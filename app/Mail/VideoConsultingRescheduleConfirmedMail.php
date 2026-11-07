<?php

// v1.1 — 2026-11-09 | Follow the house mailersend()+template_id convention (see
//                      BudgetDeliveryMail/VideoConsultingCancelledMail) — falls back to the
//                      plain Blade view below until a dashboard template is configured.
// v1.0 — 2026-11-09 | Video consulting Phase 3.

namespace App\Mail;

use App\Models\Assignment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use MailerSend\LaravelDriver\MailerSendTrait;

class VideoConsultingRescheduleConfirmedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, MailerSendTrait;

    public function __construct(public readonly Assignment $assignment)
    {
    }

    public function build(): static
    {
        $subject = 'Your video consulting call has been rescheduled';
        $this->subject($subject);

        $templateId = config('services.mailersend.video_consulting_reschedule_confirmed_template_id');

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
                            'new_time' => $this->assignment->videoConsultingHold?->slot_start?->format('l, F j, Y \a\t g:i A T'),
                        ],
                    ],
                ],
            );
        } else {
            $this->view('emails.video-consulting.reschedule-confirmed');
        }

        return $this;
    }
}
