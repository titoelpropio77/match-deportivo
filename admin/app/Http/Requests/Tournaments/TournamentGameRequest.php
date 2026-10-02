<?php

namespace App\Http\Requests\Tournaments;

use App\Models\Tournament;
use App\Models\TournamentGame;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Fixture game of a tournament: teams must be registered in it and the court must belong to its venue.
 */
class TournamentGameRequest extends FormRequest
{
    protected $errorBag = 'game';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Tournament $tournament */
        $tournament = $this->route('tournament');
        $teamIds = $tournament->registrations()->pluck('team_id')->all();

        return [
            'round' => ['required', 'string', 'max:40'],
            'round_order' => ['nullable', 'integer', 'min:1', 'max:999'],
            'home_team_id' => ['nullable', 'integer', Rule::in($teamIds)],
            'away_team_id' => ['nullable', 'integer', Rule::in($teamIds), 'different:home_team_id'],
            'court_field_id' => ['nullable', 'integer', Rule::exists('court_fields', 'id')->where('court_id', $tournament->court_id)],
            'scheduled_at' => ['nullable', 'date'],
            'home_score' => ['nullable', 'integer', 'min:0', 'max:999', 'required_with:away_score'],
            'away_score' => ['nullable', 'integer', 'min:0', 'max:999', 'required_with:home_score'],
            'status' => ['nullable', Rule::in(array_keys(TournamentGame::STATUSES))],
        ];
    }

    /**
     * Validated data with defaults: first round and "scheduled".
     *
     * @return array<string, mixed>
     */
    public function gameData(): array
    {
        return [
            ...$this->validated(),
            'round_order' => $this->integer('round_order') ?: 1,
            'status' => $this->input('status') ?: TournamentGame::STATUS_SCHEDULED,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'away_team_id.different' => 'Un equipo no puede jugar contra sí mismo.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'round' => 'fecha / ronda',
            'home_team_id' => 'equipo local',
            'away_team_id' => 'equipo visitante',
            'court_field_id' => 'cancha',
            'scheduled_at' => 'día y hora',
            'home_score' => 'goles/puntos del local',
            'away_score' => 'goles/puntos del visitante',
        ];
    }
}
