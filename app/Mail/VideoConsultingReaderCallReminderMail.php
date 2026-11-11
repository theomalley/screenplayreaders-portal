<?php

// v1.1 — 2026-11-11 | Added assignment_url (links to the reader's normal authenticated
//                      assignments.show page, distinct from call_url's call-page shortcut, so the
//                      reader can see the script before the call) and renamed reader_name ->
//                      reader_first_name to match the user's real MailerSend template.
// v1.0 — 2026-11-11 | Video consulting Phase 7 (partial) — 24h pre-call reminder to the
//                      reader. Readers previously had no equivalent of the customer's call-link
//                      email at all; the portal's own "Join Call" button was the only path.
//                      Follows the house mailersend()+template_id convention (see
//                      VideoConsultingCallLinkMail).

namespace App\Mail;

use App\Models\Assignment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use MailerSend\LaravelDriver\MailerSendTrait;

class VideoConsultingReaderCallReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, MailerSendTrait;

    public function __construct(
        public readonly Assignment $assignment,
        public readonly User $reader,
    ) {
    }

    public function build(): static
    {
        $subject = 'Reminder: your video consulting call is in 24 hours';
        $this->subject($subject);

        $templateId = config('services.mailersend.video_consulting_reminder_reader_24h_template_id');

        if ($templateId) {
            $this->mailersend(
                template_id: $templateId,
                personalization: [
                    [
                        'email' => $this->reader->email,
                        'data' => [
                            'subject' => $subject,
                            'reader_first_name' => $this->reader->readerProfile?->first_name ?? $this->reader->name,
                            'script_title' => $this->assignment->script_title,
                            'order_number' => $this->assignment->order_number,
                            'slot_time' => $this->assignment->videoConsultingHold?->slot_start?->format('l, F j, Y \a\t g:i A T'),
                            'call_url' => route('video-consulting-call.reader', $this->assignment),
                            'assignment_url' => route('assignments.show', $this->assignment),
                        ],
                    ],
                ],
            );
        } else {
            $this->view('emails.video-consulting.reader-call-reminder');
        }

        return $this;
    }
}
