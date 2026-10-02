<?php

namespace App\Http\Controllers\Settings;

use App\DataTables\RoleDataTable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\RoleRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public const PROTECTED_ROLES = ['superadmin'];

    public function index(RoleDataTable $dataTable): mixed
    {
        return $dataTable->render('settings.roles.index');
    }

    public function create(): View
    {
        return view('settings.roles.create', [
            'role' => new Role,
            'permissionGroups' => $this->permissionGroups(),
            'granted' => [],
        ]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $validated = $request->validated();

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

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        if ($this->isProtected($role)) {
            return back()->with('error', 'El rol superadmin no se puede modificar: siempre tiene todos los permisos.');
        }

        $validated = $request->validated();

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
