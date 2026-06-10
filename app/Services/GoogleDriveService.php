<?php

// v1.7 — 2026-06-10 | Add watermarkPdf() — tiles a forensic watermark across every page and
//                     applies qpdf print/copy/edit restrictions for reader downloads.
// v1.6 — 2026-06-07 | Add unlockScript() — strips PDF encryption via qpdf (falls back to Ghostscript).
// v1.5 — 2026-05-28 | Preprocess PDFs with Ghostscript before FPDI to support compressed cross-reference streams.
// v1.4 — 2026-05-24 | Add deleteFile for assignment cleanup on destroy.
// v1.3 — 2026-05-22 | Remove public Drive permissions on upload; add downloadContents for portal proxy; revokePublicAccess on replace.
// v1.2 — 2026-05-21 | Add supportsAllDrives to all API calls — required for Shared Drive usage.
// v1.1 — 2026-05-19 | Full Drive implementation — upload script, view/download links, file replace.
//                     createCoverageDoc, exportDocToPdf, removeTitlePage stubbed for later phases.

namespace App\Services;

use App\Support\Pdf\WatermarkPdf;
use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Google\Service\Drive\Permission;
use setasign\Fpdi\Fpdi;

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
    public function uploadScript(string $orderNumber, string $localPath, string $fileName = 'script.pdf', string $mimeType = 'application/pdf'): string
    {
        $folderId = $this->ensureFolder(
            config('services.google.drive_scripts_folder_id'),
            $orderNumber
        );

        $file = $this->drive->files->create(
            new DriveFile(['name' => $fileName, 'parents' => [$folderId]]),
            [
                'data'              => file_get_contents($localPath),
                'mimeType'          => $mimeType,
                'uploadType'        => 'multipart',
                'fields'            => 'id',
                'supportsAllDrives' => true,
            ]
        );

        return $file->id;
    }

    /**
     * Replace the content of an existing Drive file in place (same file ID).
     * Also strips any public "anyone" permission left from a previous upload.
     */
    public function replaceFile(string $fileId, string $localPath, ?string $fileName = null): string
    {
        $this->drive->files->update(
            $fileId,
            new DriveFile($fileName ? ['name' => $fileName] : []),
            [
                'data'              => file_get_contents($localPath),
                'mimeType'          => 'application/pdf',
                'uploadType'        => 'multipart',
                'supportsAllDrives' => true,
            ]
        );

        $this->revokePublicAccess($fileId);

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
     * Download a Drive file and return its raw bytes.
     * Used by the portal script-proxy endpoint so the Drive file ID never reaches the reader's browser.
     */
    public function downloadContents(string $fileId): string
    {
        $response = $this->drive->files->get($fileId, [
            'alt'               => 'media',
            'supportsAllDrives' => true,
        ]);

        return $response->getBody()->getContents();
    }

    /**
     * Download a Drive file to a local temp path and return that path.
     */
    public function downloadToTemp(string $fileId): string
    {
        $response = $this->drive->files->get($fileId, [
            'alt'               => 'media',
            'supportsAllDrives' => true,
        ]);

        $tmp = tempnam(sys_get_temp_dir(), 'sr_pdf_') . '.pdf';
        file_put_contents($tmp, $response->getBody()->getContents());

        return $tmp;
    }

    /**
     * Re-render a PDF at compatibility level 1.4 using Ghostscript so FPDI's free
     * parser can read it. Modern PDFs use compressed cross-reference streams that
     * FPDI cannot parse without the paid add-on.
     */
    private function flattenForFpdi(string $sourcePath): string
    {
        $output   = tempnam(sys_get_temp_dir(), 'sr_flat_') . '.pdf';
        $exitCode = 0;

        exec(
            'gs -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -dCompatibilityLevel=1.4'
            . ' -sOutputFile=' . escapeshellarg($output)
            . ' ' . escapeshellarg($sourcePath)
            . ' 2>/dev/null',
            $ignored,
            $exitCode
        );

        @unlink($sourcePath);

        if ($exitCode !== 0 || !file_exists($output) || filesize($output) === 0) {
            @unlink($output);
            throw new \RuntimeException('Ghostscript preprocessing failed. Ensure gs is installed on the server.');
        }

        return $output;
    }

    /**
     * Strip encryption/restrictions from a Drive PDF and re-upload in place.
     * Tries qpdf --decrypt first (handles all owner-password PDFs cleanly);
     * falls back to Ghostscript if qpdf is unavailable.
     * Throws RuntimeException if neither tool can unlock the file.
     */
    public function unlockScript(string $fileId): void
    {
        $tmp    = $this->downloadToTemp($fileId);
        $output = tempnam(sys_get_temp_dir(), 'sr_unlock_') . '.pdf';

        // qpdf is the preferred tool — strips owner-password restrictions cleanly
        exec(
            'qpdf --decrypt ' . escapeshellarg($tmp) . ' ' . escapeshellarg($output) . ' 2>/dev/null',
            $ignored,
            $exitCode
        );

        // Ghostscript fallback — also strips encryption as a side-effect of rewriting
        if ($exitCode !== 0 || !file_exists($output) || filesize($output) === 0) {
            @unlink($output);
            $output = tempnam(sys_get_temp_dir(), 'sr_unlock_') . '.pdf';
            exec(
                'gs -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -dCompatibilityLevel=1.4'
                . ' -sOutputFile=' . escapeshellarg($output)
                . ' ' . escapeshellarg($tmp)
                . ' 2>/dev/null',
                $ignored,
                $exitCode
            );
        }

        @unlink($tmp);

        if ($exitCode !== 0 || !file_exists($output) || filesize($output) === 0) {
            @unlink($output);
            throw new \RuntimeException('Could not unlock the PDF. The file may be protected with an open/user password that we do not have.');
        }

        try {
            $this->replaceFile($fileId, $output);
        } finally {
            @unlink($output);
        }
    }

    /**
     * Delete specific pages from the Drive PDF, re-upload in place, return same file ID.
     * $pages is a 1-indexed array of page numbers to remove, e.g. [1] or [1, 103].
     */
    public function deletePages(string $fileId, array $pages): string
    {
        $source = $this->flattenForFpdi($this->downloadToTemp($fileId));

        try {
            $pdf       = new Fpdi();
            $pageCount = $pdf->setSourceFile($source);
            $keep      = array_diff(range(1, $pageCount), $pages);

            if (empty($keep)) {
                throw new \RuntimeException('Cannot delete all pages from the PDF.');
            }

            foreach ($keep as $pageNum) {
                $tpl = $pdf->importPage($pageNum);
                $size = $pdf->getTemplateSize($tpl);
                $pdf->AddPage($size['width'] > $size['height'] ? 'L' : 'P', [$size['width'], $size['height']]);
                $pdf->useTemplate($tpl);
            }

            $output = tempnam(sys_get_temp_dir(), 'sr_out_') . '.pdf';
            $pdf->Output('F', $output);
        } finally {
            @unlink($source);
        }

        try {
            $this->replaceFile($fileId, $output);
        } finally {
            @unlink($output);
        }

        return $fileId;
    }

    /**
     * Strip the first page from a Drive PDF, re-upload in place, return same file ID.
     */
    /**
     * Permanently delete a file or Google Doc from Drive.
     * Safe to call with a null/empty ID — does nothing.
     */
    public function deleteFile(?string $fileId): void
    {
        if (! $fileId) {
            return;
        }

        $this->drive->files->delete($fileId, ['supportsAllDrives' => true]);
    }

    public function removeTitlePage(string $fileId): string
    {
        return $this->deletePages($fileId, [1]);
    }

    /**
     * Delete specific pages from a local PDF file in place (no Drive involved).
     * Used for test-script assignments where drive_script_file_id === '__LOCAL_TEST__'.
     */
    public function deletePagesLocal(string $localPath, array $pages): void
    {
        // flattenForFpdi deletes its input (assumes temp file) — copy first to protect the original.
        $tmp = tempnam(sys_get_temp_dir(), 'sr_local_') . '.pdf';
        copy($localPath, $tmp);
        $source = $this->flattenForFpdi($tmp);

        try {
            $pdf       = new Fpdi();
            $pageCount = $pdf->setSourceFile($source);
            $keep      = array_diff(range(1, $pageCount), $pages);

            if (empty($keep)) {
                throw new \RuntimeException('Cannot delete all pages from the PDF.');
            }

            foreach ($keep as $pageNum) {
                $tpl  = $pdf->importPage($pageNum);
                $size = $pdf->getTemplateSize($tpl);
                $pdf->AddPage($size['width'] > $size['height'] ? 'L' : 'P', [$size['width'], $size['height']]);
                $pdf->useTemplate($tpl);
            }

            $pdf->Output('F', $localPath);
        } finally {
            @unlink($source);
        }
    }

    /**
     * Flatten a local PDF, tile a rotated forensic watermark across every page, then apply
     * qpdf permission restrictions (no print/copy/modify/annotate). Consumes (unlinks)
     * $localPath. Returns the path to the watermarked+restricted output; caller must unlink it.
     */
    public function watermarkPdf(string $localPath, string $watermarkText): string
    {
        $source = $this->flattenForFpdi($localPath);

        try {
            $pdf       = new WatermarkPdf();
            $pageCount = $pdf->setSourceFile($source);

            for ($pageNum = 1; $pageNum <= $pageCount; $pageNum++) {
                $tpl  = $pdf->importPage($pageNum);
                $size = $pdf->getTemplateSize($tpl);
                $pdf->AddPage($size['width'] > $size['height'] ? 'L' : 'P', [$size['width'], $size['height']]);
                $pdf->useTemplate($tpl);

                $pdf->SetFont('Helvetica', '', 16);
                $pdf->SetTextColor(190, 190, 190);

                $w = $size['width'];
                $h = $size['height'];
                foreach ([0.2, 0.5, 0.8] as $fraction) {
                    $pdf->rotatedText($w * 0.1, $h * $fraction, $watermarkText, 45);
                }
            }

            $watermarked = tempnam(sys_get_temp_dir(), 'sr_wm_') . '.pdf';
            $pdf->Output('F', $watermarked);
        } finally {
            @unlink($source);
        }

        $restricted   = tempnam(sys_get_temp_dir(), 'sr_wm_restricted_') . '.pdf';
        $ownerPassword = bin2hex(random_bytes(16));

        exec(
            'qpdf --encrypt "" ' . escapeshellarg($ownerPassword) . ' 256'
            . ' --print=none --modify=none --extract=n --annotate=n -- '
            . escapeshellarg($watermarked) . ' ' . escapeshellarg($restricted)
            . ' 2>/dev/null',
            $ignored,
            $exitCode
        );

        if ($exitCode !== 0 || !file_exists($restricted) || filesize($restricted) === 0) {
            @unlink($restricted);
            // qpdf unavailable/failed — fall back to the unrestricted watermarked copy.
            return $watermarked;
        }

        @unlink($watermarked);

        return $restricted;
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

    /**
     * Convert a local DOCX file to PDF using Drive's import/export API.
     * Uploads the DOCX as a Google Doc (Drive auto-converts), exports as PDF bytes,
     * deletes the temporary Google Doc, then writes the PDF to a local temp file.
     * Returns the absolute path to the temp PDF (caller must unlink when done).
     */
    public function convertDocxToPdf(string $localDocxPath): string
    {
        $doc = $this->drive->files->create(
            new DriveFile([
                'name'     => 'sr_tmp_' . uniqid(),
                'mimeType' => 'application/vnd.google-apps.document',
            ]),
            [
                'data'              => file_get_contents($localDocxPath),
                'mimeType'          => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'uploadType'        => 'multipart',
                'fields'            => 'id',
                'supportsAllDrives' => true,
            ]
        );

        $docId = $doc->id;

        try {
            $response = $this->drive->files->export($docId, 'application/pdf', ['alt' => 'media']);
            $pdfBytes = $response->getBody()->getContents();
        } finally {
            $this->drive->files->delete($docId, ['supportsAllDrives' => true]);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'sr_docx_') . '.pdf';
        file_put_contents($tmp, $pdfBytes);

        return $tmp;
    }

    // -------------------------------------------------------------------------

    /**
     * Revoke any "anyone with link" permission from a Drive file.
     * Called after replacing a file to strip public access that may have been set previously.
     */
    private function revokePublicAccess(string $fileId): void
    {
        $perms = $this->drive->permissions->listPermissions($fileId, [
            'fields'            => 'permissions(id,type)',
            'supportsAllDrives' => true,
        ]);

        foreach ($perms->getPermissions() as $perm) {
            if ($perm->getType() === 'anyone') {
                $this->drive->permissions->delete($fileId, $perm->getId(), [
                    'supportsAllDrives' => true,
                ]);
            }
        }
    }

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
        // copyRequiresWriterPermission cannot be set per-file on Shared Drives — manage at drive level instead.
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
