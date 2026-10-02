<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;

/**
 * Create or edit a permission ("modulo.accion") and the roles that have it (never superadmin).
 */
class PermissionRequest extends FormRequest
{
    public const NAME_PATTERN = '/^[a-z0-9_]+(\.[a-z0-9_]+)+$/';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $permission = $this->route('permission');

        return [
            'name' => [
                'required',
                'string',
                'max:125',
                'regex:'.self::NAME_PATTERN,
                Rule::unique('permissions', 'name')->where('guard_name', 'web')->ignore($permission instanceof Permission ? $permission : null),
            ],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')->where('guard_name', 'web'), Rule::notIn(['superadmin'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.regex' => 'Usa el formato modulo.accion en minúsculas (ej: courts.update).',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'nombre'];
    }
}
