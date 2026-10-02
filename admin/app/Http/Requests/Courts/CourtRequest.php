<?php

namespace App\Http\Requests\Courts;

use App\Models\Court;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Create or edit a sports center, with its photo gallery.
 */
class CourtRequest extends FormRequest
{
    /**
     * Editing requires managing the sports center (CourtPolicy::manage); creating only the route permission.
     */
    public function authorize(): bool
    {
        $court = $this->route('court');

        return ! $court instanceof Court || $this->user()->can('manage', $court);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'address' => ['required', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'opening_time' => ['required', 'date_format:H:i'],
            'closing_time' => ['required', 'date_format:H:i', 'after:opening_time'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'sports' => ['sometimes', 'array'],
            'sports.*' => ['integer', 'exists:sports,id'],
            'photos' => ['sometimes', 'array', 'max:'.Court::MAX_PHOTOS],
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
                $court = $this->route('court');
                if (! $court instanceof Court || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $removed = $court->photos()->whereIn('id', $this->input('remove_photos', []))->count();
                if ($court->photos()->count() - $removed + count($this->file('photos', [])) > Court::MAX_PHOTOS) {
                    $validator->errors()->add('photos', 'La galería admite como máximo '.Court::MAX_PHOTOS.' fotos; quita alguna antes de subir más.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
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
            'name' => 'nombre',
            'city_id' => 'ciudad',
            'address' => 'dirección',
            'latitude' => 'latitud',
            'longitude' => 'longitud',
            'opening_time' => 'hora de apertura',
            'closing_time' => 'hora de cierre',
            'owner_id' => 'partner (dueño)',
            'sports' => 'deportes',
            'photos' => 'galería de fotos',
        ];
    }
}
