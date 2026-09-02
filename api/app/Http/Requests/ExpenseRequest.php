<?php

namespace App\Http\Requests;

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
}