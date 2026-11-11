<?php

// v1.0 — 2026-11-11 | Video consulting Phase 7 (partial) — 24h pre-call reminder to the
//                      customer. Sent by App\Console\Commands\SendVideoConsultingCallReminders.
//                      Follows the house mailersend()+template_id convention (see
//                      VideoConsultingCallLinkMail).

namespace App\Mail;

use App\Models\Assignment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use MailerSend\LaravelDriver\MailerSendTrait;

class VideoConsultingCallReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, MailerSendTrait;

    public function __construct(
        public readonly Assignment $assignment,
    ) {
    }

    public function build(): static
    {
        $subject = 'Reminder: your video consulting call is in 24 hours';
        $this->subject($subject);
        $this->from('support@screenplayreaders.com', 'Screenplay Readers');

        $templateId = config('services.mailersend.video_consulting_reminder_customer_24h_template_id');

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
                            'call_url' => route('video-consulting-call.public', $this->assignment->video_consulting_call_token),
                        ],
                    ],
                ],
            );
        } else {
            $this->view('emails.video-consulting.call-reminder');
        }

        return $this;
    }
}
