<?php

namespace Tests\Feature;

use App\Models\Court;
use App\Models\Sport;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\Tournament;
use App\Models\TournamentGame;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RankingTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_platform_returns_every_list(): void
    {
        $this->getJson('/api/rankings')
            ->assertOk()
            ->assertJsonPath('data.period', 'all')
            ->assertJsonPath('data.courts', [])
            ->assertJsonPath('data.teams', [])
            ->assertJsonPath('data.players', [])
            ->assertJsonPath('data.products', [])
            ->assertJsonPath('data.stores', []);
    }

    public function test_centers_without_bookings_are_ordered_by_rating(): void
    {
        $this->makeCourt('Regular', ['average' => 3.9, 'count' => 5]);
        $this->makeCourt('Excelente', ['average' => 4.9, 'count' => 40]);

        $this->getJson('/api/rankings')
            ->assertOk()
            ->assertJsonPath('data.courts.0.name', 'Excelente')
            ->assertJsonPath('data.courts.0.bookings', 0)
            ->assertJsonPath('data.courts.0.rating', 4.9)
            ->assertJsonPath('data.courts.1.name', 'Regular');
    }

    public function test_teams_and_players_come_from_played_tournament_games(): void
    {
        [$tournament, $tigres, $halcones] = $this->tournamentWithTwoTeams();
        $carlos = User::factory()->create(['name' => 'Carlos', 'nickname' => 'Charly']);
        TeamMember::query()->create(['team_id' => $tigres->id, 'user_id' => $carlos->id, 'role' => 'captain']);

        $this->game($tournament, $tigres, $halcones, 3, 1);
        $this->game($tournament, $halcones, $tigres, 2, 2);
        $this->game($tournament, $tigres, $halcones, null, null, TournamentGame::STATUS_SCHEDULED);

        $this->getJson('/api/rankings')
            ->assertOk()
            ->assertJsonPath('data.teams.0.name', 'Los Tigres')
            ->assertJsonPath('data.teams.0.played', 2)
            ->assertJsonPath('data.teams.0.won', 1)
            ->assertJsonPath('data.teams.0.drawn', 1)
            ->assertJsonPath('data.teams.0.points', 4)
            ->assertJsonPath('data.teams.0.goal_difference', 2)
            ->assertJsonPath('data.teams.1.name', 'Halcones')
            ->assertJsonPath('data.teams.1.points', 1)
            ->assertJsonPath('data.players.0.name', 'Charly')
            ->assertJsonPath('data.players.0.matches_played', 2);
    }

    public function test_month_period_ignores_older_games(): void
    {
        [$tournament, $tigres, $halcones] = $this->tournamentWithTwoTeams();
        $this->game($tournament, $tigres, $halcones, 1, 0, scheduledAt: now()->subMonths(2));

        $this->getJson('/api/rankings')->assertJsonCount(2, 'data.teams');
        $this->getJson('/api/rankings?period=month')
            ->assertOk()
            ->assertJsonPath('data.period', 'month')
            ->assertJsonCount(0, 'data.teams');
    }

    /**
     * @return array{0: Tournament, 1: Team, 2: Team}
     */
    private function tournamentWithTwoTeams(): array
    {
        $sport = Sport::query()->create(['key' => 'wallyball', 'name' => 'Wally']);
        $owner = User::factory()->create();
        $tournament = Tournament::query()->create([
            'court_id' => $this->makeCourt('Centro')->id,
            'sport_id' => $sport->id,
            'name' => 'Copa',
            'max_teams' => 4,
            'registration_closes_at' => now()->subWeek(),
            'starts_on' => now()->subDays(3),
            'status' => Tournament::STATUS_IN_PROGRESS,
        ]);
        $team = fn (string $name) => Team::query()->create(['owner_id' => $owner->id, 'sport_id' => $sport->id, 'name' => $name]);

        return [$tournament, $team('Los Tigres'), $team('Halcones')];
    }

    private function game(Tournament $tournament, Team $home, Team $away, ?int $homeScore, ?int $awayScore, string $status = TournamentGame::STATUS_PLAYED, $scheduledAt = null): void
    {
        TournamentGame::query()->create([
            'tournament_id' => $tournament->id,
            'round' => 'Fecha 1',
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'scheduled_at' => $scheduledAt ?? now()->subDay(),
            'home_score' => $homeScore,
            'away_score' => $awayScore,
            'status' => $status,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $reviewData
     */
    private function makeCourt(string $name, ?array $reviewData = null): Court
    {
        return Court::query()->create([
            'name' => $name,
            'address' => 'Santa Cruz',
            'latitude' => -17.78,
            'longitude' => -63.18,
            'opening_time' => '08:00',
            'closing_time' => '22:00',
            'review_data' => $reviewData,
        ]);
    }
}
