<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Only seeds admin-owned data. App data (sports, courts, matches) is seeded by the backend.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            AdminUserSeeder::class,
            PartnerUserSeeder::class,
            ManagerUserSeeder::class,
        ]);
    }
}
