<?php

namespace App\Http\Controllers\Settings;

use App\DataTables\PermissionDataTable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PermissionRequest;
use App\Http\Requests\Settings\TogglePermissionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionController extends Controller
{
    /**
     * Permission × role matrix, grouped by module prefix (e.g. "courts" for "courts.update").
     */
    public function index(PermissionDataTable $dataTable): mixed
    {
        return $dataTable->render('settings.permissions.index');
    }

    public function create(): View
    {
        return view('settings.permissions.create', [
            'permission' => new Permission,
            'roles' => $this->assignableRoles(),
        ]);
    }

    public function store(PermissionRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $permission = Permission::create(['name' => $validated['name'], 'guard_name' => 'web']);
        $permission->syncRoles($validated['roles'] ?? []);

        return redirect()->route('settings.permissions.index')->with('success', "Permiso {$permission->name} creado.");
    }

    public function edit(Permission $permission): View
    {
        return view('settings.permissions.edit', [
            'permission' => $permission->load('roles'),
            'roles' => $this->assignableRoles(),
        ]);
    }

    public function update(PermissionRequest $request, Permission $permission): RedirectResponse
    {
        $validated = $request->validated();

        $permission->update(['name' => $validated['name']]);
        $permission->syncRoles($validated['roles'] ?? []);

        return redirect()->route('settings.permissions.index')->with('success', "Permiso {$permission->name} actualizado.");
    }

    public function destroy(Permission $permission): JsonResponse
    {
        $permission->delete();

        return response()->json(['message' => "Permiso {$permission->name} eliminado."]);
    }

    /**
     * Grant or revoke one permission for one role from the matrix checkboxes.
     */
    public function toggle(TogglePermissionRequest $request, Permission $permission, Role $role): JsonResponse
    {
        $validated = $request->validated();

        if ($role->name === 'superadmin') {
            return response()->json(['message' => 'El rol superadmin ya tiene todos los permisos.'], 422);
        }

        if ($validated['granted']) {
            $role->givePermissionTo($permission);
        } else {
            $role->revokePermissionTo($permission);
        }

        return response()->json([
            'message' => Str::of($validated['granted'] ? 'Asignado' : 'Quitado')
                ->append(" {$permission->name} a {$role->name}.")
                ->toString(),
        ]);
    }

    private function assignableRoles()
    {
        return Role::query()->where('name', '!=', 'superadmin')->orderBy('id')->get();
    }
}
