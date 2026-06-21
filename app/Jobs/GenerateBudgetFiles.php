<?php

// v1.0 — 2026-06-21 | Initial: generates Google Sheets copy with filled tokens,
//                      exports to PDF and XLSX, extracts topsheet if needed,
//                      stores Drive file IDs, dispatches email delivery.

namespace App\Jobs;

use App\Models\Budget\BudgetOrder;
use App\Services\Budget\BudgetFileService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GenerateBudgetFiles implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;
    public int $backoff = 60;
    public int $timeout = 300;

    public function __construct(
        public readonly int $budgetOrderId,
    ) {}

    public function handle(BudgetFileService $fileService): void
    {
        $order = BudgetOrder::findOrFail($this->budgetOrderId);

        if (! $order->payload_json) {
            Log::warning('GenerateBudgetFiles: no payload_json, skipping', [
                'budget_order_id' => $order->id,
            ]);
            return;
        }

        try {
            $result = $fileService->generate($order);

            $order->update([
                'drive_spreadsheet_id' => $result['spreadsheet_id'],
                'drive_pdf_id' => $result['pdf_id'],
                'drive_xlsx_id' => $result['xlsx_id'],
            ]);

            Log::info('GenerateBudgetFiles: files generated', [
                'budget_order_id' => $order->id,
                'pdf_id' => $result['pdf_id'],
                'xlsx_id' => $result['xlsx_id'],
                'topsheet_only' => $order->topsheet_only,
            ]);

            DeliverBudgetEmail::dispatch($order->id, $result);

        } catch (\Throwable $e) {
            $order->update([
                'status' => BudgetOrder::STATUS_FAILED,
                'error_message' => 'File generation failed: ' . $e->getMessage(),
            ]);

            Log::error('GenerateBudgetFiles: failed', [
                'budget_order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
