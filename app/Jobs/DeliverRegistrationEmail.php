<?php

// v1.0 — 2026-06-22 | Initial: downloads certificate PDF from Drive, emails to customer
//                      via MailerSend template, updates registration status.

namespace App\Jobs;

use App\Mail\RegistrationCertificateMail;
use App\Models\ScriptRegistration;
use App\Services\GoogleDocsService;
use App\Services\SpacesStorageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class DeliverRegistrationEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public readonly int $registrationId,
    ) {}

    public function handle(GoogleDocsService $docs, SpacesStorageService $spaces): void
    {
        $reg = ScriptRegistration::findOrFail($this->registrationId);

        if (! $reg->drive_certificate_pdf_id) {
            Log::warning('DeliverRegistrationEmail: no PDF ID, skipping', [
                'registration_id' => $reg->id,
            ]);
            return;
        }

        $tempFiles = [];

        try {
            $pdfBytes = $reg->spaces_certificate_pdf_path
                ? $spaces->get($reg->spaces_certificate_pdf_path)
                : $docs->downloadDriveFileBytes($reg->drive_certificate_pdf_id);
            $pdfPath = tempnam(sys_get_temp_dir(), 'sr_reg_cert_') . '.pdf';
            file_put_contents($pdfPath, $pdfBytes);
            $tempFiles[] = $pdfPath;

            $safeTitle = preg_replace('/[^\w\s\-.]/', '', $reg->script_title);
            $attachments = [[
                'path' => $pdfPath,
                'as'   => "SR Registration Certificate - {$safeTitle}.pdf",
                'mime' => 'application/pdf',
            ]];

            Mail::send(new RegistrationCertificateMail($reg, $attachments));

            $reg->update([
                'status' => ScriptRegistration::STATUS_COMPLETED,
            ]);

            Log::info('DeliverRegistrationEmail: sent', [
                'registration_id' => $reg->id,
                'email' => $reg->email,
            ]);
        } finally {
            foreach ($tempFiles as $tmp) {
                @unlink($tmp);
            }
        }
    }
}
