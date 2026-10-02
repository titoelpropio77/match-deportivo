<?php

namespace App\Http\Requests\Users;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Create or edit a panel/app user. Only roles the current user may grant are accepted.
 */
class UserRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->route('user');
        $editing = $user instanceof User;

        return [
            'name' => ['required', 'string', 'max:255'],
            'nickname' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($editing ? $user : null)],
            'phone' => ['nullable', 'string', 'max:30'],
            'gender' => ['nullable', Rule::in(array_keys(User::GENDERS))],
            'preferred_position' => ['nullable', 'string', 'max:255'],
            'password' => [$editing ? 'nullable' : 'required', 'confirmed', Password::min(8)],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', Rule::in($this->user()->grantableRoles()->pluck('name'))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'phone' => 'teléfono',
            'gender' => 'género',
            'preferred_position' => 'posición',
            'password' => 'contraseña',
        ];
    }
}
