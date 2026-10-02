<?php

namespace App\Models;

use App\Enums\MatchGender;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Tournament organised by a sports center. Created and managed from the admin panel
 * (keep statuses/formats in sync with admin/app/Models/Tournament.php).
 */
class Tournament extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_FINISHED = 'finished';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Statuses visible in the app (drafts are only seen in the panel).
     */
    public const PUBLIC_STATUSES = [self::STATUS_OPEN, self::STATUS_CLOSED, self::STATUS_IN_PROGRESS, self::STATUS_FINISHED];

    public const FORMATS = [
        'league' => 'Liga (todos contra todos)',
        'knockout' => 'Eliminación directa',
        'groups_knockout' => 'Grupos + eliminación',
    ];

    protected $fillable = [
        'court_id',
        'sport_id',
        'level_id',
        'created_by',
        'name',
        'description',
        'rules',
        'format',
        'gender',
        'entry_fee',
        'prizes',
        'max_teams',
        'min_players_per_team',
        'max_players_per_team',
        'registration_closes_at',
        'starts_on',
        'ends_on',
        'status',
        'cover_path',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gender' => MatchGender::class,
            'entry_fee' => 'decimal:2',
            'max_teams' => 'integer',
            'min_players_per_team' => 'integer',
            'max_players_per_team' => 'integer',
            'registration_closes_at' => 'datetime',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(MatchLevel::class, 'level_id');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(TournamentRegistration::class);
    }

    public function games(): HasMany
    {
        return $this->hasMany(TournamentGame::class)->orderBy('round_order')->orderBy('scheduled_at')->orderBy('id');
    }

    public function scopePublished(Builder $query): void
    {
        $query->whereIn('status', self::PUBLIC_STATUSES);
    }

    public function coverUrl(): ?string
    {
        if (blank($this->cover_path)) {
            return null;
        }

        return Str::startsWith($this->cover_path, ['http://', 'https://'])
            ? $this->cover_path
            : Storage::disk('public')->url($this->cover_path);
    }

    /**
     * Registrations holding a spot: confirmed, or awaiting payment inside the payment window.
     */
    public function activeRegistrations(): HasMany
    {
        return $this->registrations()->active();
    }

    public function spotsLeft(): int
    {
        return max(0, $this->max_teams - $this->activeRegistrations()->count());
    }

    /**
     * Teams can still sign up: registrations open, before the deadline and with free spots.
     */
    public function acceptsRegistrations(): bool
    {
        return $this->status === self::STATUS_OPEN
            && $this->registration_closes_at->isFuture()
            && $this->spotsLeft() > 0;
    }

    /**
     * League table from the played games: 3 points per win, 1 per draw. Every confirmed team appears,
     * sorted by points, goal difference, goals for and name.
     *
     * @return Collection<int, array{team_id: int, team: string, played: int, won: int, drawn: int, lost: int, goals_for: int, goals_against: int, goal_difference: int, points: int}>
     */
    public function standings(): Collection
    {
        $teams = Team::query()
            ->whereIn('id', $this->registrations()->where('status', TournamentRegistration::STATUS_CONFIRMED)->select('team_id'))
            ->get(['id', 'name', 'short_name', 'primary_color', 'logo_path']);

        $rows = $teams->mapWithKeys(fn (Team $team) => [$team->id => [
            'team_id' => $team->id,
            'team' => $team->name,
            'short_name' => $team->short_name,
            'primary_color' => $team->primary_color,
            'logo_url' => $team->logo_url,
            'played' => 0,
            'won' => 0,
            'drawn' => 0,
            'lost' => 0,
            'goals_for' => 0,
            'goals_against' => 0,
            'goal_difference' => 0,
            'points' => 0,
        ]])->all();

        $played = $this->games()
            ->where('status', TournamentGame::STATUS_PLAYED)
            ->whereNotNull('home_score')
            ->whereNotNull('away_score')
            ->get();

        foreach ($played as $game) {
            foreach ([[$game->home_team_id, $game->home_score, $game->away_score], [$game->away_team_id, $game->away_score, $game->home_score]] as [$teamId, $for, $against]) {
                if (! isset($rows[$teamId])) {
                    continue;
                }
                $rows[$teamId]['played']++;
                $rows[$teamId]['goals_for'] += $for;
                $rows[$teamId]['goals_against'] += $against;
                $rows[$teamId]['goal_difference'] = $rows[$teamId]['goals_for'] - $rows[$teamId]['goals_against'];
                if ($for > $against) {
                    $rows[$teamId]['won']++;
                    $rows[$teamId]['points'] += 3;
                } elseif ($for === $against) {
                    $rows[$teamId]['drawn']++;
                    $rows[$teamId]['points']++;
                } else {
                    $rows[$teamId]['lost']++;
                }
            }
        }

        return collect($rows)
            ->sort(fn (array $a, array $b) => [$b['points'], $b['goal_difference'], $b['goals_for'], $a['team']]
                <=> [$a['points'], $a['goal_difference'], $a['goals_for'], $b['team']])
            ->values();
    }
}
