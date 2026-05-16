<?php

// v1.0 — 2026-05-16 | Stub — all Google Drive API calls will live here.
// Requires google/apiclient — add to composer.json when Drive integration is built.

namespace App\Services;

class GoogleDriveService
{
    // Drive folder IDs are set via GOOGLE_DRIVE_SCRIPTS_FOLDER_ID and
    // GOOGLE_DRIVE_COVERAGE_FOLDER_ID environment variables.

    /**
     * Upload a script PDF to the scripts/{assignment_id}/ Drive folder.
     * Returns the Drive file ID.
     */
    public function uploadScript(int $assignmentId, string $localPath): string
    {
        throw new \RuntimeException('GoogleDriveService not yet implemented.');
    }

    /**
     * Remove the first page of a PDF, re-upload in place, and return the same file ID.
     * Uses a local PDF library (e.g. spatie/pdf-to-image or barryvdh/laravel-dompdf).
     */
    public function removeTitlePage(string $driveFileId, string $localPath): string
    {
        throw new \RuntimeException('GoogleDriveService not yet implemented.');
    }

    /**
     * Create a Google Doc from coverage content and return the Doc file ID.
     */
    public function createCoverageDoc(int $assignmentId, string $htmlContent): string
    {
        throw new \RuntimeException('GoogleDriveService not yet implemented.');
    }

    /**
     * Export a Google Doc to PDF, save to Drive, and return the PDF file ID.
     */
    public function exportDocToPdf(string $docFileId, int $assignmentId): string
    {
        throw new \RuntimeException('GoogleDriveService not yet implemented.');
    }

    /**
     * Return a view-only sharing link for a Drive file.
     */
    public function viewLink(string $driveFileId): string
    {
        throw new \RuntimeException('GoogleDriveService not yet implemented.');
    }
}
