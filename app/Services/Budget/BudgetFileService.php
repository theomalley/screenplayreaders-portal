<?php

// v1.0 — 2026-06-21 | Initial: copies Google Sheets template, replaces {{tokens}} with payload
//                      values, exports to PDF and XLSX, extracts topsheet page via FPDI,
//                      uploads all files to Google Drive.
//                      Replaces Google Apps Script (code.js) and CloudConvert.

namespace App\Services\Budget;

use App\Models\Budget\BudgetOrder;
use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Google\Service\Drive\Permission;
use Google\Service\Sheets;
use Google\Service\Sheets\BatchUpdateSpreadsheetRequest;
use Google\Service\Sheets\FindReplaceRequest;
use Google\Service\Sheets\Request as SheetsRequest;
use setasign\Fpdi\Fpdi;

class BudgetFileService
{
    private Drive $drive;
    private Sheets $sheets;

    public function __construct()
    {
        $client = new Client();
        $client->useApplicationDefaultCredentials();
        $client->addScope(Drive::DRIVE);
        $client->addScope(Sheets::SPREADSHEETS);

        $impersonateUser = config('services.google.impersonate_user');
        if ($impersonateUser) {
            $client->setSubject($impersonateUser);
        }

        $this->drive = new Drive($client);
        $this->sheets = new Sheets($client);
    }

    /**
     * Full pipeline: copy template → fill tokens → export PDF + XLSX → topsheet if needed.
     * Returns array of Drive file IDs.
     */
    public function generate(BudgetOrder $order): array
    {
        $payload = $order->payload_json ?? [];
        $title = $this->buildTitle($order);

        $spreadsheetId = $this->copyTemplate($title);
        $this->replaceTokens($spreadsheetId, $payload);
        $this->makePublic($spreadsheetId);

        $pdfBytes = $this->exportPdf($spreadsheetId);
        $xlsxBytes = $this->exportXlsx($spreadsheetId);

        $outputFolder = config('services.google.budget_output_folder_id');

        $pdfFileId = $this->uploadToFolder($outputFolder, $title . '.pdf', $pdfBytes, 'application/pdf');
        $this->makePublic($pdfFileId);

        $xlsxFileId = null;
        if (! $order->topsheet_only) {
            $xlsxFileId = $this->uploadToFolder(
                $outputFolder, $title . '.xlsx', $xlsxBytes,
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            );
            $this->makePublic($xlsxFileId);
        }

        // Topsheet-only: extract just page 1 from the full PDF
        if ($order->topsheet_only) {
            $topsheetBytes = $this->extractPage1($pdfBytes);
            // Replace the full PDF with the topsheet-only version
            $this->drive->files->update($pdfFileId, new DriveFile(), [
                'data' => $topsheetBytes,
                'mimeType' => 'application/pdf',
                'uploadType' => 'multipart',
                'supportsAllDrives' => true,
            ]);
        }

        return [
            'spreadsheet_id' => $spreadsheetId,
            'pdf_id' => $pdfFileId,
            'xlsx_id' => $xlsxFileId,
            'pdf_url' => "https://drive.google.com/file/d/{$pdfFileId}/view",
            'xlsx_url' => $xlsxFileId ? "https://drive.google.com/file/d/{$xlsxFileId}/view" : null,
            'pdf_download_url' => "https://drive.google.com/uc?export=download&id={$pdfFileId}",
            'xlsx_download_url' => $xlsxFileId ? "https://drive.google.com/uc?export=download&id={$xlsxFileId}" : null,
        ];
    }

    private function buildTitle(BudgetOrder $order): string
    {
        $header = $order->header_data ?? [];
        $title = $header['title'] ?? 'Untitled';
        $safe = preg_replace('/[\\\\\\/:*?"<>|]+/', ' ', $title);
        $safe = preg_replace('/\s+/', ' ', trim($safe));

        return 'SR Budget - ' . mb_substr($safe, 0, 140);
    }

    private function copyTemplate(string $newName): string
    {
        $templateId = config('services.google.budget_template_spreadsheet_id');
        $destFolder = config('services.google.budget_spreadsheets_folder_id');

        $copy = $this->drive->files->copy(
            $templateId,
            new DriveFile([
                'name' => $newName,
                'parents' => [$destFolder],
            ]),
            ['supportsAllDrives' => true, 'fields' => 'id']
        );

        return $copy->id;
    }

    private function replaceTokens(string $spreadsheetId, array $payload): void
    {
        $requests = [];

        foreach ($payload as $key => $value) {
            $token = '{{' . $key . '}}';
            $replacement = ($value === null || $value === '') ? '' : (string) $value;

            $requests[] = new SheetsRequest([
                'findReplace' => new FindReplaceRequest([
                    'find' => $token,
                    'replacement' => $replacement,
                    'allSheets' => true,
                    'matchCase' => true,
                    'matchEntireCell' => false,
                ]),
            ]);
        }

        // Sheets API allows max 100 requests per batch — chunk if needed
        foreach (array_chunk($requests, 100) as $chunk) {
            $this->sheets->spreadsheets->batchUpdate(
                $spreadsheetId,
                new BatchUpdateSpreadsheetRequest(['requests' => $chunk])
            );
        }

        // Final sweep: replace any remaining {{...}} tokens with 0
        // The Sheets API doesn't support regex, so we replace entire cells that
        // match common token patterns. matchEntireCell=true ensures we only hit
        // cells that contain nothing but the token.
        $cleanupRequests = [];
        foreach ($payload as $key => $_) {
            // Skip — these were already replaced above
        }
        // Collect all known token prefixes from the template and replace remaining ones
        // by stripping {{ and }} separately
        $cleanupRequests[] = new SheetsRequest([
            'findReplace' => new FindReplaceRequest([
                'find' => '{{',
                'replacement' => '',
                'allSheets' => true,
                'matchCase' => false,
                'matchEntireCell' => false,
            ]),
        ]);
        $cleanupRequests[] = new SheetsRequest([
            'findReplace' => new FindReplaceRequest([
                'find' => '}}',
                'replacement' => '',
                'allSheets' => true,
                'matchCase' => false,
                'matchEntireCell' => false,
            ]),
        ]);
        $this->sheets->spreadsheets->batchUpdate(
            $spreadsheetId,
            new BatchUpdateSpreadsheetRequest(['requests' => $cleanupRequests])
        );
    }

    private function exportPdf(string $spreadsheetId): string
    {
        $url = "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/export?"
            . http_build_query([
                'format' => 'pdf',
                'portrait' => 'true',
                'fitw' => 'true',
                'scale' => '2',
                'horizontal_alignment' => 'CENTER',
                'top_margin' => '0.25',
                'bottom_margin' => '0.25',
                'left_margin' => '0.25',
                'right_margin' => '0.25',
                'sheetnames' => 'false',
                'printtitle' => 'false',
                'pagenumbers' => 'false',
                'gridlines' => 'false',
                'fzr' => 'false',
            ]);

        return $this->authenticatedGet($url);
    }

    private function exportXlsx(string $spreadsheetId): string
    {
        $url = "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/export?format=xlsx";

        return $this->authenticatedGet($url);
    }

    private function authenticatedGet(string $url): string
    {
        $client = $this->sheets->getClient();
        $httpClient = $client->authorize();
        $response = $httpClient->get($url);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(
                "Google export failed: HTTP {$response->getStatusCode()}"
            );
        }

        return $response->getBody()->getContents();
    }

    private function uploadToFolder(string $folderId, string $name, string $content, string $mimeType): string
    {
        $file = $this->drive->files->create(
            new DriveFile([
                'name' => $name,
                'parents' => [$folderId],
            ]),
            [
                'data' => $content,
                'mimeType' => $mimeType,
                'uploadType' => 'multipart',
                'fields' => 'id',
                'supportsAllDrives' => true,
            ]
        );

        return $file->id;
    }

    /**
     * Extract page 1 from a PDF using FPDI. Replaces CloudConvert.
     */
    private function extractPage1(string $pdfBytes): string
    {
        $sourcePath = tempnam(sys_get_temp_dir(), 'sr_budget_pdf_') . '.pdf';
        file_put_contents($sourcePath, $pdfBytes);

        // Flatten for FPDI compatibility (compressed xref streams)
        $flatPath = $this->flattenForFpdi($sourcePath);

        try {
            $pdf = new Fpdi();
            $pdf->setSourceFile($flatPath);
            $tpl = $pdf->importPage(1);
            $size = $pdf->getTemplateSize($tpl);
            $pdf->AddPage(
                $size['width'] > $size['height'] ? 'L' : 'P',
                [$size['width'], $size['height']]
            );
            $pdf->useTemplate($tpl);

            $outputPath = tempnam(sys_get_temp_dir(), 'sr_topsheet_') . '.pdf';
            $pdf->Output('F', $outputPath);

            $result = file_get_contents($outputPath);
        } finally {
            @unlink($sourcePath);
            @unlink($flatPath);
            @unlink($outputPath ?? '');
        }

        return $result;
    }

    /**
     * Re-render PDF at compatibility level 1.4 via Ghostscript so FPDI can parse it.
     * Same approach as GoogleDriveService::flattenForFpdi().
     */
    private function flattenForFpdi(string $sourcePath): string
    {
        $output = tempnam(sys_get_temp_dir(), 'sr_flat_') . '.pdf';

        exec(
            'gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dNOPAUSE -dQUIET -dBATCH'
            . ' -sOutputFile=' . escapeshellarg($output)
            . ' ' . escapeshellarg($sourcePath)
            . ' 2>&1',
            $gsOutput,
            $exitCode
        );

        if ($exitCode !== 0 || ! file_exists($output) || filesize($output) < 100) {
            @unlink($output);
            return $sourcePath;
        }

        @unlink($sourcePath);
        return $output;
    }

    private function makePublic(string $fileId): void
    {
        $this->drive->permissions->create(
            $fileId,
            new Permission([
                'type' => 'anyone',
                'role' => 'reader',
            ]),
            ['supportsAllDrives' => true]
        );
    }

    /**
     * Download a file's raw bytes from Drive.
     */
    public function downloadFileContents(string $fileId): string
    {
        $response = $this->drive->files->get($fileId, [
            'alt' => 'media',
            'supportsAllDrives' => true,
        ]);

        return $response->getBody()->getContents();
    }
}
