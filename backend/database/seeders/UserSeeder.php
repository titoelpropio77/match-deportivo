<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $positions = ['Rematador', 'Colocador', 'Servidor', 'Central', 'Líbero'];

        $users = [
            ['name' => 'modesto test', 'nickname' => 'Modesto', 'email' => 'modesto.test@example.com', 'phone' => '70011122', 'gender' => 'male'],
            ['name' => 'Carlos Mamani', 'nickname' => 'Charly', 'email' => 'carlos.mamani@example.com', 'phone' => '70011122', 'gender' => 'male'],
            ['name' => 'Ana Rojas', 'nickname' => 'Anita', 'email' => 'ana.rojas@example.com', 'phone' => '70022233', 'gender' => 'female'],
            ['name' => 'Luis Fernandez', 'nickname' => 'Lucho', 'email' => 'luis.fernandez@example.com', 'phone' => '70033344', 'gender' => 'male'],
            ['name' => 'Maria Suarez', 'nickname' => 'Mafe', 'email' => 'maria.suarez@example.com', 'phone' => '70044455', 'gender' => 'female'],
            ['name' => 'Jorge Vaca', 'nickname' => 'Jorgito', 'email' => 'jorge.vaca@example.com', 'phone' => '70055566', 'gender' => 'male'],
            ['name' => 'Paola Gutierrez', 'nickname' => 'Pao', 'email' => 'paola.gutierrez@example.com', 'phone' => '70066677', 'gender' => 'female'],
            ['name' => 'Diego Salazar', 'nickname' => 'Diegote', 'email' => 'diego.salazar@example.com', 'phone' => '70077788', 'gender' => 'male'],
            ['name' => 'Camila Rivero', 'nickname' => 'Cami', 'email' => 'camila.rivero@example.com', 'phone' => '70088899', 'gender' => 'female'],
            ['name' => 'Fernando Justiniano', 'nickname' => 'Fer', 'email' => 'fernando.justiniano@example.com', 'phone' => '70099900', 'gender' => 'male'],
            ['name' => 'Valeria Áñez', 'nickname' => 'Vale', 'email' => 'valeria.anez@example.com', 'phone' => '70000011', 'gender' => 'female'],
        ];

        foreach ($users as $index => $data) {
            User::factory()->create([
                'name' => $data['name'],
                'nickname' => $data['nickname'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'gender' => $data['gender'],
                'preferred_position' => $positions[$index % count($positions)],
                'password' => Hash::make('12345678'),
            ]);
        }

        // Extra random users for wider test coverage.
        User::factory(15)
            ->state(fn () => [
                'gender' => fake()->randomElement(['male', 'female']),
            ])
            ->create([
                'password' => Hash::make('password'),
            ]);
    }
}
