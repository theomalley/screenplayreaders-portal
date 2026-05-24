<?php

// v1.4 — 2026-05-24 | Skip DOCX→PDF conversion for formatting/proofreading — formatter needs editable file.
// v1.3 — 2026-05-24 | DOCX→PDF conversion via Drive import/export before upload.
// v1.2 — 2026-05-22 | Explicit local-disk path resolution; pre-flight file-exists check.
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
use Illuminate\Support\Facades\Storage;

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
        $fullPath   = Storage::disk('local')->path($this->storagePath);

        if (! file_exists($fullPath)) {
            Log::error('UploadScriptToDrive: file not found', [
                'assignment_id' => $this->assignmentId,
                'path'          => $fullPath,
            ]);
            return;
        }

        $uploadPath   = $fullPath;
        $convertedPdf = null;

        $isDocx              = str_ends_with(strtolower($this->storagePath), '.docx');
        $isFormattingOrder   = in_array($assignment->assignment_type, ['formatting', 'proofreading']);

        if ($isDocx && ! $isFormattingOrder) {
            Log::info('UploadScriptToDrive: converting DOCX to PDF via Drive', [
                'order_number' => $assignment->order_number,
            ]);
            $convertedPdf = $drive->convertDocxToPdf($fullPath);
            $uploadPath   = $convertedPdf;
        }

        try {
            $fileName = FilenameGenerator::script($assignment);
            $fileId   = $drive->uploadScript($assignment->order_number, $uploadPath, $fileName);

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
        } finally {
            @unlink($fullPath);
            if ($convertedPdf) {
                @unlink($convertedPdf);
            }
        }
    }
}
