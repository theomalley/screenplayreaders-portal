<?php

// v1.1 — 2026-08-29 | Switch to the dedicated qc_needs_attention_template_id MailerSend
//                      template (pxkjn4138jqgz781) instead of sharing assignment_template_id
//                      with NewAssignmentMail — its copy ("Your assignment needs attention" /
//                      portal button) is static in the template, so only reader_name and
//                      script_details are passed; header/body_message/subject-as-data dropped.
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

        $this->subject('Coverage Needs Revision — ' . $this->assignment->script_title);

        $this->mailersend(
            template_id: config('services.mailersend.qc_needs_attention_template_id'),
            personalization: [
                [
                    'email' => $this->reader->email,
                    'data'  => [
                        'reader_name'    => $readerName,
                        'script_details' => $this->assignment->script_title . ' (' . $typeLabel . ')',
                        'portal_url'     => config('app.url') . '/assignments#tab-attention',
                    ],
                ],
            ],
        );

        return $this;
    }
}
