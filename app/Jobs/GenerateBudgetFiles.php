<?php

// v1.1 — 2026-11-23 | FIX: handle()'s catch block was marking the order STATUS_FAILED on
//                      every caught exception, including on a transient attempt the queue
//                      worker was about to retry (tries=2, backoff=60) — same race as
//                      ProcessBudgetOrder's fix in this commit. Moved the status/error_message
//                      write out of catch (now just logs, still rethrows) and into failed(),
//                      called only once retries are truly exhausted.
// v1.0 — 2026-06-21 | Initial: generates Google Sheets copy with filled tokens,
//                      exports to PDF and XLSX, extracts topsheet if needed,
//                      stores Drive file IDs, dispatches email delivery.

namespace App\Jobs;

use App\Models\Budget\BudgetOrder;
use App\Services\Budget\BudgetFileService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

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
                'spaces_pdf_path' => $result['spaces_pdf_path'],
                'spaces_xlsx_path' => $result['spaces_xlsx_path'],
            ]);

            Log::info('GenerateBudgetFiles: files generated', [
                'budget_order_id' => $order->id,
                'pdf_id' => $result['pdf_id'],
                'xlsx_id' => $result['xlsx_id'],
                'topsheet_only' => $order->topsheet_only,
            ]);

            DeliverBudgetEmail::dispatch($order->id, $result);

        } catch (\Throwable $e) {
            Log::error('GenerateBudgetFiles: attempt failed', [
                'budget_order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Called by the queue worker once all retries are exhausted. Only now do we mark the
     * order as permanently failed — not on every per-attempt exception (see v1.1 changelog).
     */
    public function failed(Throwable $exception): void
    {
        Log::error('GenerateBudgetFiles: permanently failed after all retries', [
            'budget_order_id' => $this->budgetOrderId,
            'error' => $exception->getMessage(),
        ]);

        $order = BudgetOrder::find($this->budgetOrderId);
        if (! $order) {
            return;
        }

        if ($order->drive_pdf_id) {
            return; // a retry (or a concurrent attempt) actually completed before this failure signal landed
        }

        $order->update([
            'status' => BudgetOrder::STATUS_FAILED,
            'error_message' => 'File generation failed: ' . $exception->getMessage(),
        ]);
    }
}
