<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Order matters: expenses and recurring reference category ids, and
        // expenses reference recurring id 1.
        $this->call([
            CategorySeeder::class,
            RecurringSeeder::class,
            ExpenseSeeder::class,
        ]);
    }
}