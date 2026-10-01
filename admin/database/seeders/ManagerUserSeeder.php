<?php

namespace Database\Seeders;

use App\Models\Court;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Sample venue manager (role "manager") of "Complejo Wally Sur". Only adds the assignment,
 * so managers removed or added from the panel are left alone.
 */
class ManagerUserSeeder extends Seeder
{
    public function run(): void
    {
        $manager = User::query()->firstOrCreate(
            ['email' => 'manager.wallysur@matchdeportivo.test'],
            [
                'name' => 'Manager Wally Sur',
                'phone' => '70000004',
                'password' => Hash::make('12345678'),
            ],
        );

        // Only on first creation: a later role change from the panel is kept.
        if ($manager->wasRecentlyCreated) {
            $manager->assignRole('manager');

            Court::query()
                ->where('name', 'Complejo Wally Sur')
                ->get()
                ->each(fn (Court $court) => $court->managers()->syncWithoutDetaching([$manager->id]));
        }
    }
}
