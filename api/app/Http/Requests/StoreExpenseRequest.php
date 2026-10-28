<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesItems;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends ExpenseRequest
{
    use ValidatesItems;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        // note is intentionally unvalidated (mock accepts absent/string/null) and read
        // straight from the request in the controller.
        return [
            ...$this->itemsRules(),
            'amount' => ['required', $this->centavosRule()],
            'date' => ['required', 'string', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('user_id', $this->user()->id)],
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
