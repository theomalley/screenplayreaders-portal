<?php

// v1.3 — 2026-05-30 | Add subject line variants from Settings
// v1.2 — 2026-05-30 | Read header/body text from Settings (admin-editable)
// v1.1 — 2026-05-30 | Pre-compute header/body labels; MailerSend does not support nested conditionals
// v1.0 — 2026-05-30 | Initial: notify readers of new unassigned assignment via MailerSend template

namespace App\Mail;

use App\Models\Assignment;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Mailersend\LaravelDriver\MailerSendModelMixin;

class NewAssignmentMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, MailerSendModelMixin;

    public function __construct(
        private readonly Assignment $assignment,
        private readonly User $reader,
        private readonly string $context = 'any', // 'any' | 'rush' | 'request'
    ) {}

    public function build(): static
    {
        $rush      = $this->assignment->rush;
        $requested = $this->context === 'request';
        $texts     = Setting::getEmailNotificationTexts();

        $subject = match(true) {
            $requested && $rush => $texts['email_notif_subject_rush_request'],
            $requested          => $texts['email_notif_subject_request'],
            $rush               => $texts['email_notif_subject_rush'],
            default             => $texts['email_notif_subject_new'],
        };

        $header = match(true) {
            $requested && $rush => $texts['email_notif_header_rush_request'],
            $requested          => $texts['email_notif_header_request'],
            $rush               => $texts['email_notif_header_rush'],
            default             => $texts['email_notif_header_new'],
        };

        $script_details = $this->assignment->script_title
            . ' by ' . $this->assignment->writer_name
            . ' (' . $this->assignment->page_count . ' pages'
            . ($rush ? ', RUSH' : '')
            . ')';

        $body_message = $requested
            ? $texts['email_notif_body_request']
            : $texts['email_notif_body_new'];

        $this->subject($subject);

        $this->mailersend(
            template_id: config('services.mailersend.assignment_template_id'),
            personalization: [
                [
                    'email' => $this->reader->email,
                    'data'  => [
                        'reader_name'    => $this->reader->readerProfile?->first_name ?? $this->reader->name,
                        'subject'        => $subject,
                        'header'         => $header,
                        'script_details' => $script_details,
                        'body_message'   => $body_message,
                        'portal_url'     => config('app.url') . '/assignments',
                    ],
                ],
            ],
        );

        return $this->view('emails.empty');
    }
}
