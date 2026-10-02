<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Grant or revoke one permission for one role from the matrix checkboxes.
 */
class TogglePermissionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'granted' => ['required', 'boolean'],
        ];
    }
}
