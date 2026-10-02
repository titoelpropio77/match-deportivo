<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * One-off: the admin role now creates users and assigns roles. RolesAndPermissionsSeeder only grants
 * permissions that did not exist before to existing roles, so users.store/users.update are granted here.
 * On a fresh database the role does not exist yet and the seeder grants them as defaults.
 */
return new class extends Migration
{
    private const PERMISSIONS = ['users.store', 'users.update'];

    public function up(): void
    {
        $role = Role::query()->where(['name' => 'admin', 'guard_name' => 'web'])->first();
        if ($role === null) {
            return;
        }

        foreach (self::PERMISSIONS as $name) {
            $role->givePermissionTo(Permission::findOrCreate($name, 'web'));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::query()->where(['name' => 'admin', 'guard_name' => 'web'])->first()
            ?->revokePermissionTo(self::PERMISSIONS);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
