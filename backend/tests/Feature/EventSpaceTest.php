<?php

namespace Tests\Feature;

use App\Models\Court;
use App\Models\EventSpace;
use App\Models\EventSpaceReservation;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventSpaceTest extends TestCase
{
    use RefreshDatabase;

    private Court $court;

    private EventSpace $grill;

    protected function setUp(): void
    {
        parent::setUp();

        $this->court = Court::query()->create([
            'name' => 'Complejo Wally Sur',
            'address' => 'Santa Cruz',
            'latitude' => -17.7,
            'longitude' => -63.1,
            'opening_time' => '08:00',
            'closing_time' => '22:00',
        ]);
        $this->grill = EventSpace::query()->create([
            'court_id' => $this->court->id,
            'name' => 'Parrillero La Brasa',
            'type' => 'grill',
            'price_per_hour' => 80,
            'capacity' => 25,
            'min_hours' => 2,
            'amenities' => ['grill', 'tables_chairs'],
            'closing_time' => '23:00',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'event_space_id' => $this->grill->id,
            'date' => now()->addDay()->toDateString(),
            'start_time' => '18:00',
            'hours' => 3,
            'guests' => 20,
            'event_type' => 'birthday',
            ...$overrides,
        ];
    }

    public function test_active_spaces_are_listed_with_their_venue_and_filtered_by_guests(): void
    {
        EventSpace::query()->create([
            'court_id' => $this->court->id,
            'name' => 'Salón cerrado',
            'type' => 'hall',
            'price_per_hour' => 150,
            'capacity' => 80,
            'is_active' => false,
        ]);

        $this->getJson('/api/event-spaces')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Parrillero La Brasa')
            ->assertJsonPath('data.0.type.label', 'Parrillero')
            ->assertJsonPath('data.0.amenities.0.label', 'Parrilla')
            ->assertJsonPath('data.0.venue.name', 'Complejo Wally Sur')
            ->assertJsonPath('data.0.opening_time', '08:00')
            ->assertJsonPath('data.0.closing_time', '23:00');

        $this->getJson('/api/event-spaces?guests=30')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/event-spaces?court_id={$this->court->id}")->assertJsonCount(1, 'data');
    }

    public function test_court_list_tells_whether_the_venue_rents_event_spaces(): void
    {
        $field = $this->court->fields()->create(['name' => 'Cancha 1', 'price_per_hour' => 60]);
        $sport = Sport::query()->create(['key' => 'wallyball', 'name' => 'Wally']);
        $field->sports()->sync([$sport->id]);

        $this->getJson('/api/court-fields')->assertJsonPath('data.0.venue.event_spaces_count', 1);
    }

    public function test_a_space_is_booked_paid_and_blocks_its_hours(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/event-space-reservations', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.amount', 240)
            ->assertJsonPath('data.status', 'pending_payment')
            ->assertJsonPath('data.event_type.label', 'Cumpleaños')
            ->assertJsonPath('data.space.venue.name', 'Complejo Wally Sur');
        $id = $response->json('data.id');
        $this->assertStringStartsWith('E', $response->json('data.code'));

        $date = now()->addDay()->toDateString();
        $this->getJson("/api/event-spaces/{$this->grill->id}/availability?date={$date}")
            ->assertJsonPath('data.slots.10.start', '18:00')
            ->assertJsonPath('data.slots.10.status', 'reserved')
            ->assertJsonPath('data.slots.12.status', 'reserved')
            ->assertJsonPath('data.slots.13.status', 'available');

        $this->postJson("/api/event-space-reservations/{$id}/pay")
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/event-space-reservations', $this->payload(['start_time' => '20:00', 'hours' => 2]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('start_time');
        $this->postJson("/api/event-space-reservations/{$id}/pay")->assertForbidden();

        Sanctum::actingAs($user);
        $this->getJson('/api/event-space-reservations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'paid');
    }

    public function test_capacity_minimum_hours_and_schedule_are_enforced(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/event-space-reservations', $this->payload(['guests' => 26]))
            ->assertUnprocessable()->assertJsonValidationErrors('guests');
        $this->postJson('/api/event-space-reservations', $this->payload(['hours' => 1]))
            ->assertUnprocessable()->assertJsonValidationErrors('hours');
        $this->postJson('/api/event-space-reservations', $this->payload(['start_time' => '21:00', 'hours' => 3]))
            ->assertUnprocessable()->assertJsonValidationErrors('start_time');
        $this->postJson('/api/event-space-reservations', $this->payload(['event_type' => 'wedding']))
            ->assertUnprocessable()->assertJsonValidationErrors('event_type');

        $this->grill->update(['is_active' => false]);
        $this->postJson('/api/event-space-reservations', $this->payload())
            ->assertUnprocessable()->assertJsonValidationErrors('event_space_id');
        $this->getJson("/api/event-spaces/{$this->grill->id}")->assertNotFound();
    }

    public function test_expired_and_cancelled_holds_release_the_hours(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $id = $this->postJson('/api/event-space-reservations', $this->payload())->json('data.id');
        $this->postJson("/api/event-space-reservations/{$id}/cancel")->assertNoContent();
        $this->assertSame(EventSpaceReservation::STATUS_CANCELLED, EventSpaceReservation::query()->find($id)->status);
        $this->getJson('/api/event-space-reservations')->assertJsonCount(0, 'data');

        $second = $this->postJson('/api/event-space-reservations', $this->payload())->assertCreated()->json('data.id');
        $this->travel(EventSpaceReservation::PAYMENT_WINDOW_MINUTES + 1)->minutes();

        $this->postJson("/api/event-space-reservations/{$second}/pay")->assertUnprocessable();
        $this->postJson('/api/event-space-reservations', $this->payload())->assertCreated();
    }
}
