<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Item sold by a store. `stock` = units in the store; unpaid app orders hold units without taking
 * them out (same rules as the backend's App\Models\Product).
 */
class Product extends Model
{
    public const MAX_PHOTOS = 8;

    protected $fillable = [
        'store_id',
        'product_category_id',
        'name',
        'sku',
        'description',
        'price',
        'discount_percent',
        'stock',
        'min_stock',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'discount_percent' => 'integer',
            'stock' => 'integer',
            'min_stock' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ProductPhoto::class)->orderBy('order')->orderBy('id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(StoreOrderItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Products whose stock reached their warning level.
     */
    public function scopeLowStock(Builder $query): void
    {
        $query->whereNotNull('min_stock')->whereColumn('stock', '<=', 'min_stock');
    }

    public function finalPrice(): float
    {
        return round((float) $this->price * (100 - $this->discount_percent) / 100, 2);
    }

    /**
     * Units held by unpaid app orders still inside their payment window.
     */
    public function heldQuantity(): int
    {
        return (int) $this->orderItems()
            ->whereHas('order', fn (Builder $order) => $order->holdingStock())
            ->sum('quantity');
    }

    public function availableQuantity(): int
    {
        return max(0, $this->stock - $this->heldQuantity());
    }

    public function isLowStock(): bool
    {
        return $this->min_stock !== null && $this->stock <= $this->min_stock;
    }

    /**
     * Changes the stock and records why. Call inside a transaction with the product locked.
     */
    public function moveStock(int $quantity, string $type, ?int $userId = null, ?StoreOrder $order = null, ?string $reason = null): StockMovement
    {
        $this->stock += $quantity;
        $this->save();

        return $this->stockMovements()->create([
            'store_order_id' => $order?->id,
            'user_id' => $userId,
            'type' => $type,
            'quantity' => $quantity,
            'stock_after' => $this->stock,
            'reason' => $reason,
        ]);
    }
}
