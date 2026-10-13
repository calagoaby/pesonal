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
            'category_id' => Category::factory(),
            'recurring_id' => null,
        ];
    }
}
