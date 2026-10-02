<?php

namespace Tests\Feature;

use App\Models\Court;
use App\Models\MatchLevel;
use App\Models\MatchModel;
use App\Models\PlayerRating;
use App\Models\RatingTag;
use App\Models\Sport;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlayerProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_summarises_the_player_stats_and_reputation(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 2)->setTime(12, 0));
        $player = User::factory()->create([
            'name' => 'Carlos Mamani',
            'nickname' => 'Charly',
            'gender' => 'male',
            'preferred_position' => 'Delantero',
            'birth_date' => '1995-06-15',
            'phone' => '70011122',
        ]);
        $organizer = User::factory()->create();
        $futbol = Sport::query()->create(['key' => 'futbol_5', 'name' => 'Fútbol 5']);
        $wally = Sport::query()->create(['key' => 'wally', 'name' => 'Wally']);
        $level = MatchLevel::query()->create(['key' => 'basico', 'name' => 'Básico', 'order' => 1]);
        $arena = $this->court('Arena Norte');
        $sur = $this->court('Complejo Sur');

        // Two finished matches at two venues (2 h + 1 h), one future, one cancelled, one where he was only pending.
        $m1 = $this->match($organizer, $futbol, $level, $arena, now()->subDays(3), 2);
        $m2 = $this->match($organizer, $futbol, $level, $sur, now()->subDay(), 1);
        $m3 = $this->match($organizer, $wally, $level, $arena, now()->subDays(10), 1);
        $future = $this->match($organizer, $futbol, $level, $arena, now()->addDay(), 1);
        $cancelled = $this->match($organizer, $futbol, $level, $arena, now()->subDays(5), 1, 'cancelled');
        foreach ([$m1, $m2, $m3, $future, $cancelled] as $match) {
            $match->players()->create(['user_id' => $player->id, 'quantity_slots' => 1, 'status' => 'confirmed']);
        }
        $pending = $this->match($organizer, $futbol, $level, $arena, now()->subDays(2), 1);
        $pending->players()->create(['user_id' => $player->id, 'quantity_slots' => 1, 'status' => 'pending']);
        $this->match($player, $futbol, $level, $arena, now()->addDays(2), 1);

        $crack = RatingTag::query()->create(['sport_id' => $futbol->id, 'key' => 'crack', 'label' => 'Crack', 'polarity' => 'positive']);
        $tardon = RatingTag::query()->create(['sport_id' => $futbol->id, 'key' => 'tardon', 'label' => 'Llegó tarde', 'polarity' => 'negative']);
        $absent = RatingTag::query()->create(['sport_id' => $futbol->id, 'key' => 'no_vino', 'label' => 'No vino', 'polarity' => 'negative', 'marks_absence' => true]);
        PlayerRating::query()->create(['match_id' => $m1->id, 'reviewer_id' => $organizer->id, 'user_id' => $player->id, 'stars' => 5])->tags()->sync([$crack->id]);
        PlayerRating::query()->create(['match_id' => $m2->id, 'reviewer_id' => $organizer->id, 'user_id' => $player->id, 'stars' => 4])->tags()->sync([$crack->id, $tardon->id]);
        PlayerRating::query()->create(['match_id' => $m3->id, 'reviewer_id' => $organizer->id, 'user_id' => $player->id, 'did_not_attend' => true])->tags()->sync([$absent->id]);

        $team = Team::query()->create(['owner_id' => $player->id, 'sport_id' => $futbol->id, 'name' => 'Los Tigres']);
        $team->members()->create(['user_id' => $player->id, 'role' => 'captain']);

        Sanctum::actingAs($organizer);
        $this->getJson("/api/users/{$player->id}/profile")
            ->assertOk()
            ->assertJsonPath('data.user.nickname', 'Charly')
            ->assertJsonPath('data.user.gender', 'male')
            ->assertJsonMissingPath('data.user.email')
            ->assertJsonMissingPath('data.user.phone')
            ->assertJsonPath('data.is_me', false)
            ->assertJsonPath('data.age', 31)
            ->assertJsonPath('data.stats.matches_played', 3)
            ->assertJsonPath('data.stats.courts_played', 2)
            ->assertJsonPath('data.stats.hours_played', 4)
            ->assertJsonPath('data.stats.matches_organized', 1)
            ->assertJsonPath('data.stats.upcoming_matches', 1)
            ->assertJsonPath('data.stats.sports.0.name', 'Fútbol 5')
            ->assertJsonPath('data.stats.sports.0.matches', 2)
            ->assertJsonPath('data.rating.average', 4.5)
            ->assertJsonPath('data.rating.count', 2)
            ->assertJsonPath('data.rating.distribution.5', 1)
            ->assertJsonPath('data.rating.distribution.4', 1)
            ->assertJsonPath('data.rating.no_shows', 1)
            ->assertJsonPath('data.rating.attendance_rate', 67)
            ->assertJsonPath('data.top_tags.0.label', 'Crack')
            ->assertJsonPath('data.top_tags.0.count', 2)
            ->assertJsonCount(2, 'data.top_tags')
            ->assertJsonPath('data.teams.0.name', 'Los Tigres')
            ->assertJsonPath('data.teams.0.is_captain', true)
            ->assertJsonPath('data.recent_matches.0.court', 'Complejo Sur');

        Sanctum::actingAs($player);
        $this->getJson("/api/users/{$player->id}/profile")
            ->assertJsonPath('data.is_me', true)
            ->assertJsonPath('data.user.phone', '70011122');
    }

    public function test_a_new_player_has_an_empty_profile(): void
    {
        $player = User::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/users/{$player->id}/profile")
            ->assertOk()
            ->assertJsonPath('data.age', null)
            ->assertJsonPath('data.stats.matches_played', 0)
            ->assertJsonPath('data.rating.average', null)
            ->assertJsonPath('data.rating.attendance_rate', null)
            ->assertJsonCount(0, 'data.teams');
    }

    public function test_players_edit_their_own_profile(): void
    {
        $player = User::factory()->create(['gender' => 'male']);
        Sanctum::actingAs($player);

        $this->patchJson('/api/me', [
            'nickname' => 'Charly',
            'preferred_position' => 'Arquero',
            'birth_date' => '2000-01-31',
        ])->assertOk()
            ->assertJsonPath('data.nickname', 'Charly')
            ->assertJsonPath('data.birth_date', '2000-01-31');

        $this->patchJson('/api/me', ['birth_date' => now()->subYear()->toDateString()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('birth_date');
    }

    private function court(string $name): Court
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

    private function match(User $organizer, Sport $sport, MatchLevel $level, Court $court, $start, int $hours, string $status = 'open'): MatchModel
    {
        return MatchModel::query()->create([
            'organizer_id' => $organizer->id,
            'sport_id' => $sport->id,
            'level_id' => $level->id,
            'court_id' => $court->id,
            'gender' => 'mixed',
            'scheduled_at' => $start,
            'start_time' => $start,
            'end_time' => $start->copy()->addHours($hours),
            'total_players' => 10,
            'missing_players' => 9,
            'max_players' => 10,
            'status' => $status,
        ]);
    }
}
