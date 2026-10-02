<?php

namespace App\Http\Requests\Courts;

use App\Models\CourtFeature;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Physical court of a venue (modal of the venue edit screen).
 */
class CourtFieldRequest extends FormRequest
{
    protected $errorBag = 'field';

    /**
     * Unchecked checkboxes are not sent: an empty selection must clear the stored options (and their prices).
     */
    protected function prepareForValidation(): void
    {
        $features = array_values(array_unique((array) $this->input('features', [])));
        $this->merge(['features' => $features]);

        // Prices of options that are not checked are cleared.
        if (! in_array(CourtFeature::AIR_CONDITIONING, $features, true)) {
            $this->merge(['air_conditioning_price' => null]);
        }
        if (! in_array(CourtFeature::LIGHTING, $features, true)) {
            $this->merge(['lighting_price' => null, 'lighting_from' => null]);
        }
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
            // Keys of court_features.
            'features.*' => ['string', Rule::exists('court_features', 'key')],
            // Extra per hour when the player picks air conditioning (empty = included, no extra).
            'air_conditioning_price' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            // Extra per hour charged automatically for the hours from lighting_from.
            'lighting_price' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'lighting_from' => ['nullable', 'required_with:lighting_price', 'date_format:H:i'],
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
            'air_conditioning_price' => 'precio del aire acondicionado',
            'lighting_price' => 'precio de la iluminación',
            'lighting_from' => 'hora de encendido de la luz',
            'sports' => 'deportes',
        ];
    }
}
