<?php

namespace App\Http\Requests\Courts;

use App\Models\Court;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Assign a registered user as manager of a sports center (by email).
 */
class CourtManagerRequest extends FormRequest
{
    /**
     * Roles that may be turned into a manager: app players or existing managers, never other panel staff.
     */
    public const ASSIGNABLE_FROM = ['cliente', 'manager'];

    protected $errorBag = 'manager';

    /**
     * Only platform staff and the venue owner (CourtPolicy::assignManagers).
     */
    public function authorize(): bool
    {
        return $this->user()->can('assignManagers', $this->route('court'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'exists:users,email'],
        ];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                /** @var Court $court */
                $court = $this->route('court');
                $user = $this->manager();
                $otherRoles = $user->getRoleNames()->diff(self::ASSIGNABLE_FROM);

                $message = match (true) {
                    $user->id === $court->owner_id => 'El dueño del centro deportivo no puede ser su manager.',
                    $otherRoles->isNotEmpty() => "{$user->name} ya tiene acceso al panel con el rol {$otherRoles->implode(', ')}.",
                    $court->managers()->whereKey($user->id)->exists() => "{$user->name} ya es manager de este centro deportivo.",
                    default => null,
                };
                if ($message !== null) {
                    $validator->errors()->add('email', $message);
                }
            },
        ];
    }

    /**
     * The user to assign (only after validation passed).
     */
    public function manager(): User
    {
        return User::query()->where('email', $this->input('email'))->firstOrFail();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.exists' => 'No hay ningún usuario registrado con ese email.',
        ];
    }
}
