<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Recurring;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recurring>
 */
class RecurringFactory extends Factory
{
    protected $model = Recurring::class;

    public function definition(): array
    {
        return [
            // Whoever is signed in (a test's acting user), else a fresh account.
            'user_id' => fn () => auth()->id() ?? User::factory(),
            'amount' => 1500000, // centavos
            'note' => 'Factory standing order',
            'day_of_month' => 1,
            'start_date' => '2026-01-01',
            'end_date' => null,
            'active' => true,
            'category_id' => Category::factory(),
        ];
    }
}
