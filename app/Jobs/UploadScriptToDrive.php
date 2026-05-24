<?php

// v1.4 — 2026-05-24 | Formatting keeps original file format; proofreading converts to PDF like other services.
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

        $origExt      = strtolower(pathinfo($this->storagePath, PATHINFO_EXTENSION));
        $uploadPath   = $fullPath;
        $convertedPdf = null;

        if ($assignment->assignment_type === 'formatting') {
            // Formatting work happens outside the portal — keep the original file format.
            $fileName = FilenameGenerator::base($assignment) . '.' . $origExt;
            $mimeType = $this->mimeForExt($origExt);
        } else {
            // All other services (including proofreading) need a PDF.
            if ($origExt === 'docx') {
                Log::info('UploadScriptToDrive: converting DOCX to PDF via Drive', [
                    'order_number' => $assignment->order_number,
                ]);
                $convertedPdf = $drive->convertDocxToPdf($fullPath);
                $uploadPath   = $convertedPdf;
            }
            $fileName = FilenameGenerator::script($assignment);
            $mimeType = 'application/pdf';
        }

        try {
            $fileId = $drive->uploadScript($assignment->order_number, $uploadPath, $fileName, $mimeType);

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

    private function mimeForExt(string $ext): string
    {
        return match($ext) {
            'pdf'      => 'application/pdf',
            'docx'     => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'fdx'      => 'application/xml',
            'fadein'   => 'application/zip',
            'fountain' => 'text/plain',
            default    => 'application/octet-stream',
        };
    }
}
