<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Hourly, not daily: daily means midnight, when the dev servers are rarely running.
// The job is idempotent by (recurring_id, month), so running it often is harmless.
Schedule::command('recurring:materialize')->hourly();
