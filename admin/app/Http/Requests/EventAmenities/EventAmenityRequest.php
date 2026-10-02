<?php

namespace App\Http\Requests\EventAmenities;

use App\Models\EventAmenity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Amenity of the event_amenities catalog. The key is generated from the name on creation and never
 * changes afterwards (the app receives it with each space).
 */
class EventAmenityRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->route('eventAmenity') === null) {
            $this->merge(['key' => Str::slug((string) $this->input('name'), '_')]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $amenity = $this->route('eventAmenity');

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('event_amenities', 'name')->ignore($amenity instanceof EventAmenity ? $amenity : null)],
            'key' => [Rule::excludeIf($amenity !== null), 'required', 'string', 'max:50', Rule::unique('event_amenities', 'key')],
            // Font Awesome class, e.g. "fas fa-fire".
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
            'key.unique' => 'Ya existe un servicio con un nombre equivalente.',
            'icon.regex' => 'Usa una clase de Font Awesome, ej: fas fa-fire.',
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
