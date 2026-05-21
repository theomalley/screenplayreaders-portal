<?php

// v1.2 — 2026-05-21 | Add supportsAllDrives to all API calls — required for Shared Drive usage.
// v1.1 — 2026-05-19 | Full Drive implementation — upload script, view/download links, file replace.
//                     createCoverageDoc, exportDocToPdf, removeTitlePage stubbed for later phases.

namespace App\Services;

use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Google\Service\Drive\Permission;

class GoogleDriveService
{
    private Drive $drive;

    public function __construct()
    {
        $client = new Client();
        // Reads GOOGLE_APPLICATION_CREDENTIALS env var automatically
        $client->useApplicationDefaultCredentials();
        $client->addScope(Drive::DRIVE);
        $this->drive = new Drive($client);
    }

    /**
     * Upload a script PDF into scripts/{assignmentId}/ and return the Drive file ID.
     * The file is set view-only (anyone with link, no download/print).
     */
    public function uploadScript(int $assignmentId, string $localPath): string
    {
        $folderId = $this->ensureFolder(
            config('services.google.drive_scripts_folder_id'),
            (string) $assignmentId
        );

        $file = $this->drive->files->create(
            new DriveFile(['name' => 'script.pdf', 'parents' => [$folderId]]),
            [
                'data'             => file_get_contents($localPath),
                'mimeType'         => 'application/pdf',
                'uploadType'       => 'multipart',
                'fields'           => 'id',
                'supportsAllDrives' => true,
            ]
        );

        $this->setViewOnly($file->id);

        return $file->id;
    }

    /**
     * Replace the content of an existing Drive file in place (same file ID, same sharing).
     * Used when an admin removes a title page and re-uploads.
     */
    public function replaceFile(string $fileId, string $localPath): string
    {
        $this->drive->files->update(
            $fileId,
            new DriveFile(),
            [
                'data'              => file_get_contents($localPath),
                'mimeType'          => 'application/pdf',
                'uploadType'        => 'multipart',
                'supportsAllDrives' => true,
            ]
        );

        return $fileId;
    }

    /**
     * Iframe-embeddable view-only URL (no download button shown in the Drive viewer).
     * Use this for reader-facing script display.
     */
    public function viewLink(string $fileId): string
    {
        return "https://drive.google.com/file/d/{$fileId}/preview";
    }

    /**
     * Direct download URL — only surface this in admin/editor UI.
     */
    public function downloadUrl(string $fileId): string
    {
        return "https://drive.google.com/uc?export=download&id={$fileId}";
    }

    /**
     * Strip the first page from a Drive PDF, re-upload in place, return same file ID.
     * Requires a local PDF manipulation library (e.g. spatie/pdf-to-image + Imagick).
     */
    public function removeTitlePage(string $_fileId, string $_localPath): string
    {
        throw new \RuntimeException('removeTitlePage not yet implemented.');
    }

    /**
     * Create a Google Doc from formatted coverage HTML and return the Doc file ID.
     * Called by the coverage submission job after a reader submits.
     */
    public function createCoverageDoc(int $_assignmentId, string $_htmlContent): string
    {
        throw new \RuntimeException('createCoverageDoc not yet implemented.');
    }

    /**
     * Export an existing Google Doc to PDF, save to Drive, return the PDF file ID.
     */
    public function exportDocToPdf(string $_docFileId, int $_assignmentId): string
    {
        throw new \RuntimeException('exportDocToPdf not yet implemented.');
    }

    // -------------------------------------------------------------------------

    /**
     * Set a file to "anyone with link = viewer" and prevent viewers from downloading or printing.
     */
    private function setViewOnly(string $fileId): void
    {
        $this->drive->permissions->create(
            $fileId,
            new Permission(['type' => 'anyone', 'role' => 'reader']),
            ['fields' => 'id', 'supportsAllDrives' => true]
        );

        // copyRequiresWriterPermission blocks the download/print options in Drive UI
        $this->drive->files->update($fileId, new DriveFile([
            'copyRequiresWriterPermission' => true,
        ]), ['supportsAllDrives' => true]);
    }

    /**
     * Find or create a subfolder named $name inside $parentId. Returns the folder ID.
     */
    private function ensureFolder(string $parentId, string $name): string
    {
        $results = $this->drive->files->listFiles([
            'q'                         => "name='{$name}' and '{$parentId}' in parents"
                                         . " and mimeType='application/vnd.google-apps.folder' and trashed=false",
            'fields'                    => 'files(id)',
            'includeItemsFromAllDrives' => true,
            'supportsAllDrives'         => true,
        ]);

        if (!empty($results->files)) {
            return $results->files[0]->id;
        }

        $folder = $this->drive->files->create(
            new DriveFile([
                'name'     => $name,
                'mimeType' => 'application/vnd.google-apps.folder',
                'parents'  => [$parentId],
            ]),
            ['fields' => 'id', 'supportsAllDrives' => true]
        );

        return $folder->id;
    }
}
