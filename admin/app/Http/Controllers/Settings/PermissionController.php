<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Yajra\DataTables\Facades\DataTables;

class PermissionController extends Controller
{
    private const NAME_PATTERN = '/^[a-z0-9_]+(\.[a-z0-9_]+)+$/';

    /**
     * Permission × role matrix, grouped by module prefix (e.g. "courts" for "courts.update").
     */
    public function index(): View
    {
        return view('settings.permissions.index', [
            'roles' => Role::query()->orderBy('id')->get(),
        ]);
    }

    /**
     * Server-side DataTables source: one checkbox column per role (`role_<id>`).
     */
    public function data(): JsonResponse
    {
        $roles = Role::query()->orderBy('id')->get();
        $canAssign = auth()->user()->can('permissions.assign');

        $table = DataTables::eloquent(Permission::query()->with('roles:id'))
            ->addColumn('module', fn (Permission $permission) => Str::before($permission->name, '.'))
            ->filterColumn('module', function ($query, $keyword): void {
                $query->where('name', 'ilike', "{$keyword}%");
            })
            // The module is the name prefix, so sorting by name keeps each module together.
            ->orderColumn('module', 'name $1')
            ->addColumn('action', fn (Permission $permission) => view('settings.permissions.partials.actions', ['permission' => $permission])->render());

        foreach ($roles as $role) {
            $table->addColumn('role_'.$role->id, fn (Permission $permission) => view('settings.permissions.partials.toggle', [
                'permission' => $permission,
                'role' => $role,
                'canAssign' => $canAssign,
            ])->render());
        }

        return $table
            ->rawColumns([...$roles->map(fn (Role $role) => 'role_'.$role->id)->all(), 'action'])
            ->toJson();
    }

    public function create(): View
    {
        return view('settings.permissions.create', [
            'permission' => new Permission,
            'roles' => $this->assignableRoles(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePermission($request);

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

    public function update(Request $request, Permission $permission): RedirectResponse
    {
        $validated = $this->validatePermission($request, $permission);

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
    public function toggle(Request $request, Permission $permission, Role $role): JsonResponse
    {
        $validated = $request->validate(['granted' => ['required', 'boolean']]);

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

    /**
     * @return array<string, mixed>
     */
    private function validatePermission(Request $request, ?Permission $permission = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:125',
                'regex:'.self::NAME_PATTERN,
                Rule::unique('permissions', 'name')->where('guard_name', 'web')->ignore($permission),
            ],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', Rule::in($this->assignableRoles()->pluck('name'))],
        ], [
            'name.regex' => 'Usa el formato modulo.accion en minúsculas (ej: courts.update).',
        ], [
            'name' => 'nombre',
        ]);
    }

    private function assignableRoles()
    {
        return Role::query()->where('name', '!=', 'superadmin')->orderBy('id')->get();
    }
}
