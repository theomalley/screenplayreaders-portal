<?php

// v1.0 — 2026-06-21 | Initial: delivers budget PDF (and XLSX if applicable) to customer via MailerSend

namespace App\Mail;

use App\Models\Budget\BudgetOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use MailerSend\LaravelDriver\MailerSendTrait;

class BudgetDeliveryMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, MailerSendTrait;

    public function __construct(
        private readonly BudgetOrder $order,
        private readonly array $fileUrls,
    ) {}

    public function build(): static
    {
        $header = $this->order->header_data ?? [];
        $title = $header['title'] ?? 'Your Film Budget';
        $nameFirst = $header['name_first'] ?? $this->order->customer_name;

        $this->to($this->order->customer_email);
        $this->subject('Your SR Film Budget — ' . $title);

        $personalizationData = [
            'customer_name' => $nameFirst,
            'budget_title'  => $title,
            'pdf_url'       => $this->fileUrls['pdf_download_url'] ?? '',
            'xlsx_url'      => $this->fileUrls['xlsx_download_url'] ?? '',
            'topsheet_only' => $this->order->topsheet_only ? 'true' : 'false',
        ];

        $this->mailersend(
            template_id: config('services.mailersend.budget_template_id'),
            personalization: [
                [
                    'email' => $this->order->customer_email,
                    'data'  => $personalizationData,
                ],
            ],
        );

        return $this;
    }
}
