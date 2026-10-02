<?php

namespace App\Http\Requests\Banners;

use App\Models\Banner;
use App\Models\Court;
use App\Models\Tournament;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Home carousel banner. The target depends on link_type: a tournament, a sports center, an
 * external URL or an app section (no target).
 */
class BannerRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $type = (string) $this->input('link_type');

        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'background_color' => strtoupper((string) $this->input('background_color', '#4F46E5')),
            // Only the selector matching the link type counts.
            'link_id' => match ($type) {
                'tournament' => $this->input('tournament_id'),
                'court' => $this->input('court_id'),
                default => null,
            },
            'link_url' => $type === 'url' ? $this->input('link_url') : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:80'],
            'subtitle' => ['nullable', 'string', 'max:160'],
            'button_label' => ['nullable', 'string', 'max:30'],
            'background_color' => ['required', 'regex:/^#[0-9A-F]{6}$/'],
            'link_type' => ['required', Rule::in(array_keys(Banner::LINK_TYPES))],
            'link_id' => [
                Rule::requiredIf(in_array($this->input('link_type'), Banner::RECORD_LINKS, true)),
                'nullable',
                'integer',
            ],
            'link_url' => [Rule::requiredIf($this->input('link_type') === 'url'), 'nullable', 'url:http,https', 'max:500'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'remove_image' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * The linked record must exist.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $id = $this->input('link_id');
                if ($id === null || $validator->errors()->has('link_id')) {
                    return;
                }

                $exists = match ($this->input('link_type')) {
                    'tournament' => Tournament::query()->whereKey($id)->exists(),
                    'court' => Court::query()->whereKey($id)->exists(),
                    default => true,
                };
                if (! $exists) {
                    $validator->errors()->add('link_id', 'El destino seleccionado ya no existe.');
                }
            },
        ];
    }

    /**
     * Attributes saved on the banner (the image is handled by the controller).
     *
     * @return array<string, mixed>
     */
    public function bannerData(): array
    {
        return collect($this->validated())->except(['image', 'remove_image'])->all();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'link_id.required' => 'Elige a dónde lleva el banner.',
            'link_url.required' => 'Escribe la dirección del enlace externo.',
            'link_url.url' => 'El enlace debe empezar con http:// o https://.',
            'background_color.regex' => 'El color debe tener el formato #RRGGBB.',
            'ends_at.after' => 'La fecha de fin debe ser posterior a la de inicio.',
            'image.uploaded' => 'No se pudo subir la imagen (¿pesa más de 5 MB?).',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'título',
            'subtitle' => 'subtítulo',
            'button_label' => 'texto del botón',
            'background_color' => 'color de fondo',
            'link_type' => 'destino',
            'link_id' => 'destino',
            'link_url' => 'enlace',
            'sort_order' => 'orden',
            'starts_at' => 'inicio de publicación',
            'ends_at' => 'fin de publicación',
            'image' => 'imagen',
        ];
    }
}
