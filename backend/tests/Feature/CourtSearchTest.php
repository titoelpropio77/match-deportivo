<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Court;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourtSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_courts_are_searched_by_name_address_or_city(): void
    {
        $santaCruz = City::query()->where('key', 'santa_cruz_de_la_sierra')->firstOrFail();
        $cochabamba = City::query()->where('key', 'cochabamba')->firstOrFail();
        $this->makeCourt('Arena Norte', 'Zona Norte', $santaCruz);
        $this->makeCourt('Complejo Wally Sur', 'Av. Equipetrol', $santaCruz);
        $this->makeCourt('Club Central', 'Calle Sucre', $cochabamba);

        $this->getJson('/api/courts?search=ARENA')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Arena Norte');
        $this->getJson('/api/courts?search=equipetrol')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Complejo Wally Sur');
        $this->getJson('/api/courts?search=cochabamba')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Club Central');
        $this->getJson('/api/courts?search=inexistente')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_an_empty_search_lists_every_court(): void
    {
        $santaCruz = City::query()->where('key', 'santa_cruz_de_la_sierra')->firstOrFail();
        $this->makeCourt('Arena Norte', 'Zona Norte', $santaCruz);
        $this->makeCourt('Complejo Wally Sur', 'Av. Equipetrol', $santaCruz);

        $this->getJson('/api/courts?search=')->assertOk()->assertJsonCount(2, 'data');
    }

    private function makeCourt(string $name, string $address, City $city): Court
    {
        return Court::query()->create([
            'city_id' => $city->id,
            'name' => $name,
            'address' => $address,
            'latitude' => $city->latitude,
            'longitude' => $city->longitude,
            'opening_time' => '08:00',
            'closing_time' => '22:00',
        ]);
    }
}
