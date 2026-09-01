<?php

namespace App\Http\Requests;

class StoreExpenseRequest extends ExpenseRequest
{
    public function rules(): array
    {
        // note is intentionally unvalidated (mock accepts absent/string/null) and read
        // straight from the request in the controller.
        return [
            'amount' => ['required', $this->centavosRule()],
            'date' => ['required', 'string', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
        ];
    }
}