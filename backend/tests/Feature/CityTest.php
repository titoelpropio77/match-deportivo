<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Court;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CityTest extends TestCase
{
    use RefreshDatabase;

    public function test_cities_are_seeded_by_the_migration_and_listed(): void
    {
        $response = $this->getJson('/api/cities')->assertOk();

        $response->assertJsonFragment([
            'key' => 'santa_cruz_de_la_sierra',
            'name' => 'Santa Cruz de la Sierra',
            'department' => 'Santa Cruz',
        ]);
        $this->assertSame(
            ['Beni', 'Chuquisaca', 'Cochabamba', 'La Paz', 'Oruro', 'Pando', 'Potosí', 'Santa Cruz', 'Tarija'],
            collect($response->json('data'))->pluck('department')->unique()->values()->all(),
        );
    }

    public function test_inactive_cities_are_hidden(): void
    {
        City::query()->where('key', 'cobija')->update(['is_active' => false]);

        $this->getJson('/api/cities')
            ->assertOk()
            ->assertJsonMissing(['key' => 'cobija']);
    }

    public function test_courts_can_be_filtered_by_city(): void
    {
        $santaCruz = City::query()->where('key', 'santa_cruz_de_la_sierra')->firstOrFail();
        $laPaz = City::query()->where('key', 'la_paz')->firstOrFail();

        $this->makeCourt('Arena Santa Cruz', $santaCruz);
        $this->makeCourt('Arena La Paz', $laPaz);

        $this->getJson('/api/courts?city_id='.$laPaz->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Arena La Paz')
            ->assertJsonPath('data.0.city.name', 'La Paz');

        $this->getJson('/api/courts')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_filtering_by_unknown_city_fails_validation(): void
    {
        $this->getJson('/api/courts?city_id=999999')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('city_id');
    }

    private function makeCourt(string $name, City $city): Court
    {
        return Court::query()->create([
            'city_id' => $city->id,
            'name' => $name,
            'address' => 'Centro',
            'latitude' => $city->latitude,
            'longitude' => $city->longitude,
            'opening_time' => '08:00',
            'closing_time' => '22:00',
        ]);
    }
}
