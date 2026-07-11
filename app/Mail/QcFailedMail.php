<?php

// v1.0 — 2026-06-05 | Notify reader when their assignment is sent back from QC (needs_attention).

namespace App\Mail;

use App\Models\Assignment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use MailerSend\LaravelDriver\MailerSendTrait;

class QcFailedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, MailerSendTrait;

    public function __construct(
        private readonly Assignment $assignment,
        private readonly User $reader,
    ) {}

    public function build(): static
    {
        $readerName = $this->reader->readerProfile?->first_name ?? $this->reader->name;

        $typeLabel = match($this->assignment->assignment_type) {
            'script_coverage'   => 'Script Coverage',
            'notes_only'        => 'Notes-Only',
            'deep_dive'         => 'Advanced Script Coverage',
            'short'             => 'Short Script Coverage',
            default             => ucwords(str_replace('_', ' ', $this->assignment->assignment_type ?? '')),
        };

        $notes = $this->assignment->needs_attention_notes;
        $bodyMessage = 'Your coverage for one of your assignments did not pass QC and has been returned to you for revisions. Please log in to the portal to review the notes and resubmit.';
        if ($notes) {
            $bodyMessage .= "\n\nNotes from QC: " . $notes;
        }

        $this->subject('Coverage Needs Revision — ' . $this->assignment->script_title);

        $this->mailersend(
            template_id: config('services.mailersend.assignment_template_id'),
            personalization: [
                [
                    'email' => $this->reader->email,
                    'data'  => [
                        'reader_name'    => $readerName,
                        'subject'        => 'Coverage Needs Revision — ' . $this->assignment->script_title,
                        'header'         => 'Your Coverage Needs Revision',
                        'script_details' => $this->assignment->script_title . ' (' . $typeLabel . ')',
                        'body_message'   => $bodyMessage,
                        'portal_url'     => config('app.url') . '/assignments#tab-attention',
                    ],
                ],
            ],
        );

        return $this;
    }
}
