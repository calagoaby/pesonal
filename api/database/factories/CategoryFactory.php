<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    // A name is unique per user, and a test's rows usually share one, so the default has
    // to be collision-free across a whole test run — a counter, not a random word.
    private static int $sequence = 0;

    public function definition(): array
    {
        $n = ++self::$sequence;

        return [
            // Whoever is signed in (a test's acting user), else a fresh account.
            'user_id' => fn () => auth()->id() ?? User::factory(),
            'name' => "Category {$n}",
            'color' => '#'.substr(str_pad(dechex($n * 0x1F1F1F), 6, '0', STR_PAD_LEFT), -6),
            'emoji' => '🧾',
        ];
    }
}
