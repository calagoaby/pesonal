<?php

namespace App\Http\Requests;

use Closure;

abstract class ExpenseRequest extends ApiFormRequest
{
    public function messages(): array
    {
        return [
            'amount.required' => 'The amount field must be a positive number of centavos.',
            'date.required' => 'The date field must be a YYYY-MM-DD calendar date.',
            'date.string' => 'The date field must be a YYYY-MM-DD calendar date.',
            'date.regex' => 'The date field must be a YYYY-MM-DD calendar date.',
            'category_id.required' => 'The selected category is invalid.',
            'category_id.integer' => 'The selected category is invalid.',
            'category_id.exists' => 'The selected category is invalid.',
        ];
    }

    // Mirrors the mock's Number.isInteger(amount) && amount > 0: rejects floats,
    // zero, negatives, and numeric strings ("150"), which Laravel's integer rule accepts.
    protected function centavosRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_int($value) || $value <= 0) {
                $fail('The amount field must be a positive number of centavos.');
            }
        };
    }
}