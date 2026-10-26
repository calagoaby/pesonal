<?php

namespace App\Http\Requests;

use App\Models\Category;
use App\Rules\UniqueCategoryName;

class UpdateCategoryRequest extends ApiFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        // `sometimes` → a field is validated only when present, so PATCH stays partial.
        return [
            'name' => ['sometimes', 'required', 'string', new UniqueCategoryName($this->user()->id, $this->boundCategory()->id)],
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

    // The binding in AppServiceProvider resolves (or 404s) before validation runs.
    private function boundCategory(): Category
    {
        $category = $this->route('category');
        assert($category instanceof Category);

        return $category;
    }
}
