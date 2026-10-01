<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Every admin screen/action is guarded by one of these. Naming: <module>.<action>.
     *
     * @var list<string>
     */
    public const PERMISSIONS = [
        'dashboard.index',

        'courts.index',
        'courts.show',
        'courts.store',
        'courts.update',
        'courts.destroy',
        // Without it a user only sees/manages the courts they own (courts.owner_id) or manage (court_managers).
        'courts.view_all',
        // Assign/remove managers of a court (owners: only on their own courts).
        'courts.managers',

        'court_fields.store',
        'court_fields.update',
        'court_fields.destroy',

        'users.index',
        'users.show',
        'users.store',
        'users.update',
        'users.destroy',

        'roles.index',
        'roles.store',
        'roles.update',
        'roles.destroy',

        'permissions.index',
        'permissions.store',
        'permissions.update',
        'permissions.destroy',
        'permissions.assign',
    ];

    /**
     * Default permissions per role. superadmin is not listed: it passes every check via Gate::before.
     *
     * @var array<string, list<string>>
     */
    private const ROLE_DEFAULTS = [
        // Platform staff: every court, read-only users.
        'admin' => [
            'dashboard.index',
            'courts.index',
            'courts.show',
            'courts.store',
            'courts.update',
            'courts.destroy',
            'courts.view_all',
            'courts.managers',
            'court_fields.store',
            'court_fields.update',
            'court_fields.destroy',
            'users.index',
            'users.show',
        ],
        // Court owner: only the courts assigned to them; can add managers to them.
        'partner' => [
            'dashboard.index',
            'courts.index',
            'courts.show',
            'courts.update',
            'courts.managers',
            'court_fields.store',
            'court_fields.update',
            'court_fields.destroy',
        ],
        // Staff assigned by an owner: only the courts they manage, cannot delete or assign managers.
        'manager' => [
            'dashboard.index',
            'courts.index',
            'courts.show',
            'courts.update',
            'court_fields.store',
            'court_fields.update',
        ],
        'cliente' => [],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $newPermissions = [];
        foreach (self::PERMISSIONS as $name) {
            if (Permission::findOrCreate($name, 'web')->wasRecentlyCreated) {
                $newPermissions[] = $name;
            }
        }

        Role::findOrCreate('superadmin', 'web');

        // Defaults go to new roles in full, and to existing roles only for permissions that did not
        // exist before. Changes made from the panel survive re-seeding (Docker seeds on every start).
        foreach (self::ROLE_DEFAULTS as $roleName => $defaults) {
            $role = Role::findOrCreate($roleName, 'web');
            $grant = $role->wasRecentlyCreated ? $defaults : array_values(array_intersect($defaults, $newPermissions));
            if ($grant !== []) {
                $role->givePermissionTo($grant);
            }
        }

        $superadmin = User::query()->firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@matchdeportivo.test')],
            [
                'name' => 'Super Admin',
                'password' => Hash::make(env('ADMIN_PASSWORD', 'password')),
            ],
        );
        $superadmin->assignRole('superadmin');
    }
}
