<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentGame extends Model
{
    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_PLAYED = 'played';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        'scheduled' => 'Programado',
        'played' => 'Jugado',
        'cancelled' => 'Suspendido',
    ];

    protected $fillable = [
        'tournament_id',
        'round',
        'round_order',
        'home_team_id',
        'away_team_id',
        'court_field_id',
        'scheduled_at',
        'home_score',
        'away_score',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'home_score' => 'integer',
            'away_score' => 'integer',
            'round_order' => 'integer',
        ];
    }

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(CourtField::class, 'court_field_id');
    }
}
