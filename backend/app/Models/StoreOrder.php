<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Purchase at a store, picked up there. Keep statuses in sync with admin/app/Models/StoreOrder.php.
 */
class StoreOrder extends Model
{
    /**
     * Created from the app: holds its units until paid or until the payment window ends.
     */
    public const STATUS_PENDING_PAYMENT = 'pending_payment';

    /**
     * Paid: its units already left the stock. Ready for pickup until delivered_at is set.
     */
    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLED = 'cancelled';

    public const PAYMENT_WINDOW_MINUTES = 15;

    protected $fillable = [
        'code',
        'store_id',
        'user_id',
        'customer_name',
        'customer_phone',
        'subtotal',
        'discount_amount',
        'total',
        'status',
        'source',
        'payment_method',
        'paid_at',
        'delivered_at',
        'delivered_by',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'refunded_at',
        'created_by',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StoreOrderItem::class);
    }

    /**
     * Unpaid orders still inside their payment window: their units are held.
     */
    public function scopeHoldingStock(Builder $query): void
    {
        $query->where('status', self::STATUS_PENDING_PAYMENT)
            ->where('created_at', '>=', now()->subMinutes(self::PAYMENT_WINDOW_MINUTES));
    }

    public function paymentExpired(): bool
    {
        return $this->status === self::STATUS_PENDING_PAYMENT
            && $this->created_at->lt(now()->subMinutes(self::PAYMENT_WINDOW_MINUTES));
    }

    public function cancelledByVenue(): bool
    {
        return $this->status === self::STATUS_CANCELLED
            && $this->cancelled_by !== null
            && $this->cancelled_by !== $this->user_id;
    }
}
