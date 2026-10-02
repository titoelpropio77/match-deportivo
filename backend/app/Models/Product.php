<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Item sold by a store. `stock` counts the units in the store; unpaid app orders hold units for
 * StoreOrder::PAYMENT_WINDOW_MINUTES without taking them out. Keep the stock rules in sync with
 * admin/app/Models/Product.php.
 */
class Product extends Model
{
    /**
     * Held units preloaded by loadHeldQuantities() (null = query on demand).
     */
    public ?int $heldCache = null;

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

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Price after the discount.
     */
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

    /**
     * Units that can still be sold: stock minus what pending orders hold.
     */
    public function availableQuantity(): int
    {
        return max(0, $this->stock - ($this->heldCache ?? $this->heldQuantity()));
    }

    /**
     * Loads the held units of many products with one query (lists).
     *
     * @param  iterable<Product>  $products
     */
    public static function loadHeldQuantities(iterable $products): void
    {
        $products = collect($products);
        $held = StoreOrderItem::query()
            ->whereIn('product_id', $products->pluck('id'))
            ->whereHas('order', fn (Builder $order) => $order->holdingStock())
            ->groupBy('product_id')
            ->selectRaw('product_id, sum(quantity) as held')
            ->pluck('held', 'product_id');

        $products->each(fn (Product $product) => $product->heldCache = (int) ($held[$product->id] ?? 0));
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
