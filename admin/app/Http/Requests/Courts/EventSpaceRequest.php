<?php

namespace App\Http\Requests\Courts;

use App\Enums\EventSpaceType;
use App\Models\EventAmenity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Event space of a venue (grill area, hall...), with an optional photo.
 */
class EventSpaceRequest extends FormRequest
{
    protected $errorBag = 'space';

    /**
     * Unchecked checkboxes are not sent: an empty selection must clear the stored values.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'amenities' => array_values(array_unique((array) $this->input('amenities', []))),
            'is_active' => $this->boolean('is_active'),
        ]);
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
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::enum(EventSpaceType::class)],
            'description' => ['nullable', 'string', 'max:2000'],
            'price_per_hour' => ['required', 'numeric', 'min:0', 'max:999999'],
            'capacity' => ['required', 'integer', 'between:1,2000'],
            'min_hours' => ['required', 'integer', 'between:1,12'],
            'amenities' => ['array'],
            // Keys of event_amenities.
            'amenities.*' => ['string', Rule::exists('event_amenities', 'key')],
            'rules' => ['nullable', 'string', 'max:2000'],
            'opening_time' => ['nullable', 'date_format:H:i', 'required_with:closing_time'],
            'closing_time' => ['nullable', 'date_format:H:i', 'required_with:opening_time', 'after:opening_time'],
            'is_active' => ['boolean'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'remove_photo' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Space attributes (without the upload fields and the amenities, which go to the pivot).
     *
     * @return array<string, mixed>
     */
    public function spaceData(): array
    {
        return collect($this->validated())->except(['photo', 'remove_photo', 'amenities'])->all();
    }

    /**
     * Ids of the checked amenities.
     *
     * @return list<int>
     */
    public function amenityIds(): array
    {
        return EventAmenity::query()->whereIn('key', $this->validated('amenities'))->pluck('id')->all();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amenities.*' => 'Uno de los servicios incluidos no es válido.',
            'closing_time.after' => 'El cierre debe ser posterior a la apertura.',
            'photo.max' => 'La foto puede pesar como máximo 5 MB.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'type' => 'tipo',
            'description' => 'descripción',
            'price_per_hour' => 'precio por hora',
            'capacity' => 'capacidad',
            'min_hours' => 'mínimo de horas',
            'rules' => 'normas',
            'opening_time' => 'apertura',
            'closing_time' => 'cierre',
            'photo' => 'foto',
        ];
    }
}
