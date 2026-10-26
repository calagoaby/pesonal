<?php

namespace App\Http\Requests;

use App\Rules\UniqueCategoryName;

class StoreCategoryRequest extends ApiFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', new UniqueCategoryName($this->user()->id)],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'emoji' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The name field is required.',
            'color.required' => 'The color must be a hex value.',
            'color.regex' => 'The color must be a hex value.',
            'emoji.required' => 'The emoji field is required.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Trim so a whitespace-only name fails `required` and the stored value is clean.
        if (is_string($this->name)) {
            $this->merge(['name' => trim($this->name)]);
        }
    }
}
