<?php

namespace App\Http\Requests\Courts;

use App\Models\RentalItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sports gear a venue rents with its courts (modal of the venue edit screen).
 */
class RentalItemRequest extends FormRequest
{
    protected $errorBag = 'rental';

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    /**
     * Only users who manage the sports center (CourtPolicy::manage).
     */
    public function authorize(): bool
    {
        return $this->user()->can('manage', $this->route('court'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'sport_id' => ['required', 'integer', 'exists:sports,id'],
            'description' => ['nullable', 'string', 'max:500'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999'],
            'price_type' => ['required', Rule::in(array_keys(RentalItem::PRICE_TYPES))],
            'stock' => ['nullable', 'integer', 'between:1,999'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'sport_id' => 'deporte',
            'description' => 'descripción',
            'price' => 'precio',
            'price_type' => 'tipo de cobro',
            'stock' => 'cantidad disponible',
        ];
    }
}
