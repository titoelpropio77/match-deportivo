<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Tournament of a sports center. Schema owned by the API backend migrations; keep statuses,
 * formats and the standings rule in sync with backend/app/Models/Tournament.php.
 */
class Tournament extends Model
{
    public const STATUSES = [
        'draft' => ['Borrador (no se ve en la app)', 'secondary'],
        'open' => ['Inscripciones abiertas', 'success'],
        'closed' => ['Inscripciones cerradas', 'info'],
        'in_progress' => ['En curso', 'primary'],
        'finished' => ['Finalizado', 'dark'],
        'cancelled' => ['Cancelado', 'danger'],
    ];

    public const FORMATS = [
        'league' => 'Liga (todos contra todos)',
        'knockout' => 'Eliminación directa',
        'groups_knockout' => 'Grupos + eliminación',
    ];

    public const GENDERS = [
        'mixed' => 'Mixto',
        'male' => 'Masculino',
        'female' => 'Femenino',
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
            'entry_fee' => 'decimal:2',
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(TournamentRegistration::class);
    }

    public function games(): HasMany
    {
        return $this->hasMany(TournamentGame::class)->orderBy('round_order')->orderBy('scheduled_at')->orderBy('id');
    }

    /**
     * Tournaments of the venues the user can see (partner: owned, manager: assigned).
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->can('courts.view_all')) {
            $query->whereHas('court', fn (Builder $court) => $court->visibleTo($user));
        }
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::STATUSES[$this->status][1] ?? 'secondary';
    }

    public function coverUrl(): ?string
    {
        return $this->cover_path ? Storage::disk(CourtPhoto::DISK)->url($this->cover_path) : null;
    }

    public function confirmedTeamsCount(): int
    {
        return $this->registrations()->where('status', TournamentRegistration::STATUS_CONFIRMED)->count();
    }

    public function spotsTaken(): int
    {
        return $this->registrations()->holdingSpot()->count();
    }

    /**
     * Same rule as the API: 3 points per win, 1 per draw; ties by goal difference, goals for, name.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function standings(): Collection
    {
        $teams = Team::query()
            ->whereIn('id', $this->registrations()->where('status', TournamentRegistration::STATUS_CONFIRMED)->select('team_id'))
            ->get(['id', 'name']);

        $rows = $teams->mapWithKeys(fn (Team $team) => [$team->id => [
            'team' => $team->name,
            'played' => 0, 'won' => 0, 'drawn' => 0, 'lost' => 0,
            'goals_for' => 0, 'goals_against' => 0, 'goal_difference' => 0, 'points' => 0,
        ]])->all();

        foreach ($this->games()->where('status', TournamentGame::STATUS_PLAYED)->whereNotNull('home_score')->whereNotNull('away_score')->get() as $game) {
            foreach ([[$game->home_team_id, $game->home_score, $game->away_score], [$game->away_team_id, $game->away_score, $game->home_score]] as [$teamId, $for, $against]) {
                if (! isset($rows[$teamId])) {
                    continue;
                }
                $rows[$teamId]['played']++;
                $rows[$teamId]['goals_for'] += $for;
                $rows[$teamId]['goals_against'] += $against;
                $rows[$teamId]['goal_difference'] = $rows[$teamId]['goals_for'] - $rows[$teamId]['goals_against'];
                match (true) {
                    $for > $against => [$rows[$teamId]['won']++, $rows[$teamId]['points'] += 3],
                    $for === $against => [$rows[$teamId]['drawn']++, $rows[$teamId]['points']++],
                    default => $rows[$teamId]['lost']++,
                };
            }
        }

        return collect($rows)
            ->sort(fn (array $a, array $b) => [$b['points'], $b['goal_difference'], $b['goals_for'], $a['team']]
                <=> [$a['points'], $a['goal_difference'], $a['goals_for'], $b['team']])
            ->values();
    }
}
