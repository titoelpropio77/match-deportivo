<?php

namespace App\Http\Requests\Stores;

use App\Models\Product;
use App\Models\Store;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Create or edit a product of a store, with its photo gallery. The stock is only set on creation;
 * afterwards it changes through stock movements (StockMovementRequest) and sales.
 */
class ProductRequest extends FormRequest
{
    public const MAX_DISCOUNT = 90;

    public function authorize(): bool
    {
        /** @var Store $store */
        $store = $this->route('store');

        return $this->user()->can('manage', $store->court);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'discount_percent' => $this->input('discount_percent') ?: 0,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Store $store */
        $store = $this->route('store');
        $editing = $this->route('product') instanceof Product;

        return [
            // Only the categories the store sells.
            'product_category_id' => ['required', 'integer', Rule::exists('product_category_store', 'product_category_id')->where('store_id', $store->id)],
            'name' => ['required', 'string', 'max:150'],
            'sku' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999'],
            'discount_percent' => ['integer', 'between:0,'.self::MAX_DISCOUNT],
            'stock' => [Rule::excludeIf($editing), 'required', 'integer', 'between:0,100000'],
            'min_stock' => ['nullable', 'integer', 'between:0,100000'],
            'is_active' => ['boolean'],
            'photos' => ['sometimes', 'array', 'max:'.Product::MAX_PHOTOS],
            'photos.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'remove_photos' => ['sometimes', 'array'],
            'remove_photos.*' => ['integer'],
        ];
    }

    /**
     * On edit, the gallery after removals and uploads must stay within the limit.
     *
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $product = $this->route('product');
                if (! $product instanceof Product || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $removed = $product->photos()->whereIn('id', $this->input('remove_photos', []))->count();
                if ($product->photos()->count() - $removed + count($this->file('photos', [])) > Product::MAX_PHOTOS) {
                    $validator->errors()->add('photos', 'La galería admite como máximo '.Product::MAX_PHOTOS.' fotos; quita alguna antes de subir más.');
                }
            },
        ];
    }

    /**
     * Attributes saved on the product (photos are handled by the controller).
     *
     * @return array<string, mixed>
     */
    public function productData(): array
    {
        return collect($this->validated())->except(['photos', 'remove_photos', 'stock'])->all();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'product_category_id.exists' => 'Elige una de las categorías que vende la tienda.',
            'photos.*.image' => 'Cada archivo de la galería debe ser una imagen.',
            'photos.*.mimes' => 'Las fotos deben ser JPG, PNG o WEBP.',
            'photos.*.max' => 'Cada foto puede pesar como máximo 5 MB.',
            'photos.*.uploaded' => 'No se pudo subir una de las fotos (¿pesa más de 5 MB?).',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'product_category_id' => 'categoría',
            'name' => 'nombre',
            'sku' => 'código (SKU)',
            'description' => 'descripción',
            'price' => 'precio',
            'discount_percent' => 'descuento',
            'stock' => 'stock inicial',
            'min_stock' => 'stock mínimo',
            'is_active' => 'a la venta',
            'photos' => 'fotos',
        ];
    }
}
