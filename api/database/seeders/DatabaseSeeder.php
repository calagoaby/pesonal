<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Every seeded row belongs to this verified demo account (password: password).
        $demo = User::forceCreate([
            'name' => 'Demo',
            'email' => 'demo@pesonal.test',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);

        // Order matters: expenses and recurring reference category ids, and
        // expenses reference recurring id 1.
        $this->callWith([
            CategorySeeder::class,
            RecurringSeeder::class,
            ExpenseSeeder::class,
        ], ['userId' => $demo->id]);
    }
}
