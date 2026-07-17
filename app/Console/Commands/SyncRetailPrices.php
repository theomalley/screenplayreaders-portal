<?php

// v1.0 — 2026-07-17 | Hourly refresh of the Ratebook page's WooCommerce-linked retail prices.

namespace App\Console\Commands;

use App\Services\RetailPriceService;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use RuntimeException;

class SyncRetailPrices extends Command
{
    protected $signature = 'retail-prices:sync';

    protected $description = 'Refresh the cached WooCommerce retail prices shown on the Ratebook page.';

    public function handle(): int
    {
        try {
            RetailPriceService::refresh();
        } catch (RuntimeException|ConnectionException $e) {
            $this->warn("Retail price sync failed, keeping previously cached prices: {$e->getMessage()}");

            return Command::SUCCESS;
        }

        $this->info('Retail prices synced from WooCommerce.');

        return Command::SUCCESS;
    }
}
