<?php

namespace App\Http\Controllers;

use App\Models\Court;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Managers of a venue, assigned by its owner (or platform staff) from the venue edit screen.
 * Assigning grants the "manager" role; removing someone from their last venue takes it away.
 */
class CourtManagerController extends Controller
{
    /**
     * Roles that may be turned into a manager: app players or existing managers, never other panel staff.
     */
    private const ASSIGNABLE_FROM = ['cliente', 'manager'];

    public function store(Request $request, Court $court): RedirectResponse
    {
        Gate::authorize('assignManagers', $court);

        $validated = $request->validateWithBag('manager', [
            'email' => ['required', 'email', 'exists:users,email'],
        ], [
            'email.exists' => 'No hay ningún usuario registrado con ese email.',
        ], [
            'email' => 'email',
        ]);

        $user = User::query()->where('email', $validated['email'])->firstOrFail();

        $fail = fn (string $message) => throw ValidationException::withMessages(['email' => $message])->errorBag('manager');
        if ($user->id === $court->owner_id) {
            $fail('El dueño del centro deportivo no puede ser su manager.');
        }
        $otherRoles = $user->getRoleNames()->diff(self::ASSIGNABLE_FROM);
        if ($otherRoles->isNotEmpty()) {
            $fail("{$user->name} ya tiene acceso al panel con el rol {$otherRoles->implode(', ')}.");
        }
        if ($court->managers()->whereKey($user->id)->exists()) {
            $fail("{$user->name} ya es manager de este centro deportivo.");
        }

        DB::transaction(function () use ($court, $user): void {
            $court->managers()->attach($user->id);
            if (! $user->hasRole('manager')) {
                $user->assignRole('manager');
            }
        });

        return redirect()->route('courts.edit', $court)->with('success', "{$user->name} ahora es manager de {$court->name}.");
    }

    public function destroy(Court $court, User $manager): RedirectResponse
    {
        Gate::authorize('assignManagers', $court);

        DB::transaction(function () use ($court, $manager): void {
            $court->managers()->detach($manager->id);
            if ($manager->hasRole('manager') && ! $manager->managedCourts()->exists()) {
                $manager->removeRole('manager');
            }
        });

        return redirect()->route('courts.edit', $court)->with('success', "{$manager->name} ya no es manager de {$court->name}.");
    }
}
