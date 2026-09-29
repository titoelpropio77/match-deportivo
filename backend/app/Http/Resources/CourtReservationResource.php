<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourtReservationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'court_field_id' => $this->court_field_id,
            'sport_id' => $this->sport_id,
            'date' => $this->reserved_on->toDateString(),
            'start_time' => substr((string) $this->starts_at, 0, 5),
            'end_time' => substr((string) $this->ends_at, 0, 5),
            'hours' => $this->hours,
            'amount' => (float) $this->amount,
            'price_per_hour' => $this->relationLoaded('field')
                ? (float) $this->field->price_per_hour
                : null,
            'status' => $this->status,
            'sport' => new SportResource($this->whenLoaded('sport')),
            'field' => new CourtFieldResource($this->whenLoaded('field')),
        ];
    }
}
