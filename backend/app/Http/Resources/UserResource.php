<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'nickname' => $this->nickname,
            'email' => $this->email,
            'phone' => $this->phone,
            'preferred_position' => $this->preferred_position,
            'gender' => $this->gender,
            'birth_date' => $this->birth_date?->toDateString(),
            'favorite_sports' => SportResource::collection($this->whenLoaded('favoriteSports')),
            'photo_url' => $this->avatar_path
                ? Storage::disk('public')->url($this->avatar_path)
                : null,
            'created_at' => $this->created_at,
        ];
    }
}
