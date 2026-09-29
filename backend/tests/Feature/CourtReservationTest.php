<?php

namespace Tests\Feature;

use App\Models\Court;
use App\Models\CourtField;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CourtReservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_a_court_blocks_that_hour_for_every_sport(): void
    {
        $user = User::factory()->create();
        $wally = Sport::query()->create(['key' => 'wallyball', 'name' => 'Wally']);
        $fronton = Sport::query()->create(['key' => 'fronton', 'name' => 'Frontón']);

        $court = Court::query()->create([
            'name' => 'Complejo Wally',
            'address' => 'Santa Cruz',
            'latitude' => -17.7,
            'longitude' => -63.1,
            'opening_time' => '08:00',
            'closing_time' => '22:00',
        ]);

        $field = CourtField::query()->create([
            'court_id' => $court->id,
            'name' => 'Cancha 1',
            'price_per_hour' => 60,
        ]);
        $field->sports()->sync([$wally->id, $fronton->id]);

        $other = CourtField::query()->create([
            'court_id' => $court->id,
            'name' => 'Cancha 2',
            'price_per_hour' => 60,
        ]);
        $other->sports()->sync([$wally->id, $fronton->id]);

        Sanctum::actingAs($user);

        $this->postJson('/api/court-reservations', [
            'court_field_id' => $field->id,
            'sport_id' => $wally->id,
            'date' => now()->addDay()->toDateString(),
            'start_time' => '19:00',
            'hours' => 2,
        ])->assertCreated()
            ->assertJsonPath('data.amount', 120);

        $date = now()->addDay()->toDateString();

        $this->getJson("/api/court-fields/{$field->id}/availability?date={$date}")
            ->assertOk()
            ->assertJsonPath('data.slots.11.available', false)
            ->assertJsonPath('data.slots.12.available', false)
            ->assertJsonPath('data.slots.10.available', true);

        $this->postJson('/api/court-reservations', [
            'court_field_id' => $field->id,
            'sport_id' => $fronton->id,
            'date' => $date,
            'start_time' => '19:00',
            'hours' => 1,
        ])->assertStatus(422);

        $this->postJson('/api/court-reservations', [
            'court_field_id' => $other->id,
            'sport_id' => $fronton->id,
            'date' => $date,
            'start_time' => '19:00',
            'hours' => 1,
        ])->assertCreated()
            ->assertJsonPath('data.amount', 60);
    }
}
