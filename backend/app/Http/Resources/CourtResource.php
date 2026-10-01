<?php

namespace App\Http\Resources;

use App\Enums\CourtFieldFeature;
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
            'photos' => $this->whenLoaded('photos', fn () => $this->photos->pluck('public_url')),
            // Physical courts of the venue, so clients can pick which ones a match uses.
            'fields' => $this->whenLoaded('fields', fn () => $this->fields->sortBy('name')->values()->map(fn ($field) => [
                'id' => $field->id,
                'name' => $field->name,
                'price_per_hour' => (float) $field->price_per_hour,
                'dimensions' => $field->dimensions,
                'description' => $field->description,
                'features' => $field->features?->map(fn (CourtFieldFeature $feature) => ['key' => $feature->value, 'label' => $feature->label()])->values() ?? [],
                'sports' => SportResource::collection($field->sports),
            ])),
        ];
    }
}
