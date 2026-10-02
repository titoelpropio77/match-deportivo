<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * "Completa tu perfil": data Google / Facebook do not give, asked after signing up with them.
 */
class CompleteProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'nickname' => ['nullable', 'string', 'max:40'],
            'phone' => ['nullable', 'string', 'max:30'],
            'gender' => ['required', Rule::in(['male', 'female'])],
            'preferred_position' => ['nullable', 'string', 'max:60'],
            'birth_date' => ['nullable', 'date', 'before:-5 years', 'after:1900-01-01'],
            'favorite_sport_ids' => ['sometimes', 'array'],
            'favorite_sport_ids.*' => ['integer', 'distinct', 'exists:sports,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'birth_date.before' => 'Revisa la fecha de nacimiento.',
            'birth_date.after' => 'Revisa la fecha de nacimiento.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'nickname' => 'apodo',
            'phone' => 'teléfono',
            'gender' => 'sexo',
            'preferred_position' => 'posición',
            'birth_date' => 'fecha de nacimiento',
            'favorite_sport_ids' => 'deportes favoritos',
        ];
    }
}
