<?php

// v1.1 — 2026-05-30 | Pre-compute header/body labels; MailerSend does not support nested conditionals
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
        $rush      = $this->assignment->rush;
        $requested = $this->context === 'request';

        $header = match(true) {
            $requested && $rush => 'Rush Reader Request',
            $requested          => 'Reader Request',
            $rush               => 'Rush Assignment Available',
            default             => 'New Assignment Available',
        };

        $script_details = $this->assignment->script_title
            . ' by ' . $this->assignment->writer_name
            . ' (' . $this->assignment->page_count . ' pages'
            . ($rush ? ', RUSH' : '')
            . ')';

        $body_message = $requested
            ? 'You have been specifically requested for this assignment. Head to the portal to accept it -- it may be opened to other readers if not claimed promptly.'
            : 'This assignment has been added to the assignments list. First reader to accept it gets it.';

        $this->mailersend(
            template_id: config('services.mailersend.assignment_template_id'),
            personalization: [
                [
                    'email' => $this->reader->email,
                    'data'  => [
                        'reader_name'    => $this->reader->readerProfile?->first_name ?? $this->reader->name,
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
