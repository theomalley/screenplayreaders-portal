<?php

// v1.0 — 2026-05-30 | Initial: notify readers of new unassigned assignment via MailerSend template

namespace App\Mail;

use App\Models\Assignment;
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
        $this->mailersend(
            template_id: config('services.mailersend.assignment_template_id'),
            personalization: [
                [
                    'email' => $this->reader->email,
                    'data'  => [
                        'reader_name'     => $this->reader->readerProfile?->first_name ?? $this->reader->name,
                        'script_title'    => $this->assignment->script_title,
                        'writer_name'     => $this->assignment->writer_name,
                        'assignment_type' => $this->assignment->assignment_type,
                        'page_count'      => $this->assignment->page_count,
                        'rush'            => $this->assignment->rush ? 'RUSH' : '',
                        'is_requested'    => $this->context === 'request' ? 'true' : '',
                        'portal_url'      => config('app.url') . '/assignments',
                    ],
                ],
            ],
        );

        return $this->view('emails.empty');
    }
}
