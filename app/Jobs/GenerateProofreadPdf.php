<?php

// v1.0 — 2026-06-19 | Queued job: download script PDF from Drive, overlay proofreading marks
//                     using FPDI, upload result to Drive as the proofread PDF.

namespace App\Jobs;

use App\Models\Assignment;
use App\Models\ProofreadingMark;
use App\Services\GoogleDriveService;
use App\Support\FilenameGenerator;
use App\Support\Pdf\ProofreadPdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GenerateProofreadPdf implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $assignmentId,
    ) {}

    public function handle(GoogleDriveService $drive): void
    {
        $assignment = Assignment::with(['assignedReader.readerProfile'])
            ->findOrFail($this->assignmentId);

        if (! $assignment->drive_script_file_id) {
            Log::error('GenerateProofreadPdf: no script file on Drive', ['assignment_id' => $this->assignmentId]);
            return;
        }

        $marks = ProofreadingMark::where('assignment_id', $assignment->id)
            ->orderBy('page_number')
            ->get()
            ->groupBy('page_number');

        if ($marks->isEmpty()) {
            Log::info('GenerateProofreadPdf: no marks to render', ['assignment_id' => $this->assignmentId]);
            return;
        }

        $scriptPath = $drive->downloadToTemp($assignment->drive_script_file_id);
        $flatPath   = $drive->flattenForFpdi($scriptPath);

        try {
            $pdf       = new ProofreadPdf();
            $pageCount = $pdf->setSourceFile($flatPath);

            for ($pageNum = 1; $pageNum <= $pageCount; $pageNum++) {
                $tpl  = $pdf->importPage($pageNum);
                $size = $pdf->getTemplateSize($tpl);
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($tpl, 0, 0, $size['width'], $size['height']);

                foreach ($marks->get($pageNum, collect()) as $mark) {
                    $this->drawMark($pdf, $mark, $size['width'], $size['height']);
                }
            }

            $outputPath = tempnam(sys_get_temp_dir(), 'sr_proof_') . '.pdf';
            $pdf->Output('F', $outputPath);

            $initials = $assignment->assignedReader?->readerProfile?->initials;
            $filename = FilenameGenerator::proofreadPdf($assignment, $initials);

            if ($assignment->drive_proofread_pdf_id) {
                $drive->replaceFile($assignment->drive_proofread_pdf_id, $outputPath);
            } else {
                $folderId = config('services.google.drive_coverage_folder_id');
                $fileId   = $this->uploadToFolder($drive, $outputPath, $filename, $folderId);
                $assignment->update(['drive_proofread_pdf_id' => $fileId]);
            }

            @unlink($outputPath);

            Log::info('GenerateProofreadPdf: complete', [
                'assignment_id' => $assignment->id,
                'pages'         => $pageCount,
                'marks'         => $marks->flatten()->count(),
            ]);
        } finally {
            @unlink($flatPath);
        }
    }

    private function drawMark(ProofreadPdf $pdf, ProofreadingMark $mark, float $pageW, float $pageH): void
    {
        $data = $mark->data;
        $pdf->SetDrawColor(255, 0, 0);
        $pdf->SetTextColor(255, 0, 0);

        match ($mark->type) {
            'strikethrough' => $this->drawStrikethrough($pdf, $data, $pageW, $pageH),
            'arrow'         => $this->drawArrow($pdf, $data, $pageW, $pageH),
            'note'          => $this->drawNote($pdf, $data, $pageW, $pageH),
            default         => null,
        };
    }

    private function drawStrikethrough(ProofreadPdf $pdf, array $data, float $pageW, float $pageH): void
    {
        $pdf->SetLineWidth(0.5);
        foreach ($data['rects'] ?? [] as $r) {
            $x    = $r['x'] * $pageW;
            $y    = $r['y'] * $pageH;
            $w    = ($r['w'] ?? $r['width'] ?? 0) * $pageW;
            $h    = ($r['h'] ?? $r['height'] ?? 0) * $pageH;
            $midY = $y + $h / 2;
            $pdf->Line($x, $midY, $x + $w, $midY);
        }

        if (! empty($data['correction'])) {
            $lastRect = end($data['rects']);
            if ($lastRect) {
                $cx = $lastRect['x'] * $pageW;
                $cy = $lastRect['y'] * $pageH + ($lastRect['h'] ?? $lastRect['height'] ?? 0) * $pageH + 2;
                $pdf->SetFont('Helvetica', 'B', 7);
                $text = @iconv('UTF-8', 'CP1252//TRANSLIT//IGNORE', $data['correction']) ?: $data['correction'];
                $pdf->Text($cx, $cy, $text);
            }
        }
    }

    private function drawArrow(ProofreadPdf $pdf, array $data, float $pageW, float $pageH): void
    {
        $x1 = $data['start']['x'] * $pageW;
        $y1 = $data['start']['y'] * $pageH;
        $x2 = $data['end']['x'] * $pageW;
        $y2 = $data['end']['y'] * $pageH;

        $pdf->SetLineWidth(0.7);
        $pdf->Line($x1, $y1, $x2, $y2);
        $pdf->drawArrowhead($x1, $y1, $x2, $y2, 3);
    }

    private function drawNote(ProofreadPdf $pdf, array $data, float $pageW, float $pageH): void
    {
        $x = $data['position']['x'] * $pageW;
        $y = $data['position']['y'] * $pageH;

        $pdf->SetFont('Helvetica', '', 8);
        $text = @iconv('UTF-8', 'CP1252//TRANSLIT//IGNORE', $data['text']) ?: $data['text'];
        $pdf->Text($x, $y, $text);
    }

    private function uploadToFolder(GoogleDriveService $drive, string $localPath, string $filename, string $folderId): string
    {
        $client = new \Google\Client();
        $client->useApplicationDefaultCredentials();
        $client->setSubject(config('services.google.impersonate_user'));
        $client->addScope(\Google\Service\Drive::DRIVE);
        $driveService = new \Google\Service\Drive($client);

        $file = $driveService->files->create(
            new \Google\Service\Drive\DriveFile([
                'name'    => $filename,
                'parents' => [$folderId],
            ]),
            [
                'data'              => file_get_contents($localPath),
                'mimeType'          => 'application/pdf',
                'uploadType'        => 'multipart',
                'fields'            => 'id',
                'supportsAllDrives' => true,
            ]
        );

        return $file->id;
    }
}
