<?php

namespace App\Http\Resources;

use App\Models\EventSpaceReservation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventSpaceReservationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'event_space_id' => $this->event_space_id,
            'date' => $this->reserved_on->toDateString(),
            'start_time' => substr((string) $this->starts_at, 0, 5),
            'end_time' => substr((string) $this->ends_at, 0, 5),
            'hours' => $this->hours,
            'guests' => $this->guests,
            'event_type' => $this->event_type
                ? ['key' => $this->event_type->value, 'label' => $this->event_type->label()]
                : null,
            'amount' => (float) $this->amount,
            'status' => $this->status,
            'payment_reference' => $this->code,
            'payment_expires_at' => $this->created_at
                ?->copy()
                ->addMinutes(EventSpaceReservation::PAYMENT_WINDOW_MINUTES)
                ->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancelled_by_venue' => $this->resource->cancelledByVenue(),
            'cancellation_reason' => $this->resource->cancelledByVenue() ? $this->cancellation_reason : null,
            'refunded_at' => $this->refunded_at?->toIso8601String(),
            'notes' => $this->notes,
            'space' => new EventSpaceResource($this->whenLoaded('space')),
        ];
    }
}
