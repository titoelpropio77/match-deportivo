<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'phone' => $this->phone,
            'cover_url' => $this->resource->coverUrl(),
            'categories' => ProductCategoryResource::collection($this->whenLoaded('categories')),
            'products_count' => $this->whenCounted('products'),
            // Products currently on sale (discount_percent > 0).
            'offers_count' => $this->whenHas('offers_count'),
            'venue' => $this->whenLoaded('court', fn () => [
                'id' => $this->court->id,
                'name' => $this->court->name,
                'address' => $this->court->address,
                'opening_time' => substr((string) $this->court->opening_time, 0, 5),
                'closing_time' => substr((string) $this->court->closing_time, 0, 5),
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
