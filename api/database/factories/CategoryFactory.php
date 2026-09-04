<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    // `categories.name` is unique, so the default has to be collision-free across a
    // whole test run — a counter, not a random word that faker can repeat.
    private static int $sequence = 0;

    public function definition(): array
    {
        $n = ++self::$sequence;

        return [
            'name' => "Category {$n}",
            'color' => '#'.substr(str_pad(dechex($n * 0x1F1F1F), 6, '0', STR_PAD_LEFT), -6),
            'emoji' => '🧾',
        ];
    }
}