<?php

namespace App\Console\Commands;

use App\Actions\MaterializeRecurring;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class MaterializeRecurringCommand extends Command
{
    protected $signature = 'recurring:materialize';

    protected $description = 'Materialize due recurring templates into expenses for the current month';

    public function handle(MaterializeRecurring $action): int
    {
        // The calendar date in the job's timezone, rebuilt as a plain date: Manila's Nov 1st is
        // still Oct 31st in UTC, and only the date itself matters to the job.
        $today = Carbon::parse(now(config('pesonal.job_timezone'))->toDateString());
        $created = $action->execute($today);

        $this->info("Materialized {$created} recurring expense(s).");

        return self::SUCCESS;
    }
}
