<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends ApiFormRequest
{
    public function rules(): array
    {
        // `sometimes` → a field is validated only when present, so PATCH stays partial.
        return [
            'name' => ['sometimes', 'required', 'string', Rule::unique('categories', 'name')->ignore($this->route('category'))],
            'color' => ['sometimes', 'required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'emoji' => ['sometimes', 'required', 'string'],
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
        if ($this->has('name') && is_string($this->name)) {
            $this->merge(['name' => trim($this->name)]);
        }
    }
}