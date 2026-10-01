<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class CourtReservation extends Model
{
    public const STATUS_PENDING_PAYMENT = 'pending_payment';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Minutes an unpaid reservation holds its slot before it is released.
     */
    public const PAYMENT_WINDOW_MINUTES = 15;

    protected $fillable = [
        'court_field_id',
        'user_id',
        'sport_id',
        'reserved_on',
        'starts_at',
        'ends_at',
        'hours',
        'amount',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reserved_on' => 'date',
            'hours' => 'integer',
            'amount' => 'decimal:2',
        ];
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(CourtField::class, 'court_field_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    /**
     * Reservations that block their slot: paid ones and unpaid ones still inside the payment window.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->where('status', self::STATUS_PAID)
                ->orWhere(function (Builder $query): void {
                    $query->where('status', self::STATUS_PENDING_PAYMENT)
                        ->where('created_at', '>=', now()->subMinutes(self::PAYMENT_WINDOW_MINUTES));
                });
        });
    }

    public function paymentExpired(): bool
    {
        return $this->status === self::STATUS_PENDING_PAYMENT
            && $this->created_at->lt(now()->subMinutes(self::PAYMENT_WINDOW_MINUTES));
    }

    public function overlaps(Carbon $start, Carbon $end): bool
    {
        $date = $start->toDateString();
        $reservedStart = Carbon::parse($date.' '.$this->starts_at);
        $reservedEnd = Carbon::parse($date.' '.$this->ends_at);

        return $reservedStart->lt($end) && $reservedEnd->gt($start);
    }
}
