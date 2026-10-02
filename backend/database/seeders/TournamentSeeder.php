<?php

namespace Database\Seeders;

use App\Enums\TeamRole;
use App\Models\Court;
use App\Models\MatchLevel;
use App\Models\Sport;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\Tournament;
use App\Models\TournamentGame;
use App\Models\TournamentRegistration;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Sample tournaments, one per lifecycle stage, with their teams, registrations and fixture.
 * Dates are relative to today so the data always looks current. Idempotent: re-running updates
 * the same tournaments/teams and rebuilds their fixture.
 *
 * Needs SportSeeder, MatchLevelSeeder, CourtSeeder and UserSeeder first.
 */
class TournamentSeeder extends Seeder
{
    /**
     * Teams by sport key: name, short name, colour and member emails (first one is the captain).
     *
     * @var array<string, list<array{name: string, short: string, color: string, members: list<string>}>>
     */
    private const TEAMS = [
        'wallyball' => [
            ['name' => 'Los Tigres', 'short' => 'TIG', 'color' => '#F59E0B', 'members' => ['carlos.mamani@example.com', 'luis.fernandez@example.com']],
            ['name' => 'Halcones', 'short' => 'HAL', 'color' => '#2563EB', 'members' => ['jorge.vaca@example.com', 'diego.salazar@example.com']],
            ['name' => 'Muro Rojo', 'short' => 'MUR', 'color' => '#DC2626', 'members' => ['ana.rojas@example.com', 'maria.suarez@example.com']],
            ['name' => 'Remate Final', 'short' => 'REM', 'color' => '#16A34A', 'members' => ['paola.gutierrez@example.com', 'camila.rivero@example.com']],
        ],
        'football_5' => [
            ['name' => 'Deportivo Equipetrol', 'short' => 'DEP', 'color' => '#7C3AED', 'members' => ['fernando.justiniano@example.com', 'carlos.mamani@example.com', 'diego.salazar@example.com']],
            ['name' => 'Real Cambita', 'short' => 'RCA', 'color' => '#0EA5E9', 'members' => ['jorge.vaca@example.com', 'luis.fernandez@example.com', 'modesto.test@example.com']],
            ['name' => 'Atlético Urubó', 'short' => 'AUR', 'color' => '#EA580C', 'members' => ['valeria.anez@example.com', 'paola.gutierrez@example.com', 'ana.rojas@example.com']],
        ],
    ];

    public function run(): void
    {
        $teams = $this->seedTeams();
        if ($teams === []) {
            $this->command?->warn('TournamentSeeder: faltan usuarios de ejemplo (UserSeeder); no se crearon torneos.');

            return;
        }

        $today = Carbon::today();

        // In progress: league with two rounds played and one pending, so the standings have data.
        $this->seedTournament(
            court: 'Complejo Wally Sur',
            sport: 'wallyball',
            level: 'intermedio',
            attributes: [
                'name' => 'Copa Wally Sur 2026',
                'description' => 'Liga de wally por parejas, todos contra todos a una vuelta.',
                'rules' => "Partidos a 2 sets de 15 puntos; si hay empate, tercer set a 11.\nPresentarse 15 minutos antes.\nW.O. a los 10 minutos de tolerancia.",
                'format' => 'league',
                'gender' => 'mixed',
                'entry_fee' => 120,
                'prizes' => "1.º lugar: trofeo + Bs 600\n2.º lugar: medallas + Bs 200",
                'max_teams' => 4,
                'min_players_per_team' => 2,
                'max_players_per_team' => 3,
                'registration_closes_at' => $today->copy()->subDays(10)->setTime(23, 59),
                'starts_on' => $today->copy()->subDays(7),
                'ends_on' => $today->copy()->addDays(7),
                'status' => Tournament::STATUS_IN_PROGRESS,
            ],
            teams: $teams['wallyball'],
            firstRoundAt: $today->copy()->subDays(7)->setTime(19, 0),
            playedRounds: 2,
            scores: [[2, 1], [0, 2], [2, 0], [1, 2]],
        );

        // Registrations open: some teams paid, one cancelled with a reason.
        $open = $this->seedTournament(
            court: 'Canchas El Torneo',
            sport: 'football_5',
            level: 'basico',
            attributes: [
                'name' => 'Torneo Relámpago Fútbol 5',
                'description' => 'Torneo de un fin de semana: fase de grupos el sábado y eliminación directa el domingo.',
                'rules' => "Equipos de 5 + 3 suplentes.\nPartidos de 2 tiempos de 15 minutos.\nTarjeta roja: un partido de suspensión.",
                'format' => 'groups_knockout',
                'gender' => 'male',
                'entry_fee' => 250,
                'prizes' => "Campeón: trofeo + Bs 1.500\nSubcampeón: Bs 500\nGoleador: botín de oro",
                'max_teams' => 8,
                'min_players_per_team' => 5,
                'max_players_per_team' => 8,
                'registration_closes_at' => $today->copy()->addDays(10)->setTime(23, 59),
                'starts_on' => $today->copy()->addDays(14),
                'ends_on' => $today->copy()->addDays(15),
                'status' => Tournament::STATUS_OPEN,
            ],
            teams: array_slice($teams['football_5'], 0, 2),
        );
        $this->register($open, $teams['football_5'][2], cancelled: 'El equipo no completó la cantidad mínima de jugadores.');

        // Draft: not visible in the app yet.
        $this->seedTournament(
            court: 'Wally Center Equipetrol',
            sport: 'padel',
            level: 'avanzado',
            attributes: [
                'name' => 'Liga Pádel Primavera',
                'description' => 'Liga de parejas de pádel nivel avanzado. Borrador: falta definir premios.',
                'rules' => 'Partidos al mejor de 3 sets con punto de oro.',
                'format' => 'knockout',
                'gender' => 'mixed',
                'entry_fee' => 180,
                'prizes' => null,
                'max_teams' => 16,
                'min_players_per_team' => 2,
                'max_players_per_team' => 2,
                'registration_closes_at' => $today->copy()->addDays(30)->setTime(23, 59),
                'starts_on' => $today->copy()->addDays(35),
                'ends_on' => $today->copy()->addDays(63),
                'status' => Tournament::STATUS_DRAFT,
            ],
            teams: [],
        );

        // Finished: every game played, champion decided by the standings.
        $this->seedTournament(
            court: 'Complejo Wally Sur',
            sport: 'wallyball',
            level: 'basico',
            attributes: [
                'name' => 'Clausura Wally 2026',
                'description' => 'Torneo de cierre de temporada, todos contra todos.',
                'rules' => 'Partidos a 2 sets de 15 puntos.',
                'format' => 'league',
                'gender' => 'mixed',
                'entry_fee' => 80,
                'prizes' => 'Campeón: trofeo y camisetas.',
                'max_teams' => 4,
                'min_players_per_team' => 2,
                'max_players_per_team' => 3,
                'registration_closes_at' => $today->copy()->subDays(70)->setTime(23, 59),
                'starts_on' => $today->copy()->subDays(60),
                'ends_on' => $today->copy()->subDays(46),
                'status' => Tournament::STATUS_FINISHED,
            ],
            teams: $teams['wallyball'],
            firstRoundAt: $today->copy()->subDays(60)->setTime(18, 0),
            playedRounds: 3,
            scores: [[2, 0], [2, 1], [1, 2], [2, 0], [0, 2], [2, 1]],
        );
    }

    /**
     * @return array<string, list<Team>>
     */
    private function seedTeams(): array
    {
        $result = [];

        foreach (self::TEAMS as $sportKey => $definitions) {
            $sportId = Sport::query()->where('key', $sportKey)->value('id');

            foreach ($definitions as $definition) {
                $users = User::query()->whereIn('email', $definition['members'])->get()->keyBy('email');
                $captain = $users->get($definition['members'][0]);
                if ($sportId === null || $captain === null) {
                    return [];
                }

                $team = Team::query()->updateOrCreate(
                    ['name' => $definition['name'], 'sport_id' => $sportId],
                    [
                        'owner_id' => $captain->id,
                        'short_name' => $definition['short'],
                        'gender' => 'mixed',
                        'primary_color' => $definition['color'],
                    ],
                );

                foreach ($definition['members'] as $index => $email) {
                    if ($user = $users->get($email)) {
                        TeamMember::query()->updateOrCreate(
                            ['team_id' => $team->id, 'user_id' => $user->id],
                            ['role' => $index === 0 ? TeamRole::Captain->value : TeamRole::Player->value, 'jersey_number' => $index + 1],
                        );
                    }
                }

                $result[$sportKey][] = $team;
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<Team>  $teams  confirmed (paid) teams
     * @param  list<array{0: int, 1: int}>  $scores  results of the played games, in fixture order
     */
    private function seedTournament(
        string $court,
        string $sport,
        string $level,
        array $attributes,
        array $teams,
        ?Carbon $firstRoundAt = null,
        int $playedRounds = 0,
        array $scores = [],
    ): Tournament {
        $venue = Court::query()->where('name', $court)->firstOrFail();

        $tournament = Tournament::query()->updateOrCreate(
            ['name' => $attributes['name']],
            [
                ...$attributes,
                'court_id' => $venue->id,
                'sport_id' => Sport::query()->where('key', $sport)->value('id'),
                'level_id' => MatchLevel::query()->where('key', $level)->value('id'),
                'created_by' => $venue->owner_id,
            ],
        );

        foreach ($teams as $team) {
            $this->register($tournament, $team);
        }

        if ($firstRoundAt !== null) {
            $this->seedFixture($tournament, $teams, $venue, $firstRoundAt, $playedRounds, $scores);
        }

        return $tournament;
    }

    private function register(Tournament $tournament, Team $team, ?string $cancelled = null): void
    {
        $registeredAt = $tournament->registration_closes_at->copy()->subDays(5);

        TournamentRegistration::query()->updateOrCreate(
            ['tournament_id' => $tournament->id, 'team_id' => $team->id],
            [
                'registered_by' => $team->owner_id,
                'status' => $cancelled ? TournamentRegistration::STATUS_CANCELLED : TournamentRegistration::STATUS_CONFIRMED,
                'amount' => $tournament->entry_fee,
                'payment_method' => $cancelled ? null : 'cash',
                'paid_at' => $cancelled ? null : $registeredAt,
                'cancelled_at' => $cancelled ? $registeredAt->copy()->addDay() : null,
                'cancellation_reason' => $cancelled,
                'refunded_at' => null,
            ],
        );
    }

    /**
     * Round robin (circle method), one round per week. The first $playedRounds rounds get $scores.
     *
     * @param  list<Team>  $teams
     * @param  list<array{0: int, 1: int}>  $scores
     */
    private function seedFixture(Tournament $tournament, array $teams, Court $venue, Carbon $firstRoundAt, int $playedRounds, array $scores): void
    {
        $tournament->games()->delete();

        $fieldIds = $venue->fields()->orderBy('name')->pluck('id')->all();
        $ids = array_map(fn (Team $team) => $team->id, $teams);
        if (count($ids) % 2 === 1) {
            $ids[] = null;
        }

        $rounds = count($ids) - 1;
        $half = intdiv(count($ids), 2);
        $gameIndex = 0;

        for ($round = 0; $round < $rounds; $round++) {
            $date = $firstRoundAt->copy()->addWeeks($round);
            $played = $round < $playedRounds;

            for ($slot = 0; $slot < $half; $slot++) {
                [$home, $away] = [$ids[$slot], $ids[count($ids) - 1 - $slot]];
                if ($home === null || $away === null) {
                    continue;
                }

                $score = $played ? ($scores[$gameIndex] ?? [1, 1]) : [null, null];
                TournamentGame::query()->create([
                    'tournament_id' => $tournament->id,
                    'round' => 'Fecha '.($round + 1),
                    'round_order' => $round + 1,
                    'home_team_id' => $home,
                    'away_team_id' => $away,
                    'court_field_id' => $fieldIds === [] ? null : $fieldIds[$slot % count($fieldIds)],
                    'scheduled_at' => $date->copy()->addHours($slot),
                    'home_score' => $score[0],
                    'away_score' => $score[1],
                    'status' => $played ? TournamentGame::STATUS_PLAYED : TournamentGame::STATUS_SCHEDULED,
                ]);
                $gameIndex++;
            }

            // Rotate every position except the first one.
            $ids = [$ids[0], $ids[count($ids) - 1], ...array_slice($ids, 1, count($ids) - 2)];
        }
    }
}
