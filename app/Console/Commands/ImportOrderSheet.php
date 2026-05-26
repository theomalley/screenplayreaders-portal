<?php

// v1.0 — 2026-05-26 | One-time import of historical Google Sheet order data into order_revenues

namespace App\Console\Commands;

use App\Models\OrderRevenue;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ImportOrderSheet extends Command
{
    protected $signature = 'import:orders {file : Path to the exported CSV file}
                                          {--dry-run : Preview rows without writing to DB}';

    protected $description = 'Import historical order data from a Google Sheet CSV export';

    // Header → DB column mapping (case-insensitive, trimmed)
    private array $MAP = [
        'orderdate'        => 'ordered_at',
        'number'           => 'order_number',
        'assignmenttitle'  => 'script_title',
        'items ordered'    => 'services_purchased',
        'quantities'       => 'order_quantity',
        'ordertotal'       => 'order_total',
        'cogreader'        => 'cog_reader',
        'cogprocessing'    => 'cog_processing',
        'cogprecommission' => 'cog_precommission',
        'cogcommission'    => 'cog_commission',
        'cogtotal'         => 'cog_total',
        'netrevenue'       => 'net_revenue',
        'payment method'   => 'payment_method',
        'coupons used'     => 'coupon_code',
        'discount amount'  => 'discount_amount',
        'customer name'    => 'customer_name',
        'customer address' => 'customer_address',
        'customer email'   => 'customer_email',
        'customer phone'   => 'customer_phone',
    ];

    // Columns whose values are parsed as money (strip $, commas, parentheses)
    private array $MONEY = [
        'order_total', 'cog_reader', 'cog_processing', 'cog_precommission',
        'cog_commission', 'cog_total', 'net_revenue', 'discount_amount',
    ];

    public function handle(): int
    {
        $path = $this->argument('file');
        $dryRun = $this->option('dry-run');

        if (! file_exists($path)) {
            $this->error("File not found: {$path}");
            return 1;
        }

        $handle = fopen($path, 'r');
        if (! $handle) {
            $this->error("Cannot open file: {$path}");
            return 1;
        }

        // Read and normalize header row
        $rawHeaders = fgetcsv($handle);
        if (! $rawHeaders) {
            $this->error('CSV appears to be empty.');
            fclose($handle);
            return 1;
        }

        $headers = array_map(fn($h) => strtolower(trim($h)), $rawHeaders);

        $inserted = 0;
        $updated  = 0;
        $skipped  = 0;
        $row      = 1;

        while (($raw = fgetcsv($handle)) !== false) {
            $row++;

            // Build associative array from this row using normalized headers
            $csv = [];
            foreach ($headers as $i => $h) {
                $csv[$h] = isset($raw[$i]) ? trim($raw[$i]) : '';
            }

            // Map to DB columns
            $data = [];
            foreach ($this->MAP as $csvKey => $dbCol) {
                $val = $csv[$csvKey] ?? '';
                $data[$dbCol] = $val === '' ? null : $val;
            }

            // Also use services_purchased as ticket_summary if nothing else
            if (! empty($data['services_purchased'])) {
                $data['ticket_summary'] = $data['services_purchased'];
            }

            // Skip rows with no order number
            $orderNumber = $data['order_number'] ?? '';
            if (empty($orderNumber)) {
                $this->line("  Row {$row}: skipped (no order number)");
                $skipped++;
                continue;
            }

            // Parse date
            if (! empty($data['ordered_at'])) {
                $data['ordered_at'] = $this->parseDate($data['ordered_at']);
                if (! $data['ordered_at']) {
                    $this->warn("  Row {$row} [{$orderNumber}]: could not parse date '{$csv['orderdate']}', leaving null");
                    $data['ordered_at'] = null;
                }
            }

            // Parse money fields
            foreach ($this->MONEY as $col) {
                if (isset($data[$col])) {
                    $data[$col] = $this->parseMoney($data[$col]);
                }
            }

            // Parse quantity
            if (isset($data['order_quantity'])) {
                $data['order_quantity'] = is_numeric($data['order_quantity'])
                    ? (int) $data['order_quantity']
                    : null;
            }

            // Null out empty strings after all parsing
            foreach ($data as $k => $v) {
                if ($v === '') $data[$k] = null;
            }

            if ($dryRun) {
                $this->line("  Row {$row} [{$orderNumber}]: " . json_encode($data, JSON_UNESCAPED_UNICODE));
                continue;
            }

            $exists = OrderRevenue::where('order_number', $orderNumber)->exists();

            OrderRevenue::updateOrCreate(
                ['order_number' => $orderNumber],
                $data
            );

            if ($exists) {
                $updated++;
                $this->line("  Row {$row} [{$orderNumber}]: updated");
            } else {
                $inserted++;
                $this->line("  Row {$row} [{$orderNumber}]: inserted");
            }
        }

        fclose($handle);

        if ($dryRun) {
            $this->info('Dry run complete — no rows written.');
        } else {
            $this->info("Done. Inserted: {$inserted} | Updated: {$updated} | Skipped: {$skipped}");
        }

        return 0;
    }

    private function parseDate(string $val): ?string
    {
        if (empty($val)) return null;

        // Try common formats
        $formats = ['m/d/Y', 'Y-m-d', 'd/m/Y', 'm-d-Y', 'Y/m/d', 'n/j/Y', 'n/j/y'];
        foreach ($formats as $fmt) {
            try {
                $dt = Carbon::createFromFormat($fmt, $val);
                if ($dt) return $dt->format('Y-m-d H:i:s');
            } catch (\Exception) {}
        }

        // Fall back to Carbon's flexible parser
        try {
            return Carbon::parse($val)->format('Y-m-d H:i:s');
        } catch (\Exception) {
            return null;
        }
    }

    private function parseMoney(mixed $val): ?float
    {
        if ($val === null || $val === '') return null;

        $str = (string) $val;
        // Strip $, commas, spaces; convert (1.23) → -1.23
        $negative = preg_match('/^\(.*\)$/', trim($str));
        $str = preg_replace('/[^0-9.\-]/', '', $str);
        if (! is_numeric($str)) return null;

        $num = (float) $str;
        return $negative ? -abs($num) : $num;
    }
}
