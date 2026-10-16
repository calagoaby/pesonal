<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        return [
            // Whoever is signed in (a test's acting user), else a fresh account.
            'user_id' => fn () => auth()->id() ?? User::factory(),
            'amount' => 15000, // centavos
            'date' => '2026-06-15',
            'note' => 'Factory expense',
            // Closures run in key order, so user_id is already an id here: the category gets the row's owner.
            'category_id' => fn (array $attributes) => Category::factory()->state(['user_id' => $attributes['user_id']]),
            'recurring_id' => null,
        ];
    }
}
