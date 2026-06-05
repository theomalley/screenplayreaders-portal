<?php

// v1.0 — 2026-06-05 | Magic link login email

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MagicLinkMail extends Mailable
{
    use Queueable, SerializesModels;

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

        return $this
            ->subject('Your login link for Screenplay Readers Portal')
            ->view('emails.magic-link', [
                'firstName' => $firstName,
                'loginUrl'  => $loginUrl,
            ]);
    }
}
