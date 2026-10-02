<?php

namespace App\Http\Resources;

use App\Models\StoreOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'store_id' => $this->store_id,
            'status' => $this->status,
            'subtotal' => (float) $this->subtotal,
            'discount_amount' => (float) $this->discount_amount,
            'total' => (float) $this->total,
            'payment_reference' => $this->code,
            'payment_expires_at' => $this->created_at
                ?->copy()
                ->addMinutes(StoreOrder::PAYMENT_WINDOW_MINUTES)
                ->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancelled_by_venue' => $this->resource->cancelledByVenue(),
            'cancellation_reason' => $this->resource->cancelledByVenue() ? $this->cancellation_reason : null,
            'refunded_at' => $this->refunded_at?->toIso8601String(),
            'notes' => $this->notes,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'name' => $item->name,
                'list_price' => (float) $item->list_price,
                'discount_percent' => $item->discount_percent,
                'unit_price' => (float) $item->unit_price,
                'quantity' => $item->quantity,
                'amount' => (float) $item->amount,
                'photo_url' => $item->relationLoaded('product') ? $item->product?->photos->first()?->public_url : null,
            ])),
            'store' => new StoreResource($this->whenLoaded('store')),
        ];
    }
}
