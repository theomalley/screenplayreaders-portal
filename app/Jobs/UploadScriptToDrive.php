<?php

// v1.1 — 2026-05-22 | Use FilenameGenerator for Drive filename; update all sibling assignments;
//                     pass order_number (not ID) to uploadScript.
// v1.0 — 2026-05-19 | Queued job: upload a locally-stored script to Google Drive,
//                     store the resulting file ID on the assignment, clean up the temp file.

namespace App\Jobs;

use App\Models\Assignment;
use App\Services\GoogleDriveService;
use App\Support\FilenameGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

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
        $fullPath   = storage_path('app/' . $this->storagePath);

        $fileName = FilenameGenerator::script($assignment);
        $fileId   = $drive->uploadScript($assignment->order_number, $fullPath, $fileName);

        // Update all assignments sharing this order_number (multi-reader orders)
        Assignment::where('order_number', $assignment->order_number)->update([
            'drive_script_file_id'  => $fileId,
            'drive_script_filename' => $fileName,
        ]);

        Log::info('UploadScriptToDrive: complete', [
            'order_number' => $assignment->order_number,
            'file_id'      => $fileId,
            'filename'     => $fileName,
        ]);

        @unlink($fullPath);
    }
}
