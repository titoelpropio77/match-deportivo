<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Product line of a store order. Name and prices are copied so history survives product edits.
 */
class StoreOrderItem extends Model
{
    protected $fillable = [
        'store_order_id',
        'product_id',
        'name',
        'list_price',
        'discount_percent',
        'unit_price',
        'quantity',
        'amount',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'list_price' => 'decimal:2',
            'discount_percent' => 'integer',
            'unit_price' => 'decimal:2',
            'quantity' => 'integer',
            'amount' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(StoreOrder::class, 'store_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
