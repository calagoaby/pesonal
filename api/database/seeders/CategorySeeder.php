<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Support\DefaultCategories;
use App\Support\LedgerCipher;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(int $userId): void
    {
        // Fresh table, so insertion order fixes ids 1..8 — expenses and recurring
        // reference these ids, so DefaultCategories' order must not change.
        $seeded_at = '2026-01-01 00:00:00';

        Category::insert(array_map(
            // insert() skips the model's cast and hook, so the name is encrypted and fingerprinted by hand.
            fn (array $category) => [
                ...$category,
                'name' => LedgerCipher::encrypt($category['name']),
                'name_index' => LedgerCipher::fingerprint($userId, $category['name']),
                'user_id' => $userId, 'created_at' => $seeded_at, 'updated_at' => $seeded_at,
            ],
            DefaultCategories::ALL,
        ));
    }
}
