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
