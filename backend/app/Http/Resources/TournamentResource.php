<?php

namespace App\Http\Resources;

use App\Models\Tournament;
use App\Models\TournamentRegistration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Tournament
 */
class TournamentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $confirmed = $this->registrations_count ?? $this->registrations()->where('status', TournamentRegistration::STATUS_CONFIRMED)->count();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'format' => $this->format,
            'format_label' => Tournament::FORMATS[$this->format] ?? $this->format,
            'gender' => $this->gender?->value,
            'entry_fee' => (float) $this->entry_fee,
            'prizes' => $this->prizes,
            'max_teams' => $this->max_teams,
            'teams_count' => $confirmed,
            'spots_left' => $this->spotsLeft(),
            'min_players_per_team' => $this->min_players_per_team,
            'max_players_per_team' => $this->max_players_per_team,
            'registration_closes_at' => $this->registration_closes_at?->toIso8601String(),
            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'status' => $this->status,
            'accepts_registrations' => $this->acceptsRegistrations(),
            'cover_url' => $this->coverUrl(),
            'sport' => new SportResource($this->whenLoaded('sport')),
            'level' => new MatchLevelResource($this->whenLoaded('level')),
            'venue' => $this->whenLoaded('court', fn () => [
                'id' => $this->court->id,
                'name' => $this->court->name,
                'address' => $this->court->address,
                'city' => $this->court->relationLoaded('city') ? $this->court->city?->name : null,
            ]),
        ];
    }
}
