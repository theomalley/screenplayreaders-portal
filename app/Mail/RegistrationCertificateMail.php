<?php

// v1.0 — 2026-06-22 | Initial: delivers script registration certificate PDF to customer
//                      via MailerSend template. For unlimited registrations, includes
//                      the tokenized personal URL in personalization data.

namespace App\Mail;

use App\Models\ScriptRegistration;
use Illuminate\Mail\Mailable;
use MailerSend\LaravelDriver\MailerSendTrait;

class RegistrationCertificateMail extends Mailable
{
    use MailerSendTrait;

    public function __construct(
        private readonly ScriptRegistration $registration,
        private readonly array $certificateFiles = [],
    ) {}

    public function build(): static
    {
        $reg = $this->registration;

        $this->to($reg->email);
        $this->subject('Your Script Registration Certificate — ' . $reg->script_title);

        foreach ($this->certificateFiles as $file) {
            $this->attach($file['path'], [
                'as'   => $file['as'],
                'mime' => $file['mime'],
            ]);
        }

        $templateId = config('services.mailersend.registration_template_id');

        if ($templateId) {
            $personalization = [
                'customer_name'        => $reg->author_first,
                'script_title'         => $reg->script_title,
                'registration_id'      => $reg->registration_id,
                'authcode'             => $reg->authcode,
                'registration_expires' => $reg->expires_at
                    ? $reg->expires_at->format('F j, Y')
                    : 'Unlimited',
                'variation_label'      => $reg->variation_label,
                'unlimited_url'        => ($reg->isUnlimited() && $reg->unlimited_token)
                    ? $reg->publicRegistrationUrl()
                    : 'N/A',
            ];

            $this->mailersend(
                template_id: $templateId,
                personalization: [
                    [
                        'email' => $reg->email,
                        'data'  => $personalization,
                    ],
                ],
            );
        } else {
            $this->html($this->fallbackHtml());
        }

        return $this;
    }

    private function fallbackHtml(): string
    {
        $reg = $this->registration;
        $expiry = $reg->expires_at ? $reg->expires_at->format('F j, Y') : 'Unlimited';

        $html = '<p>Hi ' . e($reg->author_first) . ',</p>'
            . '<p>Your script registration certificate for <strong>' . e($reg->script_title) . '</strong> is attached.</p>'
            . '<p>Registration ID: ' . e($reg->registration_id) . '<br>'
            . 'Expires: ' . e($expiry) . '</p>';

        if ($reg->isUnlimited() && $reg->unlimited_token) {
            $html .= '<p>As an unlimited registration member, you can register additional scripts at:<br>'
                . '<a href="' . e($reg->publicRegistrationUrl()) . '">' . e($reg->publicRegistrationUrl()) . '</a></p>';
        }

        $html .= '<p>Thank you,<br>Screenplay Readers</p>';

        return $html;
    }
}
