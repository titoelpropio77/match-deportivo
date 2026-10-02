<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Team registered in a tournament (same statuses and payment window as the API backend).
 */
class TournamentRegistration extends Model
{
    public const STATUS_PENDING_PAYMENT = 'pending_payment';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CANCELLED = 'cancelled';

    public const PAYMENT_WINDOW_MINUTES = 30;

    public const PAYMENT_METHODS = [
        'qr' => 'QR',
        'cash' => 'Efectivo',
        'transfer' => 'Transferencia',
        'free' => 'Sin costo',
    ];

    protected $fillable = [
        'tournament_id',
        'team_id',
        'registered_by',
        'status',
        'amount',
        'payment_method',
        'paid_at',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'refunded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function scopeHoldingSpot(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query
            ->where('status', self::STATUS_CONFIRMED)
            ->orWhere(fn (Builder $pending) => $pending
                ->where('status', self::STATUS_PENDING_PAYMENT)
                ->where('created_at', '>=', now()->subMinutes(self::PAYMENT_WINDOW_MINUTES))));
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_PENDING_PAYMENT
            && $this->created_at->lt(now()->subMinutes(self::PAYMENT_WINDOW_MINUTES));
    }

    /**
     * [label, badge color] for the panel.
     *
     * @return array{string, string}
     */
    public function statusBadge(): array
    {
        return match (true) {
            $this->isExpired() => ['Expirada (sin pago)', 'secondary'],
            $this->status === self::STATUS_PENDING_PAYMENT => ['Pago pendiente', 'warning'],
            $this->status === self::STATUS_CONFIRMED => ['Confirmada', 'success'],
            $this->refunded_at !== null => ['Anulada · reembolsada', 'secondary'],
            $this->paid_at !== null => ['Anulada · devolución pendiente', 'danger'],
            default => ['Anulada', 'danger'],
        };
    }

    public function reference(): string
    {
        return sprintf('TR-%06d', $this->id);
    }
}
