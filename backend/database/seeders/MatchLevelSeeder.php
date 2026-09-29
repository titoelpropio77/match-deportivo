<?php

namespace Database\Seeders;

use App\Models\MatchLevel;
use Illuminate\Database\Seeder;

class MatchLevelSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $levels = [
            ['key' => 'basico', 'name' => 'Básico', 'order' => 1],
            ['key' => 'basico_intermedio', 'name' => 'Básico/Intermedio', 'order' => 2],
            ['key' => 'intermedio', 'name' => 'Intermedio', 'order' => 3],
            ['key' => 'intermedio_avanzado', 'name' => 'Intermedio Avanzado', 'order' => 4],
            ['key' => 'avanzado', 'name' => 'Avanzado', 'order' => 5],
            ['key' => 'elite', 'name' => 'Élite', 'order' => 6],
        ];

        foreach ($levels as $level) {
            MatchLevel::updateOrCreate(['key' => $level['key']], $level);
        }
    }
}
