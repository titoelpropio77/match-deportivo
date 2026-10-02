<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A team signed up for a tournament. Unpaid registrations hold a spot for PAYMENT_WINDOW_MINUTES.
 */
class TournamentRegistration extends Model
{
    public const STATUS_PENDING_PAYMENT = 'pending_payment';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CANCELLED = 'cancelled';

    public const PAYMENT_WINDOW_MINUTES = 30;

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

    /**
     * Holds a spot: confirmed, or unpaid and still inside the payment window.
     */
    public function scopeActive(Builder $query): void
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
     * Status shown to the player: an expired unpaid registration reads as "expired".
     */
    public function displayStatus(): string
    {
        return $this->isExpired() ? 'expired' : $this->status;
    }

    public function reference(): string
    {
        return sprintf('TR-%06d', $this->id);
    }

    public function paymentExpiresAt(): ?string
    {
        return $this->status === self::STATUS_PENDING_PAYMENT
            ? $this->created_at->copy()->addMinutes(self::PAYMENT_WINDOW_MINUTES)->toIso8601String()
            : null;
    }
}
