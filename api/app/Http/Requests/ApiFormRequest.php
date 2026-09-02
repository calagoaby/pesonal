<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

abstract class ApiFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    // Pin the contract's 422 envelope. Laravel's default top-level message summarizes
    // the first error ("... (and 1 more error)"); the contract wants this literal.
    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(response()->json([
            'message' => 'The given data was invalid.',
            'errors' => $validator->errors(),
        ], 422));
    }

    // Money rule shared by expenses and recurring. Mirrors Number.isInteger(amount) && > 0:
    // rejects floats, zero, negatives, and numeric strings ("150"), which Laravel's integer accepts.
    protected function centavosRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_int($value) || $value <= 0) {
                $fail('The amount field must be a positive number of centavos.');
            }
        };
    }
}