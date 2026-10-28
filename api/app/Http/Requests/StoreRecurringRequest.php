<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesItems;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class StoreRecurringRequest extends RecurringRequest
{
    use ValidatesItems;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        // note and active are read straight from the request (mock doesn't type-validate
        // them): note ?? null, active ?? true. day_of_month is stored as authored — the
        // clamp to month length happens only at materialization.
        return [
            ...$this->itemsRules(),
            'amount' => ['required', $this->centavosRule()],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('user_id', $this->user()->id)],
            'day_of_month' => ['required', 'integer', 'between:1,31'],
            'start_date' => ['required', 'string', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
            'end_date' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->itemsFitAmount($validator, null, []);
            },
        ];
    }
}
