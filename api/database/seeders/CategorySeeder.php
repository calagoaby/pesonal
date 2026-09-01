<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        // Fresh table, so insertion order fixes ids 1..8 — expenses and recurring
        // reference these ids, so the order must not change.
        $seeded_at = '2026-01-01 00:00:00';

        $categories = [
            ['name' => 'Housing', 'color' => '#534F8C', 'emoji' => '🏠'],
            ['name' => 'Groceries', 'color' => '#5E7A3E', 'emoji' => '🛒'],
            ['name' => 'Food', 'color' => '#C9692E', 'emoji' => '🍔'],
            ['name' => 'Transport', 'color' => '#3F6F97', 'emoji' => '🚗'],
            ['name' => 'Fun', 'color' => '#B0577B', 'emoji' => '🎉'],
            ['name' => 'Utilities', 'color' => '#7A6A3E', 'emoji' => '💡'],
            ['name' => 'Health', 'color' => '#2F7D52', 'emoji' => '💊'],
            ['name' => 'Other', 'color' => '#8A857B', 'emoji' => '🧾'],
        ];

        Category::insert(array_map(
            fn (array $category) => [...$category, 'created_at' => $seeded_at, 'updated_at' => $seeded_at],
            $categories,
        ));
    }
}