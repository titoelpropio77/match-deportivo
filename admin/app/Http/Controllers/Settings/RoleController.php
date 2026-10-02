<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Yajra\DataTables\Facades\DataTables;

class RoleController extends Controller
{
    private const PROTECTED_ROLES = ['superadmin'];

    public function index(): View
    {
        return view('settings.roles.index');
    }

    /**
     * Server-side DataTables source for the roles list.
     */
    public function data(): JsonResponse
    {
        $query = Role::query()->withCount(['permissions', 'users']);

        return DataTables::eloquent($query)
            ->editColumn('name', fn (Role $role) => '<strong>'.e($role->name).'</strong>')
            ->editColumn('permissions_count', fn (Role $role) => in_array($role->name, self::PROTECTED_ROLES, true)
                ? '<span class="badge badge-danger">todos</span>'
                : '<span class="badge badge-primary">'.$role->permissions_count.'</span>')
            ->editColumn('users_count', fn (Role $role) => '<span class="badge badge-secondary">'.$role->users_count.'</span>')
            ->orderColumn('permissions_count', 'permissions_count $1')
            ->orderColumn('users_count', 'users_count $1')
            ->editColumn('created_at', fn (Role $role) => $role->created_at?->diffForHumans())
            ->addColumn('action', fn (Role $role) => view('settings.roles.partials.actions', [
                'role' => $role,
                'protected' => in_array($role->name, self::PROTECTED_ROLES, true),
            ])->render())
            ->rawColumns(['name', 'permissions_count', 'users_count', 'action'])
            ->toJson();
    }

    public function create(): View
    {
        return view('settings.roles.create', [
            'role' => new Role,
            'permissionGroups' => $this->permissionGroups(),
            'granted' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateRole($request);

        $role = Role::create(['name' => $validated['name'], 'guard_name' => 'web']);
        $role->syncPermissions($validated['permissions'] ?? []);

        return redirect()->route('settings.roles.index')->with('success', "Rol {$role->name} creado.");
    }

    public function edit(Role $role): View
    {
        return view('settings.roles.edit', [
            'role' => $role,
            'permissionGroups' => $this->permissionGroups(),
            'granted' => $role->permissions()->pluck('name')->all(),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        if ($this->isProtected($role)) {
            return back()->with('error', 'El rol superadmin no se puede modificar: siempre tiene todos los permisos.');
        }

        $validated = $this->validateRole($request, $role);

        $role->update(['name' => $validated['name']]);
        $role->syncPermissions($validated['permissions'] ?? []);

        return redirect()->route('settings.roles.index')->with('success', "Rol {$role->name} actualizado.");
    }

    public function destroy(Role $role): JsonResponse
    {
        if ($this->isProtected($role)) {
            return response()->json(['message' => 'El rol superadmin no se puede eliminar.'], 422);
        }

        $role->delete();

        return response()->json(['message' => "Rol {$role->name} eliminado."]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateRole(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:125',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($role),
            ],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')->where('guard_name', 'web')],
        ], [
            'name.regex' => 'Usa solo minúsculas, números y guion bajo (ej: admin_cancha).',
        ], [
            'name' => 'nombre',
            'permissions' => 'permisos',
        ]);
    }

    /**
     * @return Collection<string, Collection<int, Permission>>
     */
    private function permissionGroups(): Collection
    {
        return Permission::query()
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Permission $permission) => explode('.', $permission->name)[0]);
    }

    private function isProtected(Role $role): bool
    {
        return in_array($role->name, self::PROTECTED_ROLES, true);
    }
}
