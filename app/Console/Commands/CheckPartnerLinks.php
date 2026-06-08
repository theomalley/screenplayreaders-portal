<?php

// v1.0 — 2026-06-08 | Check all partner sites that are due for a backlink check

namespace App\Console\Commands;

use App\Http\Controllers\Marketing\PartnerSiteController;
use App\Models\PartnerSite;
use Illuminate\Console\Command;

class CheckPartnerLinks extends Command
{
    protected $signature   = 'marketing:check-partner-links {--site= : Only check this partner_site ID}';
    protected $description = 'Fetch partner site URLs and record whether they link back to screenplayreaders.com';

    public function handle(): int
    {
        $query = PartnerSite::where('active', true)
            ->where(fn($q) => $q->whereNull('next_check_at')->orWhere('next_check_at', '<=', now()));

        if ($id = $this->option('site')) {
            $query->where('id', (int) $id);
        }

        $sites = $query->get();

        if ($sites->isEmpty()) {
            $this->line('No partner sites due for a check.');
            return self::SUCCESS;
        }

        foreach ($sites as $site) {
            $this->line("Checking: {$site->name} ({$site->url})");
            $result = PartnerSiteController::runCheck($site);

            $status = $result['is_up'] ? '✓ UP' : '✗ DOWN';
            $links  = count($result['links_found'] ?? []);
            $this->line("  {$status} — {$links} link(s) found" . ($result['error_message'] ? " [{$result['error_message']}]" : ''));
        }

        return self::SUCCESS;
    }
}
