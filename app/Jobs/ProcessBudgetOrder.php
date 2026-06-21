<?php

// v1.0 — 2026-06-21 | Initial: runs budget calculation engine on a pending BudgetOrder,
//                      stores the result payload, dispatches file generation job.

namespace App\Jobs;

use App\Models\Budget\BudgetOrder;
use App\Services\Budget\BudgetCalculationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

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
                'status' => BudgetOrder::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

            Log::info('ProcessBudgetOrder: calculation complete', [
                'budget_order_id' => $order->id,
                'payload_keys' => count($payload),
            ]);

            // Phase 5: dispatch GenerateBudgetFiles job here
            // GenerateBudgetFiles::dispatch($order->id);

        } catch (\Throwable $e) {
            $order->update([
                'status' => BudgetOrder::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);

            Log::error('ProcessBudgetOrder: failed', [
                'budget_order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
