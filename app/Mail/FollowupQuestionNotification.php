<?php

// v1.0 — 2026-05-30 | Notify reader of a new followup question via MailerSend template

namespace App\Mail;

use App\Models\FollowupQuestion;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use MailerSend\LaravelDriver\MailerSendTrait;

class FollowupQuestionNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, MailerSendTrait;

    public function __construct(
        private readonly FollowupQuestion $followup,
        private readonly User $reader,
    ) {}

    public function build(): static
    {
        $assignment  = $this->followup->assignment;
        $readerName  = $this->reader->readerProfile?->first_name ?? $this->reader->name;

        $typeLabel = match($assignment?->assignment_type) {
            'script_coverage' => 'Script Coverage',
            'notes_only'      => 'Notes-Only',
            'deep_dive'       => 'Advanced Script Coverage',
            'short'           => 'Short Script Coverage',
            default           => ucwords(str_replace('_', ' ', $assignment?->assignment_type ?? '')),
        };

        $this->subject('Followup Question — ' . ($assignment?->script_title ?? 'Assignment'));

        $this->mailersend(
            template_id: config('services.mailersend.assignment_template_id'),
            personalization: [
                [
                    'email' => $this->reader->email,
                    'data'  => [
                        'reader_name'    => $readerName,
                        'subject'        => 'Followup Question — ' . ($assignment?->script_title ?? 'Assignment'),
                        'header'         => 'You have a Followup Question',
                        'script_details' => ($assignment?->script_title ?? '—') . ' (' . $typeLabel . ')',
                        'body_message'   => 'A customer has submitted a followup question for one of your completed assignments. Log in to the portal to view and respond.',
                        'portal_url'     => config('app.url') . '/assignments',
                    ],
                ],
            ],
        );

        return $this;
    }
}
