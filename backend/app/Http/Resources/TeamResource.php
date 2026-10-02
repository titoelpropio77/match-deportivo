<?php

namespace App\Http\Resources;

use App\Models\TeamMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'short_name' => $this->short_name,
            'gender' => $this->gender?->value,
            'primary_color' => $this->primary_color,
            'description' => $this->description,
            'logo_url' => $this->logo_url,
            'owner_id' => $this->owner_id,
            'sport' => new SportResource($this->whenLoaded('sport')),
            'level' => new MatchLevelResource($this->whenLoaded('level')),
            'members_count' => $this->whenCounted('members', fn () => $this->members_count, fn () => $this->whenLoaded('members', fn () => $this->members->count())),
            'is_member' => $this->whenLoaded('members', fn () => $this->members->contains('user_id', $request->user()?->id)),
            'members' => $this->whenLoaded('members', fn () => $this->members->map(fn (TeamMember $member) => [
                'id' => $member->id,
                'role' => $member->role->value,
                'jersey_number' => $member->jersey_number,
                'position' => $member->position,
                'user' => new UserResource($member->user),
            ])->values()),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
