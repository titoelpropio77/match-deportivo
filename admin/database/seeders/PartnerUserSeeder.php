<?php

namespace Database\Seeders;

use App\Models\Court;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Sample court owner (role "partner"). Gets the listed courts only if they have no owner yet,
 * so an assignment changed from the panel is never overwritten.
 */
class PartnerUserSeeder extends Seeder
{
    public function run(): void
    {
        $partner = User::query()->firstOrCreate(
            ['email' => 'partner.wallysur@matchdeportivo.test'],
            [
                'name' => 'Partner Wally Sur',
                'phone' => '70000003',
                'password' => Hash::make('12345678'),
            ],
        );
        $partner->assignRole('partner');

        Court::query()
            ->whereIn('name', ['Complejo Wally Sur'])
            ->whereNull('owner_id')
            ->update(['owner_id' => $partner->id]);
    }
}
