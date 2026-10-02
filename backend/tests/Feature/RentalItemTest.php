<?php

namespace Tests\Feature;

use App\Models\Court;
use App\Models\CourtField;
use App\Models\CourtReservation;
use App\Models\RentalItem;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RentalItemTest extends TestCase
{
    use RefreshDatabase;

    private Sport $voley;

    private Sport $padel;

    private Court $court;

    private CourtField $field;

    private RentalItem $ball;

    private RentalItem $racket;

    protected function setUp(): void
    {
        parent::setUp();

        $this->voley = Sport::query()->create(['key' => 'volleyball', 'name' => 'Voleibol']);
        $this->padel = Sport::query()->create(['key' => 'padel', 'name' => 'Pádel']);
        $this->court = Court::query()->create([
            'name' => 'Complejo Wally Sur',
            'address' => 'Santa Cruz',
            'latitude' => -17.7,
            'longitude' => -63.1,
            'opening_time' => '08:00',
            'closing_time' => '22:00',
        ]);
        $this->field = CourtField::query()->create(['court_id' => $this->court->id, 'name' => 'Cancha 1', 'price_per_hour' => 60]);
        $this->field->sports()->sync([$this->voley->id, $this->padel->id]);

        // Flat: once per reservation. Per hour: × hours.
        $this->ball = RentalItem::query()->create([
            'court_id' => $this->court->id, 'sport_id' => $this->voley->id,
            'name' => 'Pelota de vóley', 'price' => 10, 'price_type' => 'flat', 'stock' => 2,
        ]);
        $this->racket = RentalItem::query()->create([
            'court_id' => $this->court->id, 'sport_id' => $this->padel->id,
            'name' => 'Raqueta de pádel', 'price' => 15, 'price_type' => 'per_hour', 'stock' => null,
        ]);
    }

    /**
     * @param  list<array{rental_item_id: int, quantity: int}>  $rentals
     * @return array<string, mixed>
     */
    private function item(Sport $sport, string $start, int $hours, array $rentals = [], ?CourtField $field = null): array
    {
        return [
            'court_field_id' => ($field ?? $this->field)->id,
            'sport_id' => $sport->id,
            'date' => now()->addDay()->toDateString(),
            'start_time' => $start,
            'hours' => $hours,
            'rentals' => $rentals,
        ];
    }

    public function test_venue_lists_its_active_gear_by_sport(): void
    {
        RentalItem::query()->create([
            'court_id' => $this->court->id, 'sport_id' => $this->voley->id,
            'name' => 'Red vieja', 'price' => 5, 'is_active' => false,
        ]);

        $this->getJson("/api/courts/{$this->court->id}/rental-items")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Pelota de vóley')
            ->assertJsonPath('data.0.sport.name', 'Voleibol')
            ->assertJsonPath('data.0.price_type', 'flat');

        $this->getJson("/api/courts/{$this->court->id}/rental-items?sport_id={$this->padel->id}")
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Raqueta de pádel');
    }

    public function test_rented_gear_is_added_to_the_booking_total(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/court-bookings', ['items' => [
            $this->item($this->voley, '09:00', 2, [['rental_item_id' => $this->ball->id, 'quantity' => 2]]),
            $this->item($this->padel, '18:00', 2, [['rental_item_id' => $this->racket->id, 'quantity' => 4]]),
        ]])->assertCreated()
            // Courts 60×2 + 60×2, ball 10×2 (flat), rackets 15×4×2 (per hour).
            ->assertJsonPath('data.amount', 240 + 20 + 120)
            ->assertJsonPath('data.reservations.0.items_amount', 20)
            ->assertJsonPath('data.reservations.0.amount', 140)
            ->assertJsonPath('data.reservations.0.rentals.0.name', 'Pelota de vóley')
            ->assertJsonPath('data.reservations.1.rentals.0.amount', 120);

        $this->assertSame(2, CourtReservation::query()->where('booking_code', $response->json('data.code'))->withCount('items')->get()->sum('items_count'));

        $this->getJson('/api/court-reservations')->assertJsonPath('data.0.rentals.0.quantity', 2);
    }

    public function test_gear_must_belong_to_the_center_and_sport(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/court-bookings', ['items' => [
            $this->item($this->padel, '09:00', 1, [['rental_item_id' => $this->ball->id, 'quantity' => 1]]),
        ]])->assertUnprocessable()->assertJsonValidationErrors('items.0.rentals.0.rental_item_id');

        $other = Court::query()->create([
            'name' => 'Otro', 'address' => 'x', 'latitude' => 0, 'longitude' => 0,
            'opening_time' => '08:00', 'closing_time' => '22:00',
        ]);
        $foreign = RentalItem::query()->create([
            'court_id' => $other->id, 'sport_id' => $this->voley->id, 'name' => 'Pelota ajena', 'price' => 5,
        ]);
        $this->postJson('/api/court-bookings', ['items' => [
            $this->item($this->voley, '09:00', 1, [['rental_item_id' => $foreign->id, 'quantity' => 1]]),
        ]])->assertUnprocessable()->assertJsonValidationErrors('items.0.rentals.0.rental_item_id');

        $this->assertSame(0, CourtReservation::query()->count());
    }

    public function test_stock_is_shared_by_overlapping_reservations(): void
    {
        $fieldB = CourtField::query()->create(['court_id' => $this->court->id, 'name' => 'Cancha 2', 'price_per_hour' => 60]);
        $fieldB->sports()->sync([$this->voley->id]);

        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/court-bookings', ['items' => [
            $this->item($this->voley, '09:00', 2, [['rental_item_id' => $this->ball->id, 'quantity' => 1]]),
        ]])->assertCreated();

        // Only 1 of 2 balls left from 09:00 to 11:00, also within the same booking.
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/court-bookings', ['items' => [
            $this->item($this->voley, '10:00', 1, [['rental_item_id' => $this->ball->id, 'quantity' => 2]], $fieldB),
        ]])->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.rentals.0.quantity' => 'Solo quedan 1']);

        $this->postJson('/api/court-bookings', ['items' => [
            $this->item($this->voley, '10:00', 1, [['rental_item_id' => $this->ball->id, 'quantity' => 1]], $fieldB),
            $this->item($this->voley, '11:00', 1, [['rental_item_id' => $this->ball->id, 'quantity' => 2]]),
        ]])->assertCreated();

        // Expired holds give the gear back.
        $this->travel(CourtReservation::PAYMENT_WINDOW_MINUTES + 1)->minutes();
        $this->postJson('/api/court-bookings', ['items' => [
            $this->item($this->voley, '10:00', 1, [['rental_item_id' => $this->ball->id, 'quantity' => 2]], $fieldB),
        ]])->assertCreated();
    }
}
