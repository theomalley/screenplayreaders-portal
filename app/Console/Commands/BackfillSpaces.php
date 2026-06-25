<?php

namespace App\Console\Commands;

use App\Models\Assignment;
use App\Models\Budget\BudgetOrder;
use App\Models\ScriptRegistration;
use App\Services\GoogleDriveService;
use App\Services\GoogleDocsService;
use App\Services\SpacesStorageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class BackfillSpaces extends Command
{
    protected $signature = 'spaces:backfill
        {--type=all : budgets, certificates, coverage, scripts, or all}
        {--limit=100 : Max records to process per type}
        {--dry-run : Show what would be copied without copying}';

    protected $description = 'Copy existing finalized files from Google Drive to DO Spaces';

    public function handle(SpacesStorageService $spaces): int
    {
        $type = $this->option('type');
        $limit = (int) $this->option('limit');
        $dryRun = $this->option('dry-run');

        $types = $type === 'all'
            ? ['budgets', 'certificates', 'coverage', 'scripts', 'registration-scripts']
            : [$type];

        foreach ($types as $t) {
            match ($t) {
                'budgets' => $this->backfillBudgets($spaces, $limit, $dryRun),
                'certificates' => $this->backfillCertificates($spaces, $limit, $dryRun),
                'coverage' => $this->backfillCoverage($spaces, $limit, $dryRun),
                'scripts' => $this->backfillScripts($spaces, $limit, $dryRun),
                'registration-scripts' => $this->backfillRegistrationScripts($spaces, $limit, $dryRun),
                default => $this->error("Unknown type: {$t}"),
            };
        }

        return self::SUCCESS;
    }

    private function backfillBudgets(SpacesStorageService $spaces, int $limit, bool $dryRun): void
    {
        $orders = BudgetOrder::whereNotNull('drive_pdf_id')
            ->whereNull('spaces_pdf_path')
            ->limit($limit)
            ->get();

        $this->info("Budgets: {$orders->count()} to backfill");

        $docs = new GoogleDocsService();

        foreach ($orders as $order) {
            $orderId = $order->woo_order_id;
            $pdfPath = "budgets/{$orderId}/{$orderId}-budget.pdf";

            if ($dryRun) {
                $this->line("  [dry-run] {$orderId} → {$pdfPath}");
                continue;
            }

            try {
                $bytes = $docs->downloadDriveFileBytes($order->drive_pdf_id);
                $spaces->store($pdfPath, $bytes);

                $update = ['spaces_pdf_path' => $pdfPath];

                if ($order->drive_xlsx_id && ! $order->spaces_xlsx_path) {
                    $xlsxPath = "budgets/{$orderId}/{$orderId}-budget.xlsx";
                    $xlsxBytes = $docs->downloadDriveFileBytes($order->drive_xlsx_id);
                    $spaces->store($xlsxPath, $xlsxBytes, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                    $update['spaces_xlsx_path'] = $xlsxPath;
                }

                $order->update($update);
                $this->line("  OK {$orderId}");
            } catch (\Throwable $e) {
                $this->error("  FAIL {$orderId}: {$e->getMessage()}");
            }
        }
    }

    private function backfillCertificates(SpacesStorageService $spaces, int $limit, bool $dryRun): void
    {
        $regs = ScriptRegistration::whereNotNull('drive_certificate_pdf_id')
            ->whereNull('spaces_certificate_pdf_path')
            ->limit($limit)
            ->get();

        $this->info("Certificates: {$regs->count()} to backfill");

        $docs = new GoogleDocsService();

        foreach ($regs as $reg) {
            $path = "certificates/{$reg->registration_id}/{$reg->registration_id}-certificate.pdf";

            if ($dryRun) {
                $this->line("  [dry-run] {$reg->registration_id} → {$path}");
                continue;
            }

            try {
                $bytes = $docs->downloadDriveFileBytes($reg->drive_certificate_pdf_id);
                $spaces->store($path, $bytes);
                $reg->update(['spaces_certificate_pdf_path' => $path]);
                $this->line("  OK {$reg->registration_id}");
            } catch (\Throwable $e) {
                $this->error("  FAIL {$reg->registration_id}: {$e->getMessage()}");
            }
        }
    }

    private function backfillCoverage(SpacesStorageService $spaces, int $limit, bool $dryRun): void
    {
        $assignments = Assignment::whereNotNull('drive_coverage_pdf_id')
            ->whereNull('spaces_coverage_pdf_path')
            ->where('status', Assignment::STATUS_COMPLETED)
            ->limit($limit)
            ->get();

        $this->info("Coverage PDFs: {$assignments->count()} to backfill");

        $drive = new GoogleDriveService();

        foreach ($assignments as $a) {
            $path = "coverage/{$a->order_number}/{$a->id}-coverage.pdf";

            if ($dryRun) {
                $this->line("  [dry-run] #{$a->order_number} (id:{$a->id}) → {$path}");
                continue;
            }

            try {
                $bytes = $drive->downloadContents($a->drive_coverage_pdf_id);
                $spaces->store($path, $bytes);
                $a->update(['spaces_coverage_pdf_path' => $path]);
                $this->line("  OK #{$a->order_number} (id:{$a->id})");
            } catch (\Throwable $e) {
                $this->error("  FAIL #{$a->order_number} (id:{$a->id}): {$e->getMessage()}");
            }
        }
    }

    private function backfillScripts(SpacesStorageService $spaces, int $limit, bool $dryRun): void
    {
        $assignments = Assignment::whereNotNull('drive_script_file_id')
            ->where('drive_script_file_id', '!=', '__LOCAL_TEST__')
            ->whereNull('spaces_script_path')
            ->where('status', Assignment::STATUS_COMPLETED)
            ->limit($limit)
            ->get();

        $this->info("Scripts: {$assignments->count()} to backfill");

        $drive = new GoogleDriveService();

        foreach ($assignments as $a) {
            $path = "scripts/{$a->order_number}/{$a->id}-script.pdf";

            if ($dryRun) {
                $this->line("  [dry-run] #{$a->order_number} (id:{$a->id}) → {$path}");
                continue;
            }

            try {
                $bytes = $drive->downloadContents($a->drive_script_file_id);
                $spaces->store($path, $bytes);
                $a->update(['spaces_script_path' => $path]);
                $this->line("  OK #{$a->order_number} (id:{$a->id})");
            } catch (\Throwable $e) {
                $this->error("  FAIL #{$a->order_number} (id:{$a->id}): {$e->getMessage()}");
            }
        }
    }

    private function backfillRegistrationScripts(SpacesStorageService $spaces, int $limit, bool $dryRun): void
    {
        $regs = ScriptRegistration::whereNotNull('uploaded_file_url')
            ->where('uploaded_file_url', '!=', '')
            ->whereNull('spaces_script_file_path')
            ->limit($limit)
            ->get();

        $this->info("Registration scripts: {$regs->count()} to backfill");

        foreach ($regs as $reg) {
            $ext = pathinfo($reg->uploaded_file_name ?? $reg->uploaded_file_url, PATHINFO_EXTENSION) ?: 'pdf';
            $path = "registration-scripts/{$reg->registration_id}/{$reg->registration_id}.{$ext}";

            if ($dryRun) {
                $this->line("  [dry-run] {$reg->registration_id} → {$path}");
                continue;
            }

            try {
                $response = Http::timeout(60)->get($reg->uploaded_file_url);
                if (! $response->successful()) {
                    $this->error("  FAIL {$reg->registration_id}: HTTP {$response->status()} from {$reg->uploaded_file_url}");
                    continue;
                }

                $spaces->store($path, $response->body());
                $reg->update(['spaces_script_file_path' => $path]);
                $this->line("  OK {$reg->registration_id}");
            } catch (\Throwable $e) {
                $this->error("  FAIL {$reg->registration_id}: {$e->getMessage()}");
            }
        }
    }
}
