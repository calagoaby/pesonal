<?php

namespace App\Http\Requests;

class UpdateRecurringRequest extends RecurringRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        // `sometimes` → only sent fields are validated. end_date present-and-null clears
        // it (open-ended). note and active are applied by the controller.
        return [
            'amount' => ['sometimes', 'required', $this->centavosRule()],
            'category_id' => ['sometimes', 'required', 'integer', 'exists:categories,id'],
            'day_of_month' => ['sometimes', 'required', 'integer', 'between:1,31'],
            'start_date' => ['sometimes', 'required', 'string', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
            'end_date' => ['sometimes', 'nullable', 'string', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
        ];
    }
}
