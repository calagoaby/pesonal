<?php

namespace Database\Seeders;

use App\Models\Recurring;
use Illuminate\Database\Seeder;

class RecurringSeeder extends Seeder
{
    public function run(): void
    {
        // Timestamps mirror mockDb.ts — Gym's later updated_at is the pause; the rest
        // were created when each order started. active is raw 1/0 here (bulk insert
        // skips the boolean cast; the model casts it back on read).
        $recurring = [
            ['amount' => 1500000, 'note' => 'Apartment rent', 'day_of_month' => 1, 'start_date' => '2026-01-01', 'end_date' => null, 'active' => 1, 'category_id' => 1, 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'],
            ['amount' => 180000, 'note' => 'Internet', 'day_of_month' => 5, 'start_date' => '2026-03-01', 'end_date' => null, 'active' => 1, 'category_id' => 6, 'created_at' => '2026-03-01 00:00:00', 'updated_at' => '2026-03-01 00:00:00'],
            ['amount' => 19400, 'note' => 'Spotify', 'day_of_month' => 15, 'start_date' => '2026-02-01', 'end_date' => null, 'active' => 1, 'category_id' => 5, 'created_at' => '2026-02-01 00:00:00', 'updated_at' => '2026-02-01 00:00:00'],
            ['amount' => 150000, 'note' => 'Gym', 'day_of_month' => 15, 'start_date' => '2026-01-01', 'end_date' => null, 'active' => 0, 'category_id' => 7, 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-05-01 00:00:00'],
            ['amount' => 62000, 'note' => 'Water bill', 'day_of_month' => 20, 'start_date' => '2026-01-01', 'end_date' => null, 'active' => 1, 'category_id' => 6, 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'],
            ['amount' => 108600, 'note' => 'Netflix', 'day_of_month' => 20, 'start_date' => '2026-01-01', 'end_date' => '2026-12-01', 'active' => 1, 'category_id' => 5, 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'],
        ];

        Recurring::insert($recurring);
    }
}
