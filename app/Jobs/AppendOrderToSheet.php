<?php

// v1.1 — 2026-06-26 | Add registration + budget columns (W–AG) with document URLs.
// v1.0 — 2026-06-26 | Queued job: append a single order row to the Google Sheets order log.

namespace App\Jobs;

use App\Models\Budget\BudgetOrder;
use App\Models\ScriptRegistration;
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

        $d         = $this->orderData;
        $orderType = $this->deriveOrderType($d);
        $orderId   = $d['woocommerce_order_id'] ?? null;
        $orderNum  = $d['order_number'] ?? '';

        // Base order columns (A–V)
        $row = [
            $d['ordered_at']          ?? '',
            $orderType,
            $orderNum,
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

        // Registration columns (W–AB)
        $reg = $this->lookupRegistration($orderId, $orderNum);
        $row[] = $reg['registration_id']  ?? '';
        $row[] = $reg['variation_label']  ?? '';
        $row[] = $reg['author']           ?? '';
        $row[] = $reg['type_of_work']     ?? '';
        $row[] = $reg['expires_at']       ?? '';
        $row[] = $reg['certificate_url']  ?? '';

        // Budget columns (AC–AG)
        $bud = $this->lookupBudget($orderId);
        $row[] = $bud['budget_amount']    ?? '';
        $row[] = $bud['state']            ?? '';
        $row[] = $bud['guilds']           ?? '';
        $row[] = $bud['pdf_url']          ?? '';
        $row[] = $bud['xlsx_url']         ?? '';

        try {
            $sheets->spreadsheets_values->append(
                $spreadsheetId,
                'Sheet1!A:AG',
                new ValueRange(['values' => [$row]]),
                ['valueInputOption' => 'USER_ENTERED']
            );

            Log::info('AppendOrderToSheet: row appended', ['order_number' => $orderNum]);
        } catch (\Throwable $e) {
            Log::error('AppendOrderToSheet: failed', [
                'order_number' => $orderNum,
                'error'        => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function lookupRegistration($orderId, $orderNum): array
    {
        $reg = null;
        if ($orderNum) {
            $reg = ScriptRegistration::where('woo_order_number', $orderNum)->first();
        }
        if (! $reg && $orderId) {
            $reg = ScriptRegistration::where('woo_order_id', (string) $orderId)->first();
        }
        if (! $reg) return [];

        $author = trim(($reg->author_first ?? '') . ' ' . ($reg->author_last ?? ''));
        if ($reg->additional_authors && $reg->additional_authors !== 'None provided') {
            $author .= ', ' . $reg->additional_authors;
        }

        $certUrl = '';
        if ($reg->drive_certificate_pdf_id) {
            $certUrl = 'https://drive.google.com/file/d/' . $reg->drive_certificate_pdf_id . '/view';
        }

        return [
            'registration_id' => $reg->registration_id ?? '',
            'variation_label' => $reg->variation_label ?? '',
            'author'          => $author,
            'type_of_work'    => $reg->type_of_work ?? '',
            'expires_at'      => $reg->expires_at ? $reg->expires_at->format('Y-m-d') : 'Unlimited',
            'certificate_url' => $certUrl,
        ];
    }

    private function lookupBudget($orderId): array
    {
        if (! $orderId) return [];

        $bud = BudgetOrder::where('woo_order_id', (string) $orderId)->first();
        if (! $bud) return [];

        $guilds = collect([
            $bud->guild_wga      ? 'WGA'      : null,
            $bud->guild_dga      ? 'DGA'      : null,
            $bud->guild_sag      ? 'SAG'      : null,
            $bud->guild_iatse    ? 'IATSE'    : null,
            $bud->guild_teamsters ? 'Teamsters' : null,
        ])->filter()->implode(', ');

        $pdfUrl  = $bud->drive_pdf_id  ? 'https://drive.google.com/file/d/' . $bud->drive_pdf_id . '/view' : '';
        $xlsxUrl = $bud->drive_xlsx_id ? 'https://drive.google.com/file/d/' . $bud->drive_xlsx_id . '/view' : '';

        return [
            'budget_amount' => $bud->budget_amount ? number_format((float) $bud->budget_amount, 0) : '',
            'state'         => $bud->state ?? '',
            'guilds'        => $guilds,
            'pdf_url'       => $pdfUrl,
            'xlsx_url'      => $xlsxUrl,
        ];
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
