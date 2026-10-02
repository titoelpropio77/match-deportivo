<?php

namespace Tests\Feature;

use App\Models\Court;
use App\Models\CourtField;
use App\Models\CourtReservation;
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
            ->assertJsonPath('data.slots.11.status', 'reserved')
            ->assertJsonPath('data.slots.11.sport.name', 'Wally')
            ->assertJsonPath('data.slots.12.available', false)
            ->assertJsonPath('data.slots.10.available', true)
            ->assertJsonPath('data.slots.10.sport', null)
            ->assertJsonCount(2, 'data.venue_fields');

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

    public function test_simulated_qr_payment_confirms_and_cancel_releases_the_slot(): void
    {
        [$user, $field, $sport] = $this->bookableField();
        $date = now()->addDay()->toDateString();
        Sanctum::actingAs($user);

        $id = $this->postJson('/api/court-reservations', [
            'court_field_id' => $field->id,
            'sport_id' => $sport->id,
            'date' => $date,
            'start_time' => '10:00',
            'hours' => 1,
        ])->assertCreated()
            ->assertJsonPath('data.status', 'pending_payment')
            ->json('data.id');

        $this->postJson("/api/court-reservations/{$id}/pay")
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        $this->postJson("/api/court-reservations/{$id}/cancel")->assertStatus(422);

        $pendingId = $this->postJson('/api/court-reservations', [
            'court_field_id' => $field->id,
            'sport_id' => $sport->id,
            'date' => $date,
            'start_time' => '11:00',
            'hours' => 1,
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/court-reservations/{$pendingId}/cancel")->assertNoContent();

        $this->getJson("/api/court-fields/{$field->id}/availability?date={$date}")
            ->assertJsonPath('data.slots.2.status', 'reserved')
            ->assertJsonPath('data.slots.3.available', true);

        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/court-reservations/{$pendingId}/pay")->assertForbidden();
    }

    public function test_unpaid_reservation_releases_the_slot_after_the_payment_window(): void
    {
        [$user, $field, $sport] = $this->bookableField();
        $date = now()->addDay()->toDateString();
        Sanctum::actingAs($user);

        $id = $this->postJson('/api/court-reservations', [
            'court_field_id' => $field->id,
            'sport_id' => $sport->id,
            'date' => $date,
            'start_time' => '10:00',
            'hours' => 1,
        ])->assertCreated()->json('data.id');

        $this->travel(CourtReservation::PAYMENT_WINDOW_MINUTES + 1)->minutes();

        $this->getJson("/api/court-fields/{$field->id}/availability?date={$date}")
            ->assertJsonPath('data.slots.2.available', true);

        $this->postJson("/api/court-reservations/{$id}/pay")->assertStatus(422);
    }

    public function test_hours_that_already_started_today_are_not_bookable(): void
    {
        [$user, $field, $sport] = $this->bookableField();
        $this->travelTo(now()->setTime(12, 30));
        Sanctum::actingAs($user);

        $this->getJson("/api/court-fields/{$field->id}/availability?date=".now()->toDateString())
            ->assertJsonPath('data.slots.4.status', 'past')
            ->assertJsonPath('data.slots.5.available', true);

        $this->postJson('/api/court-reservations', [
            'court_field_id' => $field->id,
            'sport_id' => $sport->id,
            'date' => now()->toDateString(),
            'start_time' => '12:00',
            'hours' => 1,
        ])->assertStatus(422);
    }

    public function test_user_lists_only_their_active_reservations_upcoming_first(): void
    {
        [$user, $field, $sport] = $this->bookableField();
        Sanctum::actingAs($user);

        $book = fn (string $date, string $start) => $this->postJson('/api/court-reservations', [
            'court_field_id' => $field->id,
            'sport_id' => $sport->id,
            'date' => $date,
            'start_time' => $start,
            'hours' => 1,
        ])->assertCreated()->json('data.id');

        $later = $book(now()->addDays(3)->toDateString(), '10:00');
        $sooner = $book(now()->addDay()->toDateString(), '18:00');
        $cancelled = $book(now()->addDays(2)->toDateString(), '09:00');
        $this->postJson("/api/court-reservations/{$later}/pay")->assertOk();
        $this->postJson("/api/court-reservations/{$cancelled}/cancel")->assertNoContent();

        $this->getJson('/api/court-reservations')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $sooner)
            ->assertJsonPath('data.1.id', $later)
            ->assertJsonPath('data.1.status', 'paid')
            ->assertJsonPath('data.1.field.venue.name', 'Canchas El Torneo');

        // A venue cancellation stays listed (after the upcoming ones) with its reason.
        CourtReservation::query()->whereKey($sooner)->update([
            'status' => CourtReservation::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancelled_by' => User::factory()->create()->id,
            'cancellation_reason' => 'Mantenimiento de la cancha',
        ]);

        $this->getJson('/api/court-reservations')
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $later)
            ->assertJsonPath('data.1.id', $sooner)
            ->assertJsonPath('data.1.cancelled_by_venue', true)
            ->assertJsonPath('data.1.cancellation_reason', 'Mantenimiento de la cancha');

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/court-reservations')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_venue_confirmed_reservations_block_the_slot_without_expiring(): void
    {
        [$user, $field, $sport] = $this->bookableField();
        $date = now()->addDay()->toDateString();

        CourtReservation::query()->create([
            'court_field_id' => $field->id,
            'customer_name' => 'Cliente presencial',
            'sport_id' => $sport->id,
            'reserved_on' => $date,
            'starts_at' => '10:00:00',
            'ends_at' => '11:00:00',
            'hours' => 1,
            'amount' => 80,
            'status' => CourtReservation::STATUS_CONFIRMED,
            'source' => 'admin',
        ]);

        $this->travel(CourtReservation::PAYMENT_WINDOW_MINUTES + 5)->minutes();

        $this->getJson("/api/court-fields/{$field->id}/availability?date={$date}")
            ->assertJsonPath('data.slots.2.status', 'reserved');
    }

    /**
     * @return array{User, CourtField, Sport}
     */
    private function bookableField(): array
    {
        $sport = Sport::query()->create(['key' => 'futbol_5', 'name' => 'Fútbol 5']);
        $court = Court::query()->create([
            'name' => 'Canchas El Torneo',
            'address' => 'Santa Cruz',
            'latitude' => -17.7,
            'longitude' => -63.1,
            'opening_time' => '08:00',
            'closing_time' => '22:00',
        ]);
        $field = CourtField::query()->create([
            'court_id' => $court->id,
            'name' => 'Cancha A',
            'price_per_hour' => 80,
        ]);
        $field->sports()->sync([$sport->id]);

        return [User::factory()->create(), $field, $sport];
    }
}
