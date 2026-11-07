<?php

// v1.0 — 2026-11-07 | Video consulting Phase 1 — sweep unconverted holds whose TTL has
//                      expired, same shape as assignments:release-scheduled.

namespace App\Console\Commands;

use App\Models\VideoConsultingHold;
use Illuminate\Console\Command;

class ReleaseExpiredVideoConsultingHolds extends Command
{
    protected $signature   = 'video-consulting:release-expired-holds';
    protected $description = 'Delete video consulting holds whose TTL has expired and were never converted.';

    public function handle(): int
    {
        $count = VideoConsultingHold::where('status', VideoConsultingHold::STATUS_HELD)
            ->where('expires_at', '<=', now())
            ->delete();

        if ($count > 0) {
            $this->info("Released {$count} expired video consulting hold(s).");
        }

        return Command::SUCCESS;
    }
}
