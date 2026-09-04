<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;

abstract class RecurringRequest extends ApiFormRequest
{
    public function messages(): array
    {
        return [
            'amount.required' => 'The amount field must be a positive number of centavos.',
            'category_id.required' => 'The selected category is invalid.',
            'category_id.integer' => 'The selected category is invalid.',
            'category_id.exists' => 'The selected category is invalid.',
            'day_of_month.required' => 'The day of month must be between 1 and 31.',
            'day_of_month.integer' => 'The day of month must be between 1 and 31.',
            'day_of_month.between' => 'The day of month must be between 1 and 31.',
            'start_date.required' => 'The start date must be a YYYY-MM-DD calendar date.',
            'start_date.string' => 'The start date must be a YYYY-MM-DD calendar date.',
            'start_date.regex' => 'The start date must be a YYYY-MM-DD calendar date.',
            'end_date.string' => 'The end date must be a YYYY-MM-DD calendar date.',
            'end_date.regex' => 'The end date must be a YYYY-MM-DD calendar date.',
        ];
    }

    // Cross-field end_date >= start_date, on the *merged* values (payload over stored),
    // reproducing the mock's string comparison. Skipped when a date failed format, so a
    // bad start_date never also shows a comparison error.
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->hasAny(['start_date', 'end_date'])) {
                return;
            }

            $existing = $this->route('recurring'); // bound model on PATCH, null on POST

            $start = $this->input('start_date', $existing?->start_date?->format('Y-m-d'));
            // end_date key present (even null) overrides the stored value; absent falls back.
            $end = $this->exists('end_date')
                ? $this->input('end_date')
                : $existing?->end_date?->format('Y-m-d');

            if ($start !== null && $end !== null && $end < $start) {
                $validator->errors()->add('end_date', 'The end date must be on or after the start date.');
            }
        });
    }
}
