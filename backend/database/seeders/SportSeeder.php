<?php

namespace Database\Seeders;

use App\Models\Sport;
use Illuminate\Database\Seeder;

class SportSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $sports = [
            ['key' => 'football_5', 'name' => 'Fútbol 5'],
            ['key' => 'football_7', 'name' => 'Fútbol 7'],
            ['key' => 'padel', 'name' => 'Pádel'],
            ['key' => 'basketball', 'name' => 'Baloncesto'],
            ['key' => 'tennis', 'name' => 'Tenis'],
            ['key' => 'volleyball', 'name' => 'Voleibol'],
            ['key' => 'wallyball', 'name' => 'Wally'],
            ['key' => 'fronton', 'name' => 'Frontón'],
        ];

        foreach ($sports as $sport) {
            Sport::updateOrCreate(['key' => $sport['key']], $sport);
        }
    }
}
