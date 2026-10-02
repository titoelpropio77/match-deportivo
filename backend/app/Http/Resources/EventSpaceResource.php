<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventSpaceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => ['key' => $this->type->value, 'label' => $this->type->label()],
            'description' => $this->description,
            'price_per_hour' => (float) $this->price_per_hour,
            'capacity' => $this->capacity,
            'min_hours' => $this->min_hours,
            'amenities' => EventAmenityResource::collection($this->whenLoaded('amenities')),
            'rules' => $this->rules,
            'photo_url' => $this->resource->photoUrl(),
            'opening_time' => $this->relationLoaded('court') ? substr($this->resource->openingTime(), 0, 5) : null,
            'closing_time' => $this->relationLoaded('court') ? substr($this->resource->closingTime(), 0, 5) : null,
            'venue' => $this->whenLoaded('court', fn () => [
                'id' => $this->court->id,
                'name' => $this->court->name,
                'address' => $this->court->address,
                'latitude' => (float) $this->court->latitude,
                'longitude' => (float) $this->court->longitude,
                'city' => $this->court->relationLoaded('city') && $this->court->city
                    ? new CityResource($this->court->city)
                    : null,
                'photos' => $this->court->relationLoaded('photos')
                    ? $this->court->photos->pluck('public_url')
                    : [],
            ]),
        ];
    }
}
