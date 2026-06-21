<?php

// v1.0 — 2026-06-21 | Initial: sends budget files to customer via MailerSend template email

namespace App\Jobs;

use App\Mail\BudgetDeliveryMail;
use App\Models\Budget\BudgetOrder;
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

    public function handle(): void
    {
        $order = BudgetOrder::findOrFail($this->budgetOrderId);

        Mail::send(new BudgetDeliveryMail($order, $this->fileUrls));

        $order->update([
            'status' => BudgetOrder::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        Log::info('DeliverBudgetEmail: sent', [
            'budget_order_id' => $order->id,
            'email' => $order->customer_email,
            'topsheet_only' => $order->topsheet_only,
        ]);
    }
}
