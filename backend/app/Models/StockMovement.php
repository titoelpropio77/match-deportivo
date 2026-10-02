<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One change of a product's stock. Types are shared with admin/app/Models/StockMovement.php.
 */
class StockMovement extends Model
{
    public const TYPE_INITIAL = 'initial';

    public const TYPE_SALE = 'sale';

    /**
     * Units back on the shelf after a paid order was cancelled.
     */
    public const TYPE_RETURN = 'return';

    protected $fillable = [
        'product_id',
        'store_order_id',
        'user_id',
        'type',
        'quantity',
        'stock_after',
        'reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'stock_after' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(StoreOrder::class, 'store_order_id');
    }
}
