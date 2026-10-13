<?php

namespace App\Support;

/**
 * The one list behind both a new account's starting categories and the demo seed.
 * The seeder relies on this order for its category ids 1..8, so append, never reorder.
 */
final class DefaultCategories
{
    /** @var list<array{name: string, color: string, emoji: string}> */
    public const ALL = [
        ['name' => 'Housing', 'color' => '#534F8C', 'emoji' => '🏠'],
        ['name' => 'Groceries', 'color' => '#5E7A3E', 'emoji' => '🛒'],
        ['name' => 'Food', 'color' => '#C9692E', 'emoji' => '🍔'],
        ['name' => 'Transport', 'color' => '#3F6F97', 'emoji' => '🚗'],
        ['name' => 'Fun', 'color' => '#B0577B', 'emoji' => '🎉'],
        ['name' => 'Utilities', 'color' => '#7A6A3E', 'emoji' => '💡'],
        ['name' => 'Health', 'color' => '#2F7D52', 'emoji' => '💊'],
        ['name' => 'Other', 'color' => '#8A857B', 'emoji' => '🧾'],
    ];
}
