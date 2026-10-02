<?php

namespace App\Http\Requests\Stores;

use App\Models\Court;
use App\Models\Store;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Create or edit a store of a sports center. The center is chosen on creation and never changes.
 */
class StoreRequest extends FormRequest
{
    /**
     * Editing requires managing the store's center (CourtPolicy::manage); the center of a new store
     * is checked in after().
     */
    public function authorize(): bool
    {
        $store = $this->route('store');

        return ! $store instanceof Store || $this->user()->can('manage', $store->court);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'categories' => $this->input('categories', []),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $editing = $this->route('store') instanceof Store;

        return [
            'court_id' => [Rule::excludeIf($editing), 'required', 'integer', 'exists:courts,id'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'phone' => ['nullable', 'string', 'max:30'],
            'categories' => ['required', 'array', 'min:1'],
            'categories.*' => ['integer', 'exists:product_categories,id'],
            'is_active' => ['boolean'],
            'cover' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'remove_cover' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * A new store can only be opened in a center the user manages.
     *
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->route('store') instanceof Store || $validator->errors()->has('court_id')) {
                    return;
                }

                $visible = Court::query()->visibleTo($this->user())->whereKey($this->input('court_id'))->exists();
                if (! $visible) {
                    $validator->errors()->add('court_id', 'Solo puedes abrir tiendas en tus centros deportivos.');
                }
            },
        ];
    }

    /**
     * Attributes saved on the store (cover and categories are handled by the controller).
     *
     * @return array<string, mixed>
     */
    public function storeData(): array
    {
        return collect($this->validated())->except(['categories', 'cover', 'remove_cover'])->all();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'categories.required' => 'Elige al menos una categoría de productos.',
            'cover.uploaded' => 'No se pudo subir la portada (¿pesa más de 5 MB?).',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'court_id' => 'centro deportivo',
            'name' => 'nombre',
            'description' => 'descripción',
            'phone' => 'teléfono',
            'categories' => 'categorías',
            'is_active' => 'activa',
            'cover' => 'portada',
        ];
    }
}
