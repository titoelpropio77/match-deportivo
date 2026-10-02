<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One change of a product's stock (types shared with the backend's App\Models\StockMovement).
 */
class StockMovement extends Model
{
    public const TYPE_INITIAL = 'initial';

    public const TYPE_RESTOCK = 'restock';

    public const TYPE_LOSS = 'loss';

    /**
     * Physical count: the stock is set to what was counted.
     */
    public const TYPE_ADJUSTMENT = 'adjustment';

    public const TYPE_SALE = 'sale';

    public const TYPE_RETURN = 'return';

    /**
     * Type => [label, badge color].
     */
    public const TYPES = [
        self::TYPE_INITIAL => ['Stock inicial', 'secondary'],
        self::TYPE_RESTOCK => ['Ingreso', 'success'],
        self::TYPE_LOSS => ['Baja / merma', 'danger'],
        self::TYPE_ADJUSTMENT => ['Ajuste por conteo', 'info'],
        self::TYPE_SALE => ['Venta', 'primary'],
        self::TYPE_RETURN => ['Devolución', 'warning'],
    ];

    /**
     * Movements staff register by hand from the panel.
     */
    public const MANUAL_TYPES = [self::TYPE_RESTOCK, self::TYPE_LOSS, self::TYPE_ADJUSTMENT];

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type][0] ?? $this->type;
    }

    public function typeColor(): string
    {
        return self::TYPES[$this->type][1] ?? 'secondary';
    }
}
