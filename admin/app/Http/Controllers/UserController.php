<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;
use Yajra\DataTables\Facades\DataTables;

class UserController extends Controller
{
    public function index(): View
    {
        return view('users.index');
    }

    /**
     * Server-side DataTables source for the users list.
     */
    public function data(): JsonResponse
    {
        $query = User::query()
            ->select(['id', 'name', 'nickname', 'email', 'phone', 'updated_at'])
            ->with('roles:id,name');

        return DataTables::eloquent($query)
            ->addColumn('roles', fn (User $user) => view('users.partials.roles', ['user' => $user])->render())
            ->filterColumn('roles', function ($query, $keyword): void {
                $query->whereHas('roles', fn ($roles) => $roles->where('name', 'ilike', "%{$keyword}%"));
            })
            ->editColumn('email', fn (User $user) => view('partials.email', ['email' => $user->email])->render())
            ->editColumn('updated_at', fn (User $user) => $user->updated_at?->diffForHumans())
            ->addColumn('action', fn (User $user) => view('users.partials.actions', ['user' => $user])->render())
            ->rawColumns(['roles', 'email', 'action'])
            ->toJson();
    }

    public function create(): View
    {
        return view('users.create', [
            'user' => new User,
            'roles' => $this->assignableRoles(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateUser($request);

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
            'roles' => $this->assignableRoles(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->ensureCanManage($user);
        $validated = $this->validateUser($request, $user);

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
     * @return array<string, mixed>
     */
    private function validateUser(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'nickname' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:30'],
            'gender' => ['nullable', Rule::in(array_keys(User::GENDERS))],
            'preferred_position' => ['nullable', 'string', 'max:255'],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8)],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', Rule::in($this->assignableRoles()->pluck('name'))],
        ], [], [
            'name' => 'nombre',
            'phone' => 'teléfono',
            'gender' => 'género',
            'preferred_position' => 'posición',
            'password' => 'contraseña',
        ]);
    }

    /**
     * Roles the current user may grant: a superadmin grants any; anyone else only roles whose
     * permissions they hold themselves (so nobody can create a user with more access than they have).
     * superadmin is never assignable by others: it has no permissions but passes every check.
     *
     * @return Collection<int, Role>
     */
    private function assignableRoles(): Collection
    {
        $actor = Auth::user();
        $roles = Role::query()->with('permissions:id,name')->orderBy('name')->get();

        if ($actor->hasRole('superadmin')) {
            return $roles;
        }

        $own = $actor->getAllPermissions()->pluck('name');

        return $roles
            ->reject(fn (Role $role) => $role->name === 'superadmin')
            ->filter(fn (Role $role) => $role->permissions->pluck('name')->diff($own)->isEmpty())
            ->values();
    }

    /**
     * Users holding a role the current user cannot grant (e.g. a superadmin) are off limits.
     */
    private function ensureCanManage(User $user): void
    {
        $outOfReach = $user->getRoleNames()->diff($this->assignableRoles()->pluck('name'));

        abort_if(
            $outOfReach->isNotEmpty(),
            403,
            'No puedes modificar a un usuario con el rol '.$outOfReach->implode(', ').'.'
        );
    }
}
