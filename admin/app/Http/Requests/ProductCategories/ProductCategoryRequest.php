<?php

namespace App\Http\Requests\ProductCategories;

use App\Models\ProductCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Category of the product_categories catalog. The key is generated from the name on creation and
 * never changes afterwards (the app uses it to pick an icon).
 */
class ProductCategoryRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->route('productCategory') === null) {
            $this->merge(['key' => Str::slug((string) $this->input('name'), '_')]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $category = $this->route('productCategory');

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('product_categories', 'name')->ignore($category instanceof ProductCategory ? $category : null)],
            'key' => [Rule::excludeIf($category !== null), 'required', 'string', 'max:50', Rule::unique('product_categories', 'key')],
            // Font Awesome class, e.g. "fas fa-futbol".
            'icon' => ['nullable', 'string', 'max:50', 'regex:/^fa[srb]? fa-[a-z0-9-]+$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'key.required' => 'El nombre debe tener letras o números.',
            'key.unique' => 'Ya existe una categoría con un nombre equivalente.',
            'icon.regex' => 'Usa una clase de Font Awesome, ej: fas fa-futbol.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'icon' => 'ícono',
        ];
    }
}
