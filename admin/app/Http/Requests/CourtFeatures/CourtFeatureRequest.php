<?php

namespace App\Http\Requests\CourtFeatures;

use App\Models\CourtFeature;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Amenity of the court_features catalog. The key is generated from the name on creation and never
 * changes afterwards (the surcharges look features up by key).
 */
class CourtFeatureRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->route('courtFeature') === null) {
            $this->merge(['key' => Str::slug((string) $this->input('name'), '_')]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $feature = $this->route('courtFeature');

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('court_features', 'name')->ignore($feature instanceof CourtFeature ? $feature : null)],
            'key' => [Rule::excludeIf($feature !== null), 'required', 'string', 'max:50', Rule::unique('court_features', 'key')],
            // Font Awesome class, e.g. "fas fa-parking".
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
            'key.unique' => 'Ya existe una característica con un nombre equivalente.',
            'icon.regex' => 'Usa una clase de Font Awesome, ej: fas fa-parking.',
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
