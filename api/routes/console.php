<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// The job is idempotent by (recurring_id, month), so running it often is harmless.
if (app()->isProduction()) {
    // Once a day, just after Manila midnight: every run wakes the sleeping database, so hourly
    // would keep it awake around the clock.
    Schedule::command('recurring:materialize')->dailyAt('00:05')->timezone(config('pesonal.job_timezone'));
} else {
    // Hourly locally: daily means midnight, when the dev servers are rarely running.
    Schedule::command('recurring:materialize')->hourly();
}
