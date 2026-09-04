<?php

namespace App\Http\Requests;

class UpdateExpenseRequest extends ExpenseRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        // `sometimes` → only sent fields are validated, so PATCH stays partial.
        // note is unvalidated and applied by the controller (absent vs null vs string).
        return [
            'amount' => ['sometimes', 'required', $this->centavosRule()],
            'date' => ['sometimes', 'required', 'string', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
            'category_id' => ['sometimes', 'required', 'integer', 'exists:categories,id'],
        ];
    }
}
