<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Idempotent by (recurring_id, month), so the exact time isn't load-bearing;
// a missed day self-heals on the next run.
Schedule::command('recurring:materialize')->daily();
