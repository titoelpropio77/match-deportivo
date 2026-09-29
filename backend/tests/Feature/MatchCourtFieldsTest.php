<?php

namespace Tests\Feature;

use App\Models\Court;
use App\Models\CourtField;
use App\Models\MatchLevel;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MatchCourtFieldsTest extends TestCase
{
    use RefreshDatabase;

    private Sport $sport;

    private MatchLevel $level;

    private Court $arenaNorte;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
        $this->sport = Sport::query()->create(['key' => 'padel', 'name' => 'Pádel']);
        $this->level = MatchLevel::query()->create(['key' => 'basico', 'name' => 'Básico', 'order' => 1]);
        $this->arenaNorte = $this->makeCourt('Arena Norte');
    }

    public function test_a_match_can_use_several_courts_of_the_venue(): void
    {
        $field1 = $this->makeField($this->arenaNorte, 'Cancha 1');
        $field2 = $this->makeField($this->arenaNorte, 'Cancha 2');
        $this->makeField($this->arenaNorte, 'Cancha 3');

        $response = $this->postJson('/api/matches', $this->payload([
            'court_field_ids' => [$field2->id, $field1->id],
        ]))->assertCreated();

        $response->assertJsonCount(2, 'data.court_fields')
            ->assertJsonPath('data.court_fields.0.name', 'Cancha 1')
            ->assertJsonPath('data.court_fields.1.name', 'Cancha 2')
            ->assertJsonPath('data.court_fields.0.sports.0.name', 'Pádel');

        $matchId = $response->json('data.id');
        $this->getJson("/api/matches/{$matchId}")
            ->assertOk()
            ->assertJsonCount(2, 'data.court_fields');
    }

    public function test_courts_are_required_when_the_venue_has_them(): void
    {
        $this->makeField($this->arenaNorte, 'Cancha 1');

        $this->postJson('/api/matches', $this->payload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('court_field_ids');
    }

    public function test_a_venue_without_courts_does_not_require_them(): void
    {
        $this->postJson('/api/matches', $this->payload())
            ->assertCreated()
            ->assertJsonCount(0, 'data.court_fields');
    }

    public function test_courts_from_another_venue_are_rejected(): void
    {
        $this->makeField($this->arenaNorte, 'Cancha 1');
        $foreign = $this->makeField($this->makeCourt('Otro Centro'), 'Cancha X');

        $this->postJson('/api/matches', $this->payload(['court_field_ids' => [$foreign->id]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('court_field_ids.0');
    }

    public function test_the_courts_list_includes_each_venue_fields(): void
    {
        $this->makeField($this->arenaNorte, 'Cancha 2');
        $this->makeField($this->arenaNorte, 'Cancha 1');

        $this->getJson('/api/courts')
            ->assertOk()
            ->assertJsonPath('data.0.fields.0.name', 'Cancha 1')
            ->assertJsonPath('data.0.fields.1.name', 'Cancha 2');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'sport_id' => $this->sport->id,
            'level_id' => $this->level->id,
            'court_id' => $this->arenaNorte->id,
            'gender' => 'mixed',
            'start_time' => now()->addDay()->setTime(18, 0)->toIso8601String(),
            'end_time' => now()->addDay()->setTime(20, 0)->toIso8601String(),
            'max_players' => 8,
            ...$overrides,
        ];
    }

    private function makeCourt(string $name): Court
    {
        return Court::query()->create([
            'name' => $name,
            'address' => 'Santa Cruz',
            'latitude' => -17.78,
            'longitude' => -63.18,
            'opening_time' => '08:00',
            'closing_time' => '22:00',
        ]);
    }

    private function makeField(Court $court, string $name): CourtField
    {
        $field = $court->fields()->create(['name' => $name, 'price_per_hour' => 80]);
        $field->sports()->sync([$this->sport->id]);

        return $field;
    }
}
