<?php

namespace Tests\Feature;

use App\Models\Court;
use App\Models\Sport;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TournamentTest extends TestCase
{
    use RefreshDatabase;

    private User $captain;

    private Sport $futbol;

    private Court $court;

    protected function setUp(): void
    {
        parent::setUp();

        $this->captain = User::factory()->create();
        $this->futbol = Sport::query()->create(['key' => 'futbol_5', 'name' => 'Fútbol 5']);
        $this->court = Court::query()->create([
            'name' => 'Arena Norte',
            'address' => 'Santa Cruz',
            'latitude' => -17.78,
            'longitude' => -63.18,
            'opening_time' => '08:00',
            'closing_time' => '22:00',
        ]);
        Sanctum::actingAs($this->captain);
    }

    private function tournament(array $overrides = []): Tournament
    {
        return Tournament::query()->create([
            'court_id' => $this->court->id,
            'sport_id' => $this->futbol->id,
            'name' => 'Copa Primavera',
            'format' => 'league',
            'gender' => 'mixed',
            'entry_fee' => 200,
            'max_teams' => 8,
            'min_players_per_team' => 3,
            'max_players_per_team' => 10,
            'registration_closes_at' => now()->addDays(5),
            'starts_on' => now()->addDays(7)->toDateString(),
            'status' => Tournament::STATUS_OPEN,
            ...$overrides,
        ]);
    }

    private function team(string $name, int $players = 4, ?User $owner = null, ?Sport $sport = null): Team
    {
        $owner ??= $this->captain;
        $team = Team::query()->create(['owner_id' => $owner->id, 'sport_id' => ($sport ?? $this->futbol)->id, 'name' => $name]);
        $team->members()->create(['user_id' => $owner->id, 'role' => 'captain']);
        foreach (User::factory()->count($players - 1)->create() as $member) {
            $team->members()->create(['user_id' => $member->id, 'role' => 'player']);
        }

        return $team;
    }

    public function test_the_app_lists_published_tournaments_only(): void
    {
        $this->tournament(['name' => 'Copa Abierta']);
        $this->tournament(['name' => 'Borrador', 'status' => Tournament::STATUS_DRAFT]);
        $this->tournament(['name' => 'Copa Pasada', 'status' => Tournament::STATUS_FINISHED, 'ends_on' => now()->subDay()->toDateString()]);
        $draft = Tournament::query()->where('name', 'Borrador')->first();

        $this->getJson('/api/tournaments')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Copa Abierta')
            ->assertJsonPath('data.0.entry_fee', 200)
            ->assertJsonPath('data.0.spots_left', 8)
            ->assertJsonPath('data.0.accepts_registrations', true)
            ->assertJsonPath('data.0.venue.name', 'Arena Norte');

        $this->getJson('/api/tournaments?scope=finished')->assertJsonPath('data.0.name', 'Copa Pasada');
        $this->getJson("/api/tournaments/{$draft->id}")->assertNotFound();
    }

    public function test_a_captain_registers_the_team_pays_and_sees_it_in_my_tournaments(): void
    {
        $tournament = $this->tournament();
        $team = $this->team('Los Tigres');

        $registrationId = $this->postJson("/api/tournaments/{$tournament->id}/registrations", ['team_id' => $team->id])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending_payment')
            ->assertJsonPath('data.amount', 200)
            ->assertJsonPath('data.team.name', 'Los Tigres')
            ->json('data.id');

        // The pending registration holds a spot.
        $this->assertSame(7, $tournament->fresh()->spotsLeft());

        $this->postJson("/api/tournament-registrations/{$registrationId}/pay")
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        $this->getJson("/api/tournaments/{$tournament->id}")
            ->assertJsonPath('data.teams_count', 1)
            ->assertJsonPath('data.teams.0.name', 'Los Tigres')
            ->assertJsonPath('data.my_registrations.0.status', 'confirmed')
            ->assertJsonPath('data.standings.0.team', 'Los Tigres');

        // A teammate sees it too.
        Sanctum::actingAs($team->members()->where('role', 'player')->first()->user);
        $this->getJson('/api/tournaments/mine')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.tournament.name', 'Copa Primavera')
            ->assertJsonPath('data.0.registration.status', 'confirmed');
        // ...but cannot pay or cancel for the captain.
        $this->postJson("/api/tournament-registrations/{$registrationId}/cancel")->assertForbidden();
    }

    public function test_registration_rules(): void
    {
        $tournament = $this->tournament(['max_teams' => 1]);
        $padel = Sport::query()->create(['key' => 'padel', 'name' => 'Pádel']);
        $otherCaptain = User::factory()->create();

        $register = fn (Team $team) => $this->postJson("/api/tournaments/{$tournament->id}/registrations", ['team_id' => $team->id]);

        $register($this->team('Ajeno', 4, $otherCaptain))->assertUnprocessable()->assertJsonValidationErrors('team_id');
        $register($this->team('Pádel', 4, null, $padel))->assertUnprocessable()->assertJsonFragment(['Pádel no es un equipo del deporte del torneo.']);
        $register($this->team('Chico', 2))->assertUnprocessable()->assertJsonFragment(['El torneo pide al menos 3 jugadores; Chico tiene 2.']);
        $register($this->team('Enorme', 11))->assertUnprocessable();

        $tigres = $this->team('Los Tigres');
        $register($tigres)->assertCreated();
        $register($tigres)->assertUnprocessable()->assertJsonFragment(['Los Tigres ya está inscrito en este torneo.']);
        $register($this->team('Águilas'))->assertUnprocessable()->assertJsonFragment(['El torneo ya no tiene cupos.']);

        $closed = $this->tournament(['registration_closes_at' => now()->subHour()]);
        $this->postJson("/api/tournaments/{$closed->id}/registrations", ['team_id' => $tigres->id])
            ->assertUnprocessable()
            ->assertJsonFragment(['Las inscripciones de este torneo están cerradas.']);
    }

    public function test_unpaid_registrations_expire_and_the_team_can_sign_up_again(): void
    {
        $tournament = $this->tournament(['max_teams' => 1]);
        $team = $this->team('Los Tigres');
        $id = $this->postJson("/api/tournaments/{$tournament->id}/registrations", ['team_id' => $team->id])->json('data.id');

        $this->travel(TournamentRegistration::PAYMENT_WINDOW_MINUTES + 1)->minutes();

        $this->assertSame(1, $tournament->fresh()->spotsLeft());
        $this->postJson("/api/tournament-registrations/{$id}/pay")->assertUnprocessable();
        $this->postJson("/api/tournaments/{$tournament->id}/registrations", ['team_id' => $team->id])
            ->assertCreated()
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.status', 'pending_payment');
    }

    public function test_free_tournaments_confirm_right_away_and_pending_ones_can_be_cancelled(): void
    {
        $free = $this->tournament(['entry_fee' => 0]);
        $this->postJson("/api/tournaments/{$free->id}/registrations", ['team_id' => $this->team('Gratis')->id])
            ->assertCreated()
            ->assertJsonPath('data.status', 'confirmed');

        $paid = $this->tournament();
        $id = $this->postJson("/api/tournaments/{$paid->id}/registrations", ['team_id' => $this->team('Pagan')->id])->json('data.id');
        $this->postJson("/api/tournament-registrations/{$id}/cancel")->assertNoContent();
        $this->assertSame(8, $paid->fresh()->spotsLeft());
    }

    public function test_standings_count_three_points_per_win_and_one_per_draw(): void
    {
        $tournament = $this->tournament();
        [$a, $b, $c] = [$this->team('Alfa'), $this->team('Beta'), $this->team('Gama')];
        foreach ([$a, $b, $c] as $team) {
            $tournament->registrations()->create(['team_id' => $team->id, 'status' => 'confirmed', 'amount' => 200]);
        }
        $game = fn ($home, $away, $hs, $as) => $tournament->games()->create([
            'round' => 'Fecha 1', 'home_team_id' => $home->id, 'away_team_id' => $away->id,
            'home_score' => $hs, 'away_score' => $as, 'status' => 'played',
        ]);
        $game($a, $b, 3, 1);
        $game($b, $c, 2, 2);
        $game($c, $a, 0, 1);
        $tournament->games()->create(['round' => 'Fecha 2', 'home_team_id' => $a->id, 'away_team_id' => $c->id, 'status' => 'scheduled']);

        $this->getJson("/api/tournaments/{$tournament->id}")
            ->assertJsonPath('data.standings.0.team', 'Alfa')
            ->assertJsonPath('data.standings.0.points', 6)
            ->assertJsonPath('data.standings.0.goal_difference', 3)
            ->assertJsonPath('data.standings.1.team', 'Gama')
            ->assertJsonPath('data.standings.1.points', 1)
            ->assertJsonPath('data.standings.2.team', 'Beta')
            ->assertJsonCount(4, 'data.games');
    }
}
