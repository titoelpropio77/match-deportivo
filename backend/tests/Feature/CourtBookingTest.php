<?php

namespace Tests\Feature;

use App\Models\Court;
use App\Models\CourtField;
use App\Models\CourtReservation;
use App\Models\MatchLevel;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CourtBookingTest extends TestCase
{
    use RefreshDatabase;

    private Sport $futbol;

    private CourtField $fieldA;

    private CourtField $fieldB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->futbol = Sport::query()->create(['key' => 'futbol_5', 'name' => 'Fútbol 5']);
        $court = Court::query()->create([
            'name' => 'Arena Norte',
            'address' => 'Santa Cruz',
            'latitude' => -17.7,
            'longitude' => -63.1,
            'opening_time' => '08:00',
            'closing_time' => '22:00',
        ]);
        $this->fieldA = CourtField::query()->create(['court_id' => $court->id, 'name' => 'Cancha 1', 'price_per_hour' => 50]);
        $this->fieldB = CourtField::query()->create(['court_id' => $court->id, 'name' => 'Cancha 2', 'price_per_hour' => 70]);
        $this->fieldA->sports()->sync([$this->futbol->id]);
        $this->fieldB->sports()->sync([$this->futbol->id]);
    }

    private function item(CourtField $field, string $start, int $hours, ?string $date = null): array
    {
        return [
            'court_field_id' => $field->id,
            'sport_id' => $this->futbol->id,
            'date' => $date ?? now()->addDay()->toDateString(),
            'start_time' => $start,
            'hours' => $hours,
        ];
    }

    public function test_several_courts_and_ranges_are_booked_and_paid_together(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/court-bookings', ['items' => [
            $this->item($this->fieldA, '09:00', 3),
            $this->item($this->fieldA, '18:00', 1),
            $this->item($this->fieldB, '09:00', 2),
        ]])->assertCreated()
            ->assertJsonPath('data.amount', 50 * 3 + 50 + 70 * 2)
            ->assertJsonPath('data.hours', 6)
            ->assertJsonPath('data.status', 'pending_payment')
            ->assertJsonCount(3, 'data.reservations');

        $code = $response->json('data.code');
        $this->assertSame(3, CourtReservation::query()->where('booking_code', $code)->count());
        $response->assertJsonPath('data.reservations.0.payment_reference', $code);

        $date = now()->addDay()->toDateString();
        $this->getJson("/api/court-fields/{$this->fieldA->id}/availability?date={$date}")
            ->assertJsonPath('data.slots.1.status', 'reserved')
            ->assertJsonPath('data.slots.3.status', 'reserved')
            ->assertJsonPath('data.slots.4.available', true);

        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/court-bookings/{$code}/pay")->assertForbidden();

        Sanctum::actingAs($user);
        $this->postJson("/api/court-bookings/{$code}/pay")
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');
        $this->assertSame(3, CourtReservation::query()->where('booking_code', $code)->where('status', 'paid')->count());
    }

    public function test_one_taken_range_rejects_the_whole_booking(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/court-bookings', ['items' => [$this->item($this->fieldB, '10:00', 1)]])->assertCreated();

        $this->postJson('/api/court-bookings', ['items' => [
            $this->item($this->fieldA, '09:00', 2),
            $this->item($this->fieldB, '09:00', 2),
        ]])->assertStatus(422)
            ->assertJsonValidationErrors('items.1.start_time');

        $this->assertSame(0, CourtReservation::query()->where('court_field_id', $this->fieldA->id)->count());
    }

    public function test_overlapping_ranges_within_the_same_booking_are_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/court-bookings', ['items' => [
            $this->item($this->fieldA, '09:00', 2),
            $this->item($this->fieldA, '10:00', 1),
        ]])->assertStatus(422)
            ->assertJsonValidationErrors('items.1.start_time');
    }

    public function test_cancelling_a_booking_releases_all_its_ranges(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $code = $this->postJson('/api/court-bookings', ['items' => [
            $this->item($this->fieldA, '09:00', 1),
            $this->item($this->fieldB, '09:00', 1),
        ]])->json('data.code');

        $this->postJson("/api/court-bookings/{$code}/cancel")->assertNoContent();

        $this->assertSame(2, CourtReservation::query()->where('booking_code', $code)->where('status', 'cancelled')->count());
        $this->postJson("/api/court-bookings/{$code}/pay")->assertStatus(422);
    }

    public function test_a_match_can_be_created_from_a_booking_once_and_is_linked_in_my_reservations(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $code = $this->postJson('/api/court-bookings', ['items' => [
            $this->item($this->fieldA, '17:00', 2),
            $this->item($this->fieldB, '17:00', 2),
        ]])->json('data.code');

        $level = MatchLevel::query()->create(['key' => 'basico', 'name' => 'Básico', 'order' => 1]);
        $payload = [
            'sport_id' => $this->futbol->id,
            'level_id' => $level->id,
            'court_id' => $this->fieldA->court_id,
            'court_field_ids' => [$this->fieldA->id, $this->fieldB->id],
            'gender' => 'mixed',
            'start_time' => now()->addDay()->setTime(17, 0)->toIso8601String(),
            'end_time' => now()->addDay()->setTime(19, 0)->toIso8601String(),
            'max_players' => 10,
            'booking_code' => $code,
        ];

        $matchId = $this->postJson('/api/matches', $payload)
            ->assertCreated()
            ->assertJsonPath('data.booking_code', $code)
            ->json('data.id');

        $this->getJson('/api/court-reservations')
            ->assertJsonPath('data.0.match_id', $matchId)
            ->assertJsonPath('data.1.match_id', $matchId);

        // Only one match per booking.
        $this->postJson('/api/matches', $payload)->assertUnprocessable()->assertJsonValidationErrors('booking_code');

        // Someone else's booking cannot be used.
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/matches', $payload)->assertUnprocessable()->assertJsonValidationErrors('booking_code');
    }
}
