<?php

// v1.0 — 2026-06-15 | Delete Notification History rows older than the admin-configured retention period (0 = never expire)

namespace App\Console\Commands;

use App\Models\NotificationHistory;
use App\Models\Setting;
use Illuminate\Console\Command;

class PruneNotificationHistory extends Command
{
    protected $signature = 'notifications:prune-history';

    protected $description = 'Delete Notification History rows older than the admin-configured retention period.';

    public function handle(): int
    {
        $days = Setting::getNotificationHistoryRetentionDays();

        if ($days <= 0) {
            return Command::SUCCESS;
        }

        $deleted = NotificationHistory::where('created_at', '<', now()->subDays($days))->delete();

        if ($deleted > 0) {
            $this->info("Pruned {$deleted} notification(s) older than {$days} day(s).");
        }

        return Command::SUCCESS;
    }
}
