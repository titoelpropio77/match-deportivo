<?php

namespace Tests\Feature;

use App\Models\Court;
use App\Models\CourtFeature;
use App\Models\CourtField;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CourtSurchargeTest extends TestCase
{
    use RefreshDatabase;

    private Sport $futbol;

    private Court $court;

    protected function setUp(): void
    {
        parent::setUp();

        $this->futbol = Sport::query()->create(['key' => 'futbol_5', 'name' => 'Fútbol 5']);
        $this->court = Court::query()->create([
            'name' => 'Arena Norte',
            'address' => 'Santa Cruz',
            'latitude' => -17.7,
            'longitude' => -63.1,
            'opening_time' => '08:00',
            'closing_time' => '23:00',
        ]);
    }

    /**
     * @param  list<string>  $features  CourtFeature keys (seeded by the migration)
     */
    private function field(array $attributes = [], array $features = []): CourtField
    {
        $field = CourtField::query()->create([
            'court_id' => $this->court->id,
            'name' => 'Cancha 1',
            'price_per_hour' => 50,
            ...$attributes,
        ]);
        $field->sports()->sync([$this->futbol->id]);
        $field->features()->sync(CourtFeature::query()->whereIn('key', $features)->pluck('id'));

        return $field;
    }

    private function book(CourtField $field, string $start, int $hours, array $extra = [])
    {
        Sanctum::actingAs(User::factory()->create());

        return $this->postJson('/api/court-bookings', ['items' => [[
            'court_field_id' => $field->id,
            'sport_id' => $this->futbol->id,
            'date' => now()->addDay()->toDateString(),
            'start_time' => $start,
            'hours' => $hours,
            ...$extra,
        ]]]);
    }

    public function test_night_hours_add_the_lighting_price(): void
    {
        $field = $this->field([
            'lighting_price' => 10,
            'lighting_from' => '18:00',
        ], [CourtFeature::LIGHTING]);

        // 17:00–20:00: only 18–19 and 19–20 are lit.
        $this->book($field, '17:00', 3)
            ->assertCreated()
            ->assertJsonPath('data.amount', 50 * 3 + 10 * 2)
            ->assertJsonPath('data.reservations.0.lighting_amount', 20)
            ->assertJsonPath('data.reservations.0.air_conditioning', false);

        $this->book($field, '09:00', 2)
            ->assertCreated()
            ->assertJsonPath('data.amount', 100)
            ->assertJsonPath('data.reservations.0.lighting_amount', 0);

        $this->getJson("/api/court-fields/{$field->id}/availability?date=".now()->addDay()->toDateString())
            ->assertJsonPath('data.field.lighting_price', 10)
            ->assertJsonPath('data.field.lighting_from', '18:00')
            ->assertJsonPath('data.slots.9.start', '17:00')
            ->assertJsonPath('data.slots.9.lighting', false)
            ->assertJsonPath('data.slots.10.lighting', true);
    }

    public function test_features_added_to_the_catalog_are_listed(): void
    {
        CourtFeature::query()->create(['key' => 'parking', 'name' => 'Estacionamiento', 'icon' => 'fas fa-parking']);
        $field = $this->field([], ['parking', 'covered']);

        $this->getJson("/api/court-fields/{$field->id}/availability?date=".now()->addDay()->toDateString())
            ->assertOk()
            ->assertJsonPath('data.field.features', [
                ['key' => 'parking', 'label' => 'Estacionamiento', 'icon' => 'fas fa-parking'],
                ['key' => 'covered', 'label' => 'Techada', 'icon' => 'fas fa-home'],
            ]);
    }

    public function test_lighting_is_not_charged_without_the_feature(): void
    {
        $field = $this->field(['lighting_price' => 10, 'lighting_from' => '18:00']);

        $this->book($field, '19:00', 1)
            ->assertCreated()
            ->assertJsonPath('data.amount', 50);
    }

    public function test_air_conditioning_is_charged_only_when_chosen(): void
    {
        $field = $this->field([
            'air_conditioning_price' => 15,
        ], [CourtFeature::AIR_CONDITIONING]);

        $this->book($field, '09:00', 2, ['air_conditioning' => true])
            ->assertCreated()
            ->assertJsonPath('data.amount', 50 * 2 + 15 * 2)
            ->assertJsonPath('data.reservations.0.air_conditioning', true)
            ->assertJsonPath('data.reservations.0.air_conditioning_amount', 30)
            ->assertJsonPath('data.reservations.0.field.air_conditioning_price', 15);

        $this->book($field, '12:00', 1)
            ->assertCreated()
            ->assertJsonPath('data.amount', 50)
            ->assertJsonPath('data.reservations.0.air_conditioning', false);
    }

    public function test_air_conditioning_is_rejected_on_courts_without_it(): void
    {
        $field = $this->field();

        $this->book($field, '09:00', 1, ['air_conditioning' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.air_conditioning');
    }

    public function test_air_conditioning_and_lighting_add_up(): void
    {
        $field = $this->field([
            'air_conditioning_price' => 15,
            'lighting_price' => 10,
            'lighting_from' => '18:30',
        ], [CourtFeature::AIR_CONDITIONING, CourtFeature::LIGHTING]);

        // 18–19 is partly after 18:30, so it is lit.
        $this->book($field, '18:00', 2, ['air_conditioning' => true])
            ->assertCreated()
            ->assertJsonPath('data.amount', 50 * 2 + 15 * 2 + 10 * 2);
    }
}
