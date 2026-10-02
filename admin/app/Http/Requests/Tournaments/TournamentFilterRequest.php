<?php

namespace App\Http\Requests\Tournaments;

use App\Models\Tournament;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filters of the tournament list (TournamentDataTable).
 */
class TournamentFilterRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'court_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(array_keys(Tournament::STATUSES))],
            'sport_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['court_id' => 'centro deportivo', 'status' => 'estado', 'sport_id' => 'deporte'];
    }
}
