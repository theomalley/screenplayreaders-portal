<?php

// v1.1 — 2026-11-09 | Follow the house mailersend()+template_id convention (see
//                      BudgetDeliveryMail/VideoConsultingCancelledMail) — falls back to the
//                      plain Blade view below until a dashboard template is configured.
// v1.0 — 2026-11-09 | Video consulting Phase 3.

namespace App\Mail;

use App\Models\Assignment;
use App\Models\VideoConsultingRescheduleOffer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use MailerSend\LaravelDriver\MailerSendTrait;

class VideoConsultingRescheduleOfferMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, MailerSendTrait;

    public readonly string $offerUrl;

    public function __construct(
        public readonly Assignment $assignment,
        public readonly VideoConsultingRescheduleOffer $offer,
    ) {
        $this->offerUrl = route('video-consulting-reschedule-offer.show', $offer->token);
    }

    public function build(): static
    {
        $subject = 'Please pick a new time for your video consulting call';
        $this->subject($subject);

        $templateId = config('services.mailersend.video_consulting_reschedule_offer_template_id');

        if ($templateId) {
            $this->mailersend(
                template_id: $templateId,
                personalization: [
                    [
                        'email' => $this->assignment->video_consulting_customer_email,
                        'data' => [
                            'subject' => $subject,
                            'script_title' => $this->assignment->script_title,
                            'order_number' => $this->assignment->order_number,
                            'offer_url' => $this->offerUrl,
                        ],
                    ],
                ],
            );
        } else {
            $this->view('emails.video-consulting.reschedule-offer');
        }

        return $this;
    }
}
