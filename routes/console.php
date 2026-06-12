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
