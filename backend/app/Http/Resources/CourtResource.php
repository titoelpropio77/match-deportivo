<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourtResource extends JsonResource
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
            'owner_id' => $this->owner_id,
            'city_id' => $this->city_id,
            'city' => new CityResource($this->whenLoaded('city')),
            'name' => $this->name,
            'address' => $this->address,
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,
            'opening_time' => $this->opening_time,
            'closing_time' => $this->closing_time,
            'review_data' => $this->review_data,
            'sports' => SportResource::collection($this->whenLoaded('sports')),
            'photos' => $this->whenLoaded('photos', fn () => $this->photos->pluck('url')),
        ];
    }
}
