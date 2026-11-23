<?php

// v1.1 — 2026-11-23 | FIX: handle()'s catch block was marking the order STATUS_FAILED on
//                      every caught exception, including on a transient attempt that the
//                      queue worker was about to retry (tries=3, backoff=30) — an admin
//                      watching the list could see "Failed" and manually trigger
//                      regenerate() while the automatic retry was still pending, racing a
//                      duplicate concurrent run. Moved the status/error_message write out of
//                      catch (now just logs, still rethrows) and into failed(), which Laravel
//                      only calls once all retries are truly exhausted. Same shape as
//                      DownloadWherebyRecording's fix (commit b7e6081).
// v1.0 — 2026-06-21 | Initial: runs budget calculation engine on a pending BudgetOrder,
//                      stores the result payload, dispatches file generation job.

namespace App\Jobs;

use App\Models\Budget\BudgetOrder;
use App\Services\Budget\BudgetCalculationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessBudgetOrder implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public readonly int $budgetOrderId,
    ) {}

    public function handle(): void
    {
        $order = BudgetOrder::findOrFail($this->budgetOrderId);

        if ($order->status !== BudgetOrder::STATUS_PENDING) {
            Log::info('ProcessBudgetOrder: skipping, status is ' . $order->status, [
                'budget_order_id' => $order->id,
            ]);
            return;
        }

        $order->update(['status' => BudgetOrder::STATUS_PROCESSING]);

        try {
            $input = $order->form_input_data ?? [];

            $service = new BudgetCalculationService();
            $payload = $service->calculate($input);

            $order->update([
                'payload_json' => $payload,
            ]);

            Log::info('ProcessBudgetOrder: calculation complete, dispatching file generation', [
                'budget_order_id' => $order->id,
                'payload_keys' => count($payload),
            ]);

            GenerateBudgetFiles::dispatch($order->id);

        } catch (\Throwable $e) {
            Log::error('ProcessBudgetOrder: attempt failed', [
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
        Log::error('ProcessBudgetOrder: permanently failed after all retries', [
            'budget_order_id' => $this->budgetOrderId,
            'error' => $exception->getMessage(),
        ]);

        $order = BudgetOrder::find($this->budgetOrderId);
        if (! $order) {
            return;
        }

        if ($order->payload_json) {
            return; // a retry (or a concurrent attempt) actually completed before this failure signal landed
        }

        $order->update([
            'status' => BudgetOrder::STATUS_FAILED,
            'error_message' => $exception->getMessage(),
        ]);
    }
}
