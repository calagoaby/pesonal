<?php

namespace App\Console\Commands;

use App\Actions\MaterializeRecurring;
use Illuminate\Console\Command;

class MaterializeRecurringCommand extends Command
{
    protected $signature = 'recurring:materialize';

    protected $description = 'Materialize due recurring templates into expenses for the current month';

    public function handle(MaterializeRecurring $action): int
    {
        $created = $action->execute(now());

        $this->info("Materialized {$created} recurring expense(s).");

        return self::SUCCESS;
    }
}
