<?php

// v1.0 — 2026-05-19 | Queued job: upload a locally-stored PDF to Google Drive,
//                     store the resulting file ID on the assignment, clean up the temp file.

namespace App\Jobs;

use App\Models\Assignment;
use App\Services\GoogleDriveService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class UploadScriptToDrive implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $assignmentId,
        public readonly string $storagePath, // relative to storage/app/
    ) {}

    public function handle(GoogleDriveService $drive): void
    {
        $assignment = Assignment::findOrFail($this->assignmentId);

        $fullPath = storage_path('app/' . $this->storagePath);

        $fileId = $drive->uploadScript($assignment->id, $fullPath);

        $assignment->update(['drive_script_file_id' => $fileId]);

        @unlink($fullPath);
    }
}
