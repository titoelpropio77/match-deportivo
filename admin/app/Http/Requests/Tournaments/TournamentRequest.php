<?php

namespace App\Http\Requests\Tournaments;

use App\Models\Tournament;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create or edit a tournament (with optional cover).
 */
class TournamentRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $editing = $this->route('tournament') instanceof Tournament;

        return [
            'name' => ['required', 'string', 'max:120'],
            'court_id' => ['required', 'integer', 'exists:courts,id'],
            'sport_id' => ['required', 'integer', 'exists:sports,id'],
            'level_id' => ['nullable', 'integer', 'exists:match_levels,id'],
            'format' => ['required', Rule::in(array_keys(Tournament::FORMATS))],
            'gender' => ['required', Rule::in(array_keys(Tournament::GENDERS))],
            'entry_fee' => ['required', 'numeric', 'min:0', 'max:100000'],
            'prizes' => ['nullable', 'string', 'max:2000'],
            'max_teams' => ['required', 'integer', 'min:2', 'max:256'],
            'min_players_per_team' => ['required', 'integer', 'min:1', 'max:50'],
            'max_players_per_team' => ['nullable', 'integer', 'gte:min_players_per_team', 'max:60'],
            // Editing an ongoing tournament keeps a closing date that may already be past.
            'registration_closes_at' => ['required', 'date', $editing ? 'nullable' : 'after:now'],
            'starts_on' => array_filter(['required', 'date', $this->filled('registration_closes_at')
                ? 'after_or_equal:'.substr((string) $this->input('registration_closes_at'), 0, 10)
                : null]),
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'status' => ['required', Rule::in(array_keys(Tournament::STATUSES))],
            'description' => ['nullable', 'string', 'max:5000'],
            'rules' => ['nullable', 'string', 'max:10000'],
            'cover' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'remove_cover' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'starts_on.after_or_equal' => 'El torneo debe empezar después del cierre de inscripciones.',
            'max_players_per_team.gte' => 'El máximo de jugadores no puede ser menor al mínimo.',
            'registration_closes_at.after' => 'El cierre de inscripciones debe ser una fecha futura.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'court_id' => 'centro deportivo',
            'sport_id' => 'deporte',
            'level_id' => 'nivel',
            'format' => 'formato',
            'gender' => 'categoría',
            'entry_fee' => 'costo de inscripción',
            'prizes' => 'premios',
            'max_teams' => 'cupo de equipos',
            'min_players_per_team' => 'mínimo de jugadores',
            'max_players_per_team' => 'máximo de jugadores',
            'registration_closes_at' => 'cierre de inscripciones',
            'starts_on' => 'fecha de inicio',
            'ends_on' => 'fecha de fin',
            'status' => 'estado',
            'description' => 'descripción',
            'rules' => 'reglamento',
            'cover' => 'portada',
        ];
    }
}
