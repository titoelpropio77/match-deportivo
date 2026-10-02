<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sale of a store: bought from the app (pending payment → paid) or at the counter (paid at once).
 * Keep statuses in sync with the backend's App\Models\StoreOrder.
 */
class StoreOrder extends Model
{
    public const STATUS_PENDING_PAYMENT = 'pending_payment';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLED = 'cancelled';

    public const PAYMENT_WINDOW_MINUTES = 15;

    /**
     * Status shown in the panel => [label, badge color]. All but pending/paid/cancelled are derived.
     */
    public const DISPLAY_STATUSES = [
        'pending_payment' => ['Pago pendiente', 'warning'],
        'ready' => ['Pagada · por entregar', 'info'],
        'delivered' => ['Entregada', 'success'],
        'cancelled' => ['Anulada', 'danger'],
        'refund_pending' => ['Anulada · devolución pendiente', 'danger'],
        'refunded' => ['Anulada · reembolsada', 'secondary'],
        'expired' => ['Expirada (sin pago)', 'secondary'],
    ];

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

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function deliveredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }

    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->can('courts.view_all')) {
            $query->whereHas('store.court', fn (Builder $court) => $court->visibleTo($user));
        }
    }

    /**
     * Unpaid orders still inside their payment window: their units are held.
     */
    public function scopeHoldingStock(Builder $query): void
    {
        $query->where('status', self::STATUS_PENDING_PAYMENT)
            ->where('created_at', '>=', now()->subMinutes(self::PAYMENT_WINDOW_MINUTES));
    }

    public function scopeCollected(Builder $query): void
    {
        $query->whereNotNull('paid_at')->whereNull('refunded_at');
    }

    public function displayStatus(): string
    {
        return match (true) {
            $this->status === self::STATUS_CANCELLED && $this->refunded_at !== null => 'refunded',
            $this->status === self::STATUS_CANCELLED && $this->paid_at !== null => 'refund_pending',
            $this->status === self::STATUS_PAID && $this->delivered_at !== null => 'delivered',
            $this->status === self::STATUS_PAID => 'ready',
            $this->isExpired() => 'expired',
            default => $this->status,
        };
    }

    public function statusLabel(): string
    {
        return self::DISPLAY_STATUSES[$this->displayStatus()][0] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::DISPLAY_STATUSES[$this->displayStatus()][1] ?? 'secondary';
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_PENDING_PAYMENT
            && $this->created_at?->lt(now()->subMinutes(self::PAYMENT_WINDOW_MINUTES));
    }

    public function canBeDelivered(): bool
    {
        return $this->status === self::STATUS_PAID && $this->delivered_at === null;
    }

    public function canBeCancelled(): bool
    {
        return $this->status === self::STATUS_PAID
            || ($this->status === self::STATUS_PENDING_PAYMENT && ! $this->isExpired());
    }

    public function canBeRefunded(): bool
    {
        return $this->status === self::STATUS_CANCELLED && $this->paid_at !== null && $this->refunded_at === null;
    }

    public function customerName(): string
    {
        return $this->user?->name ?? $this->customer_name ?? 'Cliente de mostrador';
    }

    public function customerPhone(): ?string
    {
        return $this->user?->phone ?? $this->customer_phone;
    }

    public function itemsCount(): int
    {
        return (int) $this->items->sum('quantity');
    }
}
