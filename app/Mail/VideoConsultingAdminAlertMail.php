<?php

// v1.1 — 2026-11-09 | Follow the house mailersend()+template_id convention (see
//                      BudgetDeliveryMail/VideoConsultingCancelledMail) — falls back to the
//                      plain Blade view below until a dashboard template is configured.
// v1.0 — 2026-11-09 | Video consulting Phase 3 — generic admin alert for cancellation / no-show
//                      report / reschedule-deadline-expired.

namespace App\Mail;

use App\Models\Assignment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use MailerSend\LaravelDriver\MailerSendTrait;

class VideoConsultingAdminAlertMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, MailerSendTrait;

    public function __construct(
        public readonly Assignment $assignment,
        public readonly string $heading,
        public readonly string $message,
        public readonly User $admin,
    ) {
    }

    public function build(): static
    {
        $subject = "Video Consulting — {$this->heading} — #{$this->assignment->order_number}";
        $this->subject($subject);

        $templateId = config('services.mailersend.video_consulting_admin_alert_template_id');

        if ($templateId) {
            $this->mailersend(
                template_id: $templateId,
                personalization: [
                    [
                        'email' => $this->admin->email,
                        'data' => [
                            'subject' => $subject,
                            'heading' => $this->heading,
                            'message' => $this->message,
                            'order_number' => $this->assignment->order_number,
                            'script_title' => $this->assignment->script_title,
                            'portal_url' => route('assignments.show', $this->assignment),
                        ],
                    ],
                ],
            );
        } else {
            $this->view('emails.video-consulting.admin-alert');
        }

        return $this;
    }
}
