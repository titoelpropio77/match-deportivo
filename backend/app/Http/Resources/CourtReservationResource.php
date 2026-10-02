<?php

namespace App\Http\Resources;

use App\Models\CourtReservation;
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
            'booking_code' => $this->booking_code,
            // Only set by "mis reservas": the match created from this booking.
            'match_id' => $this->resource->getAttribute('match_id'),
            'court_field_id' => $this->court_field_id,
            'sport_id' => $this->sport_id,
            'date' => $this->reserved_on->toDateString(),
            'start_time' => substr((string) $this->starts_at, 0, 5),
            'end_time' => substr((string) $this->ends_at, 0, 5),
            'hours' => $this->hours,
            // Total: court hours + rented gear (items_amount).
            'amount' => (float) $this->amount,
            'items_amount' => (float) $this->items_amount,
            'rentals' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'rental_item_id' => $item->rental_item_id,
                'name' => $item->name,
                'quantity' => $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'price_type' => $item->price_type,
                'amount' => (float) $item->amount,
            ])->values()),
            'price_per_hour' => $this->relationLoaded('field')
                ? (float) $this->field->price_per_hour
                : null,
            'status' => $this->status,
            'payment_reference' => $this->booking_code ?? sprintf('MD-%06d', $this->id),
            'payment_expires_at' => $this->created_at
                ?->copy()
                ->addMinutes(CourtReservation::PAYMENT_WINDOW_MINUTES)
                ->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancelled_by_venue' => $this->resource->cancelledByVenue(),
            'cancellation_reason' => $this->resource->cancelledByVenue() ? $this->cancellation_reason : null,
            'refunded_at' => $this->refunded_at?->toIso8601String(),
            'sport' => new SportResource($this->whenLoaded('sport')),
            'field' => new CourtFieldResource($this->whenLoaded('field')),
        ];
    }
}
