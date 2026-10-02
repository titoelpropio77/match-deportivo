<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class CourtReservation extends Model
{
    public const STATUS_PENDING_PAYMENT = 'pending_payment';

    public const STATUS_PAID = 'paid';

    /**
     * Booked by venue staff, to be paid at the venue. Blocks the slot without expiring.
     */
    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Minutes an unpaid reservation holds its slot before it is released.
     */
    public const PAYMENT_WINDOW_MINUTES = 15;

    protected $fillable = [
        'booking_code',
        'court_field_id',
        'user_id',
        'sport_id',
        'reserved_on',
        'starts_at',
        'ends_at',
        'hours',
        'amount',
        'items_amount',
        'air_conditioning',
        'air_conditioning_amount',
        'lighting_amount',
        'status',
        'customer_name',
        'customer_phone',
        'source',
        'payment_method',
        'paid_at',
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
            'reserved_on' => 'date',
            'hours' => 'integer',
            'amount' => 'decimal:2',
            'items_amount' => 'decimal:2',
            'air_conditioning' => 'boolean',
            'air_conditioning_amount' => 'decimal:2',
            'lighting_amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'refunded_at' => 'datetime',
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
     * Gear rented with this reservation (balls, rackets...).
     */
    public function items(): HasMany
    {
        return $this->hasMany(CourtReservationItem::class);
    }

    /**
     * Reservations that block their slot: paid or venue-confirmed ones, and unpaid ones still inside the payment window.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->whereIn('status', [self::STATUS_PAID, self::STATUS_CONFIRMED])
                ->orWhere(function (Builder $query): void {
                    $query->where('status', self::STATUS_PENDING_PAYMENT)
                        ->where('created_at', '>=', now()->subMinutes(self::PAYMENT_WINDOW_MINUTES));
                });
        });
    }

    /**
     * Cancelled by the venue (admin panel) rather than by the player who booked it.
     */
    public function cancelledByVenue(): bool
    {
        return $this->status === self::STATUS_CANCELLED
            && $this->cancelled_by !== null
            && $this->cancelled_by !== $this->user_id;
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
