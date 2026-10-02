<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'name' => $this->name,
            'sku' => $this->sku,
            'description' => $this->description,
            'category' => new ProductCategoryResource($this->whenLoaded('category')),
            'price' => (float) $this->price,
            'discount_percent' => $this->discount_percent,
            'final_price' => $this->resource->finalPrice(),
            // Units the user can buy now (stock minus what other pending orders hold).
            'available' => $this->resource->availableQuantity(),
            'photos' => $this->whenLoaded('photos', fn () => $this->photos->pluck('public_url')),
            'store' => new StoreResource($this->whenLoaded('store')),
        ];
    }
}
