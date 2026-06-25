<?php

namespace App\Jobs;

use App\Models\ScriptRegistration;
use App\Services\SpacesStorageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CopyRegistrationScriptToSpaces implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;
    public int $backoff = 60;
    public int $timeout = 120;

    public function __construct(
        public readonly int $registrationId,
    ) {}

    public function handle(SpacesStorageService $spaces): void
    {
        $reg = ScriptRegistration::findOrFail($this->registrationId);

        if ($reg->spaces_script_file_path) {
            return;
        }

        if (! $reg->uploaded_file_url) {
            return;
        }

        $response = Http::timeout(60)->get($reg->uploaded_file_url);

        if (! $response->successful()) {
            Log::error('CopyRegistrationScriptToSpaces: download failed', [
                'registration_id' => $reg->id,
                'url' => $reg->uploaded_file_url,
                'status' => $response->status(),
            ]);
            throw new \RuntimeException("Failed to download script: HTTP {$response->status()}");
        }

        $ext = pathinfo($reg->uploaded_file_name ?? $reg->uploaded_file_url, PATHINFO_EXTENSION) ?: 'pdf';
        $path = "registrations/{$reg->registration_id}/{$reg->registration_id}.{$ext}";

        $spaces->store($path, $response->body(), match ($ext) {
            'pdf' => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            default => 'application/octet-stream',
        });

        $reg->update(['spaces_script_file_path' => $path]);

        Log::info('CopyRegistrationScriptToSpaces: copied', [
            'registration_id' => $reg->id,
            'spaces_path' => $path,
        ]);
    }
}
