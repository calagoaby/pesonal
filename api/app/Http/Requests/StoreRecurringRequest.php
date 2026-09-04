<?php

namespace App\Http\Requests;

class StoreRecurringRequest extends RecurringRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        // note and active are read straight from the request (mock doesn't type-validate
        // them): note ?? null, active ?? true. day_of_month is stored as authored — the
        // clamp to month length happens only at materialization.
        return [
            'amount' => ['required', $this->centavosRule()],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'day_of_month' => ['required', 'integer', 'between:1,31'],
            'start_date' => ['required', 'string', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
            'end_date' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
        ];
    }
}
