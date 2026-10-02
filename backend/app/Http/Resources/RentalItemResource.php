<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RentalItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'court_id' => $this->court_id,
            'sport_id' => $this->sport_id,
            'sport' => new SportResource($this->whenLoaded('sport')),
            'name' => $this->name,
            'description' => $this->description,
            'price' => (float) $this->price,
            // per_hour: price × hours of the reservation; flat: once per reservation.
            'price_type' => $this->price_type,
            'stock' => $this->stock,
        ];
    }
}
