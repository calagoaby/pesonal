<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesItems;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class UpdateExpenseRequest extends ExpenseRequest
{
    use ValidatesItems;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        // `sometimes` → only sent fields are validated, so PATCH stays partial.
        // note is unvalidated and applied by the controller (absent vs null vs string).
        return [
            ...$this->itemsRules(),
            'amount' => ['sometimes', 'required', $this->centavosRule()],
            'date' => ['sometimes', 'required', 'string', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
            'category_id' => ['sometimes', 'required', 'integer', Rule::exists('categories', 'id')->where('user_id', $this->user()->id)],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $existing = $this->route('expense');
                $this->itemsFitAmount($validator, $existing->amount->centavos(), $existing->items);
            },
        ];
    }
}
