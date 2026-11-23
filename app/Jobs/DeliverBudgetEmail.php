<?php

// v1.2 — 2026-11-23 | FIX: the catch block below (added in v1.1's sibling fix) was marking
//                      the order STATUS_FAILED on every caught exception, including on a
//                      transient attempt the queue worker was about to retry (tries=3,
//                      backoff=60) — same race as ProcessBudgetOrder/GenerateBudgetFiles'
//                      fix in this commit. Moved the status/error_message write out of catch
//                      (now just logs, still rethrows) and into failed(), called only once
//                      retries are truly exhausted — preserves the original v1.1 intent (never
//                      leave the order silently stuck) without the premature-FAILED race.
// v1.1 — 2026-06-21 | Download PDF/XLSX from Drive and attach to email
// v1.0 — 2026-06-21 | Initial: sends budget files to customer via MailerSend template email

namespace App\Jobs;

use App\Mail\BudgetDeliveryMail;
use App\Models\Budget\BudgetOrder;
use App\Services\Budget\BudgetFileService;
use App\Services\SpacesStorageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class DeliverBudgetEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public readonly int $budgetOrderId,
        public readonly array $fileUrls,
    ) {}

    public function handle(BudgetFileService $fileService, SpacesStorageService $spaces): void
    {
        $order = BudgetOrder::findOrFail($this->budgetOrderId);

        $header = $order->header_data ?? [];
        $title = $header['title'] ?? 'Budget';
        $safeTitle = preg_replace('/[^\w\s\-.]/', '', $title);

        $attachments = [];
        $tempFiles = [];

        try {
            if ($order->drive_pdf_id) {
                $pdfBytes = $order->spaces_pdf_path
                    ? $spaces->get($order->spaces_pdf_path)
                    : $fileService->downloadFileContents($order->drive_pdf_id);
                $pdfPath = tempnam(sys_get_temp_dir(), 'sr_budget_pdf_') . '.pdf';
                file_put_contents($pdfPath, $pdfBytes);
                $tempFiles[] = $pdfPath;
                $attachments[] = [
                    'path' => $pdfPath,
                    'as' => "{$order->woo_order_id} - SR Budget - {$safeTitle}.pdf",
                    'mime' => 'application/pdf',
                ];
            }

            if ($order->drive_xlsx_id && !$order->topsheet_only) {
                $xlsxBytes = $order->spaces_xlsx_path
                    ? $spaces->get($order->spaces_xlsx_path)
                    : $fileService->downloadFileContents($order->drive_xlsx_id);
                $xlsxPath = tempnam(sys_get_temp_dir(), 'sr_budget_xlsx_') . '.xlsx';
                file_put_contents($xlsxPath, $xlsxBytes);
                $tempFiles[] = $xlsxPath;
                $attachments[] = [
                    'path' => $xlsxPath,
                    'as' => "{$order->woo_order_id} - SR Budget - {$safeTitle}.xlsx",
                    'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ];
            }

            Mail::send(new BudgetDeliveryMail($order, $this->fileUrls, budgetFiles: $attachments));

            $order->update([
                'status' => BudgetOrder::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

            Log::info('DeliverBudgetEmail: sent with attachments', [
                'budget_order_id' => $order->id,
                'email' => $order->customer_email,
                'attachments' => count($attachments),
            ]);
        } catch (\Throwable $e) {
            Log::error('DeliverBudgetEmail: attempt failed', [
                'budget_order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            foreach ($tempFiles as $tmp) {
                @unlink($tmp);
            }
        }
    }

    /**
     * Called by the queue worker once all retries are exhausted. Only now do we mark the
     * order as permanently failed — not on every per-attempt exception (see v1.2 changelog).
     * Without this, a permanent mail failure (bad address, provider outage past retries)
     * would leave the order silently stuck at STATUS_PROCESSING forever (the original v1.1 fix).
     */
    public function failed(Throwable $exception): void
    {
        Log::error('DeliverBudgetEmail: permanently failed after all retries', [
            'budget_order_id' => $this->budgetOrderId,
            'error' => $exception->getMessage(),
        ]);

        $order = BudgetOrder::find($this->budgetOrderId);
        if (! $order) {
            return;
        }

        if ($order->status === BudgetOrder::STATUS_COMPLETED) {
            return; // a retry (or a concurrent attempt) actually completed before this failure signal landed
        }

        $order->update([
            'status' => BudgetOrder::STATUS_FAILED,
            'error_message' => 'Email delivery failed: ' . $exception->getMessage(),
        ]);
    }
}
