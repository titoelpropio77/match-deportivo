<?php

namespace Tests\Feature;

use App\Models\Sport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FavoriteSportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_player_registers_with_favorite_sports(): void
    {
        $futbol = Sport::query()->create(['key' => 'futbol_5', 'name' => 'Fútbol 5']);
        $wally = Sport::query()->create(['key' => 'wally', 'name' => 'Wally']);
        Sport::query()->create(['key' => 'padel', 'name' => 'Pádel']);

        $response = $this->postJson('/api/register', [
            'name' => 'Ana Rojas',
            'email' => 'ana@test.com',
            'gender' => 'female',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'favorite_sport_ids' => [$wally->id, $futbol->id],
        ])->assertCreated()
            ->assertJsonCount(2, 'user.favorite_sports')
            ->assertJsonPath('user.favorite_sports.0.name', 'Fútbol 5')
            ->assertJsonPath('user.favorite_sports.1.name', 'Wally');

        $this->withToken($response->json('token'))
            ->getJson('/api/me')
            ->assertJsonCount(2, 'user.favorite_sports');
    }

    public function test_favorites_are_optional_and_must_exist(): void
    {
        $base = [
            'name' => 'Beto',
            'gender' => 'male',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ];

        $this->postJson('/api/register', [...$base, 'email' => 'beto@test.com'])
            ->assertCreated()
            ->assertJsonCount(0, 'user.favorite_sports');

        $this->postJson('/api/register', [...$base, 'email' => 'beto2@test.com', 'favorite_sport_ids' => [999]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('favorite_sport_ids.0');
    }

    public function test_favorites_are_edited_from_the_profile_and_shown_on_it(): void
    {
        $futbol = Sport::query()->create(['key' => 'futbol_5', 'name' => 'Fútbol 5']);
        $padel = Sport::query()->create(['key' => 'padel', 'name' => 'Pádel']);
        $player = User::factory()->create();
        $player->favoriteSports()->sync([$futbol->id]);
        Sanctum::actingAs($player);

        $this->patchJson('/api/me', ['favorite_sport_ids' => [$padel->id]])
            ->assertOk()
            ->assertJsonCount(1, 'data.favorite_sports')
            ->assertJsonPath('data.favorite_sports.0.name', 'Pádel');

        // Other fields alone do not touch the favorites.
        $this->patchJson('/api/me', ['nickname' => 'Pepe'])->assertJsonCount(1, 'data.favorite_sports');

        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/users/{$player->id}/profile")->assertJsonPath('data.user.favorite_sports.0.name', 'Pádel');
    }
}
