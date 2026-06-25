<?php

// v1.1 — 2026-06-21 | Download PDF/XLSX from Drive and attach to email
// v1.0 — 2026-06-21 | Initial: sends budget files to customer via MailerSend template email

namespace App\Jobs;

use App\Mail\BudgetDeliveryMail;
use App\Models\Budget\BudgetOrder;
use App\Services\Budget\BudgetFileService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class DeliverBudgetEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public readonly int $budgetOrderId,
        public readonly array $fileUrls,
    ) {}

    public function handle(BudgetFileService $fileService): void
    {
        $order = BudgetOrder::findOrFail($this->budgetOrderId);

        $header = $order->header_data ?? [];
        $title = $header['title'] ?? 'Budget';
        $safeTitle = preg_replace('/[^\w\s\-.]/', '', $title);

        $attachments = [];
        $tempFiles = [];

        try {
            // Download PDF from Drive
            if ($order->drive_pdf_id) {
                $pdfBytes = $fileService->downloadFileContents($order->drive_pdf_id);
                $pdfPath = tempnam(sys_get_temp_dir(), 'sr_budget_pdf_') . '.pdf';
                file_put_contents($pdfPath, $pdfBytes);
                $tempFiles[] = $pdfPath;
                $attachments[] = [
                    'path' => $pdfPath,
                    'as' => "{$order->woo_order_id} - SR Budget - {$safeTitle}.pdf",
                    'mime' => 'application/pdf',
                ];
            }

            // Download XLSX from Drive (if not topsheet-only)
            if ($order->drive_xlsx_id && !$order->topsheet_only) {
                $xlsxBytes = $fileService->downloadFileContents($order->drive_xlsx_id);
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
        } finally {
            foreach ($tempFiles as $tmp) {
                @unlink($tmp);
            }
        }
    }
}
