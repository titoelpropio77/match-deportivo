<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
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
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'gender' => ['required', 'string', 'in:male,female'],
            'birth_date' => ['nullable', 'date', 'before:-5 years', 'after:1900-01-01'],
            'favorite_sport_ids' => ['sometimes', 'array'],
            'favorite_sport_ids.*' => ['integer', 'distinct', 'exists:sports,id'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
            'photo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ];
    }
}
