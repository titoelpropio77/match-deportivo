<?php

namespace App\Http\Requests\Stores;

use App\Models\StockMovement;
use App\Models\Store;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Stock change registered by hand: restock (+), loss (−) or a physical count (sets the stock).
 */
class StockMovementRequest extends FormRequest
{
    protected $errorBag = 'stock';

    public function authorize(): bool
    {
        /** @var Store $store */
        $store = $this->route('store');

        return $this->user()->can('manage', $store->court);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(StockMovement::MANUAL_TYPES)],
            // A count may be 0 (nothing left); restock and loss move at least one unit.
            'quantity' => ['required', 'integer', 'max:100000', $this->input('type') === StockMovement::TYPE_ADJUSTMENT ? 'min:0' : 'min:1'],
            'reason' => [Rule::requiredIf($this->input('type') === StockMovement::TYPE_LOSS), 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Indica el motivo de la baja (dañado, vencido, uso interno...).',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'type' => 'tipo de movimiento',
            'quantity' => 'cantidad',
            'reason' => 'motivo',
        ];
    }
}
