<?php

namespace App\Http\Controllers;

use App\DataTables\UserDataTable;
use App\Http\Requests\Users\UserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(UserDataTable $dataTable): mixed
    {
        return $dataTable->render('users.index');
    }

    public function create(): View
    {
        return view('users.create', [
            'user' => new User,
            'roles' => Auth::user()->grantableRoles(),
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = User::query()->create($validated);
        $user->syncRoles($validated['roles'] ?? []);

        return redirect()->route('users.index')->with('success', "Usuario {$user->name} creado.");
    }

    public function show(User $user): View
    {
        $user->load('roles')->loadCount(['courts', 'organizedMatches']);

        return view('users.show', ['user' => $user]);
    }

    public function edit(User $user): View
    {
        $this->ensureCanManage($user);

        return view('users.edit', [
            'user' => $user,
            'roles' => Auth::user()->grantableRoles(),
        ]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $this->ensureCanManage($user);
        $validated = $request->validated();

        if (blank($validated['password'] ?? null)) {
            unset($validated['password']);
        }

        $user->update($validated);

        // Never let users strip their own access by accident.
        $roles = $validated['roles'] ?? [];
        if ($user->is(Auth::user()) && $user->hasRole('superadmin') && ! in_array('superadmin', $roles, true)) {
            $roles[] = 'superadmin';
        }
        $user->syncRoles($roles);

        return redirect()->route('users.index')->with('success', "Usuario {$user->name} actualizado.");
    }

    public function destroy(User $user): JsonResponse
    {
        if ($user->is(Auth::user())) {
            return response()->json(['message' => 'No puedes eliminar tu propio usuario.'], 422);
        }

        $this->ensureCanManage($user);
        $user->delete();

        return response()->json(['message' => "Usuario {$user->name} eliminado."]);
    }

    /**
     * Users holding a role the current user cannot grant (e.g. a superadmin) are off limits.
     */
    private function ensureCanManage(User $user): void
    {
        $outOfReach = $user->getRoleNames()->diff(Auth::user()->grantableRoles()->pluck('name'));

        abort_if(
            $outOfReach->isNotEmpty(),
            403,
            'No puedes modificar a un usuario con el rol '.$outOfReach->implode(', ').'.'
        );
    }
}
