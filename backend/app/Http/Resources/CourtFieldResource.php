<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourtFieldResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price_per_hour' => (float) $this->price_per_hour,
            'dimensions' => $this->dimensions,
            'description' => $this->description,
            'features' => CourtFeatureResource::collection($this->whenLoaded('features')),
            // Extra per hour when the player picks air conditioning; null when the court does not offer it.
            'air_conditioning_price' => $this->resource->offersAirConditioning() ? (float) $this->air_conditioning_price : null,
            // Extra per hour charged automatically for the hours from lighting_from (night).
            'lighting_price' => $this->resource->chargesLighting() ? (float) $this->lighting_price : null,
            'lighting_from' => $this->resource->chargesLighting() ? substr((string) $this->lighting_from, 0, 5) : null,
            'sports' => SportResource::collection($this->whenLoaded('sports')),
            'venue' => $this->whenLoaded('court', fn () => [
                'id' => $this->court->id,
                'name' => $this->court->name,
                'address' => $this->court->address,
                'city' => $this->court->relationLoaded('city')
                    ? new CityResource($this->court->city)
                    : null,
                'opening_time' => $this->court->opening_time,
                'closing_time' => $this->court->closing_time,
                'photos' => $this->court->relationLoaded('photos')
                    ? $this->court->photos->pluck('public_url')
                    : [],
                // Only set by the court list: the venue also rents spaces for events.
                'event_spaces_count' => $this->court->event_spaces_count,
            ]),
        ];
    }
}
