<?php

// v1.1 — 2026-06-05 | Switch to MailerSend template (vywj2lp5v5mg7oqz)
// v1.0 — 2026-06-05 | Magic link login email

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use MailerSend\LaravelDriver\MailerSendTrait;

class MagicLinkMail extends Mailable
{
    use Queueable, SerializesModels, MailerSendTrait;

    public function __construct(
        private readonly User   $user,
        private readonly string $token,
    ) {}

    public function build(): static
    {
        $firstName = $this->user->readerProfile?->first_name
            ?? $this->user->editorProfile?->first_name
            ?? $this->user->name;

        $loginUrl = url('/magic-link/' . $this->token);
        $subject  = 'Your login link for Screenplay Readers Portal';

        $this->subject($subject);

        $this->mailersend(
            template_id: 'vywj2lp5v5mg7oqz',
            personalization: [
                [
                    'email' => $this->user->email,
                    'data'  => [
                        'subject'    => $subject,
                        'first_name' => $firstName,
                        'login_url'  => $loginUrl,
                    ],
                ],
            ],
        );

        return $this;
    }
}
