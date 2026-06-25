<?php

// v1.0 — 2026-06-22 | Initial: generates registration certificate PDF from Google Docs template,
//                      saves to Drive, dispatches email delivery job.

namespace App\Jobs;

use App\Models\ScriptRegistration;
use App\Services\GoogleDocsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GenerateRegistrationCertificate implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;
    public int $backoff = 60;
    public int $timeout = 120;

    public function __construct(
        public readonly int $registrationId,
    ) {}

    public function handle(GoogleDocsService $docs): void
    {
        $reg = ScriptRegistration::findOrFail($this->registrationId);

        if ($reg->status !== ScriptRegistration::STATUS_PENDING) {
            return;
        }

        try {
            $templateId = config('services.google.registration_template_id');
            $outputFolderId = config('services.google.registration_output_folder_id');
            $placeholders = $this->buildPlaceholders($reg);

            $orderPrefix = $reg->woo_order_number ? $reg->woo_order_number . ' — ' : '';
            $filename = $orderPrefix . 'SR Registration Certificate — ' . $reg->script_title . ' — ' . $reg->registration_id;

            $spacesPath = "registrations/{$reg->registration_id}/{$reg->registration_id}-certificate.pdf";
            $result = $docs->generateCertificatePdf($templateId, $placeholders, $filename, $outputFolderId, $spacesPath);

            $reg->update([
                'drive_certificate_pdf_id' => $result['drive_id'],
                'spaces_certificate_pdf_path' => $result['spaces_path'],
            ]);

            Log::info('GenerateRegistrationCertificate: PDF generated', [
                'registration_id' => $reg->id,
                'pdf_id' => $result['drive_id'],
                'spaces_path' => $result['spaces_path'],
            ]);

            DeliverRegistrationEmail::dispatch($reg->id);

        } catch (\Throwable $e) {
            $reg->update([
                'status' => ScriptRegistration::STATUS_FAILED,
                'error_message' => 'Certificate generation failed: ' . $e->getMessage(),
            ]);

            Log::error('GenerateRegistrationCertificate: failed', [
                'registration_id' => $reg->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function buildPlaceholders(ScriptRegistration $reg): array
    {
        return [
            '{{REGISTRATIONNO}}'    => $reg->registration_id,
            '{{DATEREGISTRATION}}'  => $reg->registered_at->format('F j, Y g:i A T'),
            '{{TITLE}}'             => $reg->script_title,
            '{{TYPE}}'              => $reg->type_of_work,
            '{{PAGES}}'             => (string) ($reg->page_count ?? ''),
            '{{EMAIL}}'             => $reg->email,
            '{{AUTHOR1FIRST}}'      => $reg->author_first,
            '{{AUTHOR1LAST}}'       => $reg->author_last,
            '{{ADDRESS}}'           => $reg->street_address,
            '{{CITY}}'              => $reg->city,
            '{{STATE}}'             => $reg->state_or_province,
            '{{ZIP}}'               => $reg->postal_or_zip,
            '{{COUNTRY}}'           => $reg->country,
            '{{ADDITIONALAUTHORS}}' => $reg->additional_authors ?? 'None provided',
            '{{UNIQUEID}}'          => $reg->unique_id ?? 'None provided',
            '{{EXPIRATIONDATE}}'    => $reg->expires_at
                ? $reg->expires_at->format('F j, Y')
                : 'Unlimited',
            '{{AUTHCODE}}'          => $reg->authcode,
            '{{LIFETIMEURL}}'       => $reg->isUnlimited() && $reg->unlimited_token
                ? $reg->publicRegistrationUrl()
                : 'N/A',
        ];
    }
}
