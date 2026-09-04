<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Expense;
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
            'amount' => 15000, // centavos
            'date' => '2026-06-15',
            'note' => 'Factory expense',
            'category_id' => Category::factory(),
            'recurring_id' => null,
        ];
    }
}
