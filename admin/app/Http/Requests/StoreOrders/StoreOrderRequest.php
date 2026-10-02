<?php

namespace App\Http\Requests\StoreOrders;

use App\Models\CourtReservation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Counter sale registered by store staff: paid at once and the units leave the stock.
 */
class StoreOrderRequest extends FormRequest
{
    public const MAX_LINES = 30;

    protected function prepareForValidation(): void
    {
        // Lines left empty in the form are ignored.
        $items = collect($this->input('items', []))
            ->filter(fn ($item) => is_array($item) && filled($item['product_id'] ?? null))
            ->values()
            ->all();

        $this->merge(['items' => $items, 'delivered' => $this->boolean('delivered')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'items' => ['required', 'array', 'min:1', 'max:'.self::MAX_LINES],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'between:1,1000'],
            'user_email' => ['nullable', 'email', 'exists:users,email'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'payment_method' => ['required', Rule::in(array_keys(CourtReservation::PAYMENT_METHODS))],
            'delivered' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Agrega al menos un producto a la venta.',
            'user_email.exists' => 'No hay ningún usuario de la app con ese email.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'store_id' => 'tienda',
            'items' => 'productos',
            'items.*.product_id' => 'producto',
            'items.*.quantity' => 'cantidad',
            'user_email' => 'email del cliente',
            'customer_name' => 'nombre del cliente',
            'customer_phone' => 'teléfono',
            'payment_method' => 'método de pago',
            'notes' => 'notas',
        ];
    }
}
