<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Sample court administrators (role "admin"). Run after RolesAndPermissionsSeeder,
 * which gives the role its default permissions.
 */
class AdminUserSeeder extends Seeder
{
    /**
     * @var list<array{name: string, email: string, phone: string}>
     */
    private const ADMINS = [
        ['name' => 'Administrador Norte', 'email' => 'admin.norte@matchdeportivo.test', 'phone' => '70000001'],
        ['name' => 'Administrador Sur', 'email' => 'admin.sur@matchdeportivo.test', 'phone' => '70000002'],
    ];

    public function run(): void
    {
        $role = Role::findByName('admin', 'web');

        foreach (self::ADMINS as $admin) {
            // firstOrCreate: re-seeding never resets a password changed from the panel.
            $user = User::query()->firstOrCreate(
                ['email' => $admin['email']],
                [
                    'name' => $admin['name'],
                    'phone' => $admin['phone'],
                    'password' => Hash::make('12345678'),
                ],
            );

            $user->assignRole($role);
        }
    }
}
