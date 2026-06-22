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

            $filename = 'SR Registration Certificate — ' . $reg->script_title . ' — ' . $reg->registration_id;

            $pdfId = $docs->generateCertificatePdf($templateId, $placeholders, $filename, $outputFolderId);

            $reg->update([
                'drive_certificate_pdf_id' => $pdfId,
            ]);

            Log::info('GenerateRegistrationCertificate: PDF generated', [
                'registration_id' => $reg->id,
                'pdf_id' => $pdfId,
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

    // Placeholder names must match the {{tags}} in the Google Doc template.
    // Verify against template 1qgeRv6I4HaMP6xozm_0bjyxuJ7kayXk3ZJsd5azNN7Q.
    private function buildPlaceholders(ScriptRegistration $reg): array
    {
        return [
            '{{registration_id}}'       => $reg->registration_id,
            '{{title}}'                 => $reg->script_title,
            '{{page_count}}'            => (string) ($reg->page_count ?? ''),
            '{{type_of_work}}'          => $reg->type_of_work,
            '{{author_first}}'          => $reg->author_first,
            '{{author_last}}'           => $reg->author_last,
            '{{additional_authors}}'    => $reg->additional_authors ?? 'None provided',
            '{{street_address}}'        => $reg->street_address,
            '{{city}}'                  => $reg->city,
            '{{state}}'                 => $reg->state_or_province,
            '{{postal}}'               => $reg->postal_or_zip,
            '{{country}}'              => $reg->country,
            '{{phone}}'                => $reg->phone,
            '{{unique_id}}'            => $reg->unique_id ?? 'None provided',
            '{{email}}'                => $reg->email,
            '{{registration_expires}}' => $reg->expires_at
                ? $reg->expires_at->format('F j, Y')
                : 'Unlimited',
            '{{authcode}}'             => $reg->authcode,
            '{{timestamp}}'            => $reg->registered_at->format('F j, Y g:i A T'),
            '{{date}}'                 => $reg->registered_at->format('F j, Y'),
            '{{uploaded_file_name}}'   => $reg->uploaded_file_name ?? '',
        ];
    }
}
