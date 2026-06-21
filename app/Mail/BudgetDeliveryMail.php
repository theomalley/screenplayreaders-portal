<?php

// v1.1 — 2026-06-21 | Attach PDF and XLSX files downloaded from Drive
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
        private readonly array $attachments = [],
    ) {}

    public function build(): static
    {
        $header = $this->order->header_data ?? [];
        $title = $header['title'] ?? 'Your Film Budget';
        $nameFirst = $header['name_first'] ?? $this->order->customer_name;

        $this->to($this->order->customer_email);
        $this->subject('Your SR Film Budget — ' . $title);

        // Attach PDF and XLSX files
        foreach ($this->attachments as $file) {
            $this->attach($file['path'], [
                'as' => $file['as'],
                'mime' => $file['mime'],
            ]);
        }

        $templateId = config('services.mailersend.budget_template_id');

        if ($templateId) {
            $this->mailersend(
                template_id: $templateId,
                personalization: [
                    [
                        'email' => $this->order->customer_email,
                        'data' => [
                            'customer_name' => $nameFirst,
                            'budget_title' => $title,
                            'pdf_url' => $this->fileUrls['pdf_download_url'] ?? '',
                            'xlsx_url' => $this->fileUrls['xlsx_download_url'] ?? '',
                            'topsheet_only' => $this->order->topsheet_only ? 'true' : 'false',
                        ],
                    ],
                ],
            );
        } else {
            $this->html($this->fallbackHtml($nameFirst, $title));
        }

        return $this;
    }

    private function fallbackHtml(string $name, string $title): string
    {
        $fileList = '';
        foreach ($this->attachments as $file) {
            $fileList .= '<li>' . e($file['as']) . '</li>';
        }

        return '<p>Hi ' . e($name) . ',</p>'
            . '<p>Your film budget <strong>' . e($title) . '</strong> is attached to this email.</p>'
            . ($fileList ? '<ul>' . $fileList . '</ul>' : '')
            . '<p>Thank you,<br>Screenplay Readers</p>';
    }
}
