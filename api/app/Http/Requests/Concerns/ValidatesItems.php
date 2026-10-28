<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\Validator;

/**
 * Shared by the four expense and standing-order write requests. Needs centavosRule()
 * from ApiFormRequest.
 */
trait ValidatesItems
{
    /** @return array<string, mixed> */
    protected function itemsRules(): array
    {
        return [
            'items' => ['sometimes', 'array', 'max:20'],
            'items.*' => ['array'],
            'items.*.name' => ['required', 'string', 'max:40'],
            'items.*.amount' => ['required', $this->centavosRule()],
        ];
    }

    /**
     * The sum is checked against the amount the record will end up with: what was
     * sent, else what is stored. A request sending neither amount nor items is never
     * refused over items, and an input that already failed validation is left alone.
     *
     * @param  list<array{name: string, amount: int}>  $storedItems
     */
    protected function itemsFitAmount(Validator $validator, ?int $storedAmount, array $storedItems): void
    {
        if (! $this->has('amount') && ! $this->has('items')) {
            return;
        }

        foreach ($validator->errors()->keys() as $key) {
            if ($key === 'amount' || $key === 'items' || str_starts_with($key, 'items.')) {
                return;
            }
        }

        $amount = $this->has('amount') ? $this->input('amount') : $storedAmount;
        $items = $this->has('items') ? $this->input('items') : $storedItems;

        if ($amount !== null && array_sum(array_column($items, 'amount')) > $amount) {
            $validator->errors()->add('items', 'The items add up to more than the amount.');
        }
    }
}
