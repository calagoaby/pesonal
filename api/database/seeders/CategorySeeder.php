<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Support\DefaultCategories;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(int $userId): void
    {
        // Fresh table, so insertion order fixes ids 1..8 — expenses and recurring
        // reference these ids, so DefaultCategories' order must not change.
        $seeded_at = '2026-01-01 00:00:00';

        Category::insert(array_map(
            fn (array $category) => [...$category, 'user_id' => $userId, 'created_at' => $seeded_at, 'updated_at' => $seeded_at],
            DefaultCategories::ALL,
        ));
    }
}
