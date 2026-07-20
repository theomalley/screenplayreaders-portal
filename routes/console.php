<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Release assignments to Available when their scheduled available_at time has passed.
Schedule::command('assignments:release-scheduled')->everyMinute()->withoutOverlapping();
// 2026-06-02

// Check partner sites for backlinks (runs every 5 min; command skips sites that aren't due yet).
Schedule::command('marketing:check-partner-links')->everyFiveMinutes()->withoutOverlapping();
// 2026-06-08

// Auto-add the editor's weekly flat-rate pay as a pending adjustment, once per pay period.
Schedule::command('editor-pay:add-weekly-flat')->hourly()->withoutOverlapping();
// 2026-06-11

// Delete Notification History rows older than the admin-configured retention period (0 = never expire).
Schedule::command('notifications:prune-history')->daily()->withoutOverlapping();
// 2026-06-15

// Detect HelpScout conversation IDs merged into a new ID since being stored, and auto-heal
// the record before a draft attempt hits the stale ID and 404s.
Schedule::command('helpscout:reconcile-conversation-ids')->daily()->withoutOverlapping();
// 2026-07-16

// Transfer unaccepted assignments to their configured next tier once a tier's timeout elapses.
Schedule::command('tiers:escalate-timeouts')->hourly()->withoutOverlapping();
// 2026-07-20
