<?php

// v1.0 — 2026-06-26 | Queued job: append a single order row to the Google Sheets order log.

namespace App\Jobs;

use Google\Client;
use Google\Service\Sheets;
use Google\Service\Sheets\ValueRange;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class AppendOrderToSheet implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly array $orderData,
    ) {}

    public function handle(): void
    {
        $spreadsheetId = config('services.google.order_log_sheet_id');
        if (! $spreadsheetId) {
            Log::warning('AppendOrderToSheet: GOOGLE_ORDER_LOG_SHEET_ID not configured, skipping.');
            return;
        }

        $client = new Client();
        $client->useApplicationDefaultCredentials();
        $client->addScope(Sheets::SPREADSHEETS);
        $sheets = new Sheets($client);

        $d = $this->orderData;

        $row = [
            $d['ordered_at']          ?? '',
            $this->deriveOrderType($d),
            $d['order_number']        ?? '',
            $d['script_title']        ?? '',
            $d['services_purchased']  ?? '',
            $d['order_quantity']      ?? '',
            $d['order_total']         ?? 0,
            $d['cog_reader']          ?? 0,
            $d['cog_processing']      ?? 0,
            $d['cog_precommission']   ?? 0,
            $d['cog_commission']      ?? 0,
            $d['cog_total']           ?? 0,
            $d['net_revenue']         ?? 0,
            '',  // reader/invoice paid — blank at order time
            $d['payment_method']      ?? '',
            $d['coupon_code']         ?? '',
            $d['discount_amount']     ?? 0,
            $d['customer_name']       ?? '',
            $d['customer_address']    ?? '',
            $d['customer_email']      ?? '',
            $d['customer_phone']      ?? '',
        ];

        try {
            $sheets->spreadsheets_values->append(
                $spreadsheetId,
                'Sheet1!A:V',
                new ValueRange(['values' => [$row]]),
                ['valueInputOption' => 'USER_ENTERED']
            );

            Log::info('AppendOrderToSheet: row appended', ['order_number' => $d['order_number'] ?? '']);
        } catch (\Throwable $e) {
            Log::error('AppendOrderToSheet: failed', [
                'order_number' => $d['order_number'] ?? '',
                'error'        => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function deriveOrderType(array $d): string
    {
        $sku      = strtolower($d['sku'] ?? '');
        $services = strtolower($d['services_purchased'] ?? '');

        if (str_contains($sku, 'reg') || str_contains($services, 'registration')) return 'registration';
        if (str_contains($sku, 'budget') || str_contains($services, 'budget'))     return 'budget';
        if (str_contains($services, 'formatting'))                                  return 'formatting';
        if (str_contains($services, 'proofreading'))                                return 'proofreading';
        if (str_contains($services, 'consultation'))                                return 'consultation';

        return 'service';
    }
}
