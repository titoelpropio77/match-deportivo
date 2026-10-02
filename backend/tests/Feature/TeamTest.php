<?php

namespace Tests\Feature;

use App\Models\Court;
use App\Models\MatchLevel;
use App\Models\Sport;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeamTest extends TestCase
{
    use RefreshDatabase;

    private User $captain;

    private Sport $futbol;

    private Sport $padel;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->captain = User::factory()->create(['name' => 'Capitán']);
        $this->futbol = Sport::query()->create(['key' => 'futbol_5', 'name' => 'Fútbol 5']);
        $this->padel = Sport::query()->create(['key' => 'padel', 'name' => 'Pádel']);
        Sanctum::actingAs($this->captain);
    }

    public function test_a_team_is_created_with_logo_and_members_and_the_creator_is_captain(): void
    {
        [$ana, $beto] = User::factory()->count(2)->create();

        $response = $this->post('/api/teams', [
            'name' => 'Los Tigres',
            'short_name' => 'tig',
            'sport_id' => $this->futbol->id,
            'gender' => 'male',
            'primary_color' => '#FF6F00',
            'description' => 'Jugamos los jueves.',
            // Real 1x1 PNG (the container has no GD for UploadedFile::fake()->image()).
            'logo' => UploadedFile::fake()->createWithContent('logo.png', base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
            )),
            'member_ids' => [$ana->id, $beto->id, $this->captain->id],
        ], ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('data.name', 'Los Tigres')
            ->assertJsonPath('data.short_name', 'TIG')
            ->assertJsonPath('data.sport.name', 'Fútbol 5')
            ->assertJsonPath('data.gender', 'male')
            ->assertJsonPath('data.members_count', 3)
            ->assertJsonPath('data.members.0.role', 'captain')
            ->assertJsonPath('data.members.0.user.id', $this->captain->id)
            ->assertJsonPath('data.is_member', true);

        $this->assertNotNull($response->json('data.logo_url'));
        Storage::disk('public')->assertExists(Team::query()->first()->logo_path);

        $this->getJson('/api/teams')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.members_count', 3);

        Sanctum::actingAs($ana);
        $this->getJson('/api/teams')->assertJsonCount(1, 'data');
    }

    public function test_only_the_captain_edits_and_manages_members(): void
    {
        $team = $this->makeTeam('Los Tigres');
        $player = User::factory()->create();
        $outsider = User::factory()->create();

        $this->postJson("/api/teams/{$team->id}/members", ['user_id' => $player->id, 'jersey_number' => 10, 'position' => 'Delantero'])
            ->assertCreated()
            ->assertJsonPath('data.members_count', 2)
            ->assertJsonPath('data.members.1.jersey_number', 10);
        $this->postJson("/api/teams/{$team->id}/members", ['user_id' => $player->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_id');

        $this->postJson("/api/teams/{$team->id}", ['name' => 'Tigres FC'])->assertOk()->assertJsonPath('data.name', 'Tigres FC');

        Sanctum::actingAs($outsider);
        $this->postJson("/api/teams/{$team->id}", ['name' => 'Hackeado'])->assertForbidden();
        $this->postJson("/api/teams/{$team->id}/members", ['user_id' => $outsider->id])->assertForbidden();
        $this->deleteJson("/api/teams/{$team->id}")->assertForbidden();

        // A member updates their own number and can leave; the captain cannot leave.
        Sanctum::actingAs($player);
        $this->patchJson("/api/teams/{$team->id}/members/{$player->id}", ['jersey_number' => 7])
            ->assertOk()
            ->assertJsonPath('data.members.1.jersey_number', 7);
        $this->deleteJson("/api/teams/{$team->id}/members/{$player->id}")->assertOk()->assertJsonPath('data.members_count', 1);

        Sanctum::actingAs($this->captain);
        $this->deleteJson("/api/teams/{$team->id}/members/{$this->captain->id}")->assertUnprocessable();
        $this->deleteJson("/api/teams/{$team->id}")->assertNoContent();
        $this->assertDatabaseCount('teams', 0);
    }

    public function test_teams_are_searched_by_name_or_abbreviation_and_sport(): void
    {
        $this->makeTeam('Los Tigres', 'TIG');
        $this->makeTeam('Águilas del Norte', 'AGN');
        $this->makeTeam('Tigres Pádel', null, $this->padel);

        $this->getJson('/api/teams/search?query=tigres')->assertJsonCount(2, 'data');
        $this->getJson("/api/teams/search?query=tigres&sport_id={$this->futbol->id}")
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Los Tigres');
        $this->getJson('/api/teams/search?query=agn')->assertJsonPath('data.0.name', 'Águilas del Norte');
    }

    public function test_adding_a_team_to_a_match_adds_all_its_players(): void
    {
        $team = $this->makeTeam('Los Tigres');
        $members = User::factory()->count(3)->create();
        foreach ($members as $member) {
            $team->members()->create(['user_id' => $member->id, 'role' => 'player']);
        }
        $extra = User::factory()->create();

        $response = $this->postJson('/api/matches', $this->matchPayload([
            'team_ids' => [$team->id],
            // Also listed individually: must not be added twice.
            'player_ids' => [$members[0]->id, $extra->id],
        ]))->assertCreated()
            ->assertJsonPath('data.teams.0.name', 'Los Tigres')
            ->assertJsonPath('data.missing_players', 10 - 5);

        // Captain (organizer, member of the team) + 3 members + extra player.
        $playerIds = collect($response->json('data.players'))->pluck('user_id')->sort()->values()->all();
        $this->assertSame(
            collect([$this->captain->id, ...$members->pluck('id'), $extra->id])->sort()->values()->all(),
            $playerIds
        );

        $this->getJson('/api/matches/'.$response->json('data.id'))->assertJsonPath('data.teams.0.short_name', null);
    }

    public function test_teams_must_be_of_the_match_sport_and_fit_the_player_limit(): void
    {
        $padelTeam = $this->makeTeam('Pádel Club', null, $this->padel);
        $this->postJson('/api/matches', $this->matchPayload(['team_ids' => [$padelTeam->id]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('team_ids');

        $big = $this->makeTeam('Plantel grande');
        foreach (User::factory()->count(5)->create() as $member) {
            $big->members()->create(['user_id' => $member->id, 'role' => 'player']);
        }
        $this->postJson('/api/matches', $this->matchPayload(['team_ids' => [$big->id], 'max_players' => 4]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('max_players');
    }

    private function makeTeam(string $name, ?string $short = null, ?Sport $sport = null): Team
    {
        $team = Team::query()->create([
            'owner_id' => $this->captain->id,
            'sport_id' => ($sport ?? $this->futbol)->id,
            'name' => $name,
            'short_name' => $short,
        ]);
        $team->members()->create(['user_id' => $this->captain->id, 'role' => 'captain']);

        return $team;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function matchPayload(array $overrides = []): array
    {
        $court = Court::query()->create([
            'name' => 'Arena Norte',
            'address' => 'Santa Cruz',
            'latitude' => -17.78,
            'longitude' => -63.18,
            'opening_time' => '08:00',
            'closing_time' => '22:00',
        ]);

        return [
            'sport_id' => $this->futbol->id,
            'level_id' => MatchLevel::query()->create(['key' => 'basico'.uniqid(), 'name' => 'Básico', 'order' => 1])->id,
            'court_id' => $court->id,
            'gender' => 'mixed',
            'start_time' => now()->addDay()->setTime(18, 0)->toIso8601String(),
            'end_time' => now()->addDay()->setTime(20, 0)->toIso8601String(),
            'max_players' => 10,
            ...$overrides,
        ];
    }
}
