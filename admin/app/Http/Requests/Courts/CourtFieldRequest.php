<?php

namespace App\Http\Requests\Courts;

use App\Enums\CourtFieldFeature;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Physical court of a venue (modal of the venue edit screen).
 */
class CourtFieldRequest extends FormRequest
{
    protected $errorBag = 'field';

    /**
     * Unchecked checkboxes are not sent: an empty selection must clear the stored options.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['features' => array_values(array_unique((array) $this->input('features', [])))]);
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
            'name' => ['required', 'string', 'max:255'],
            'price_per_hour' => ['required', 'numeric', 'min:0', 'max:999999'],
            'dimensions' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:2000'],
            'features' => ['array'],
            'features.*' => [Rule::enum(CourtFieldFeature::class)],
            'sports' => ['required', 'array', 'min:1'],
            'sports.*' => ['integer', 'exists:sports,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'features.*' => 'Una de las opciones adicionales no es válida.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'price_per_hour' => 'precio por hora',
            'dimensions' => 'dimensiones',
            'description' => 'descripción',
            'features' => 'opciones adicionales',
            'sports' => 'deportes',
        ];
    }
}
