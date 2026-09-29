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
            'sports' => SportResource::collection($this->whenLoaded('sports')),
            'venue' => $this->whenLoaded('court', fn () => [
                'id' => $this->court->id,
                'name' => $this->court->name,
                'address' => $this->court->address,
                'opening_time' => $this->court->opening_time,
                'closing_time' => $this->court->closing_time,
                'photos' => $this->court->relationLoaded('photos')
                    ? $this->court->photos->pluck('url')
                    : [],
            ]),
        ];
    }
}
